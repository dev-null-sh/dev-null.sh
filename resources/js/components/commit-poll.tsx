import { router, useHttp, usePage } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { useEffect, useEffectEvent, useId, useRef, useState } from 'react';
import { toast } from 'sonner';
import CommitPollController from '@/actions/App/Http/Controllers/CommitPollController';
import CommitPollVoteController from '@/actions/App/Http/Controllers/CommitPollVoteController';
import { BodySegments } from '@/components/commit-body';
import { StableLabel } from '@/components/stable-label';
import { Button } from '@/components/ui/button';
import { useCommitNotFound } from '@/hooks/use-deleted-resources';
import { useNow } from '@/hooks/use-now';
import { useOwnsCommit } from '@/hooks/use-owns-commit';
import { bodySegments } from '@/lib/code';
import { changesSince, readAt } from '@/lib/commit-changes';
import { exactCountTitle, formatCount, formatExactCount } from '@/lib/numbers';
import { coalesce, commitChannel, listen } from '@/lib/realtime';
import { cn } from '@/lib/utils';
import type { Commit, Poll } from '@/types';

function timeLeft(closesAt: string, now: Date): string {
    const minutes = Math.ceil(
        (new Date(closesAt).getTime() - now.getTime()) / 60_000,
    );

    if (minutes >= 1440) {
        return `${Math.floor(minutes / 1440)}d left`;
    }

    if (minutes >= 60) {
        return `${Math.floor(minutes / 60)}h left`;
    }

    return `${Math.max(minutes, 1)}m left`;
}

const failedVoteMessage = 'Could not record your vote. Try again.';

function votesLabel(count: number, format = formatExactCount): string {
    return `${format(count)} ${count === 1 ? 'vote' : 'votes'}`;
}

function percentage(votes: number, total: number): number {
    return total === 0 ? 0 : Math.round((votes / total) * 100);
}

// Only mentions the body also makes are linked, since those are the people the commit notifies.
function OptionLabel({
    label,
    mentions,
    isStatic,
}: {
    label: string;
    mentions: Commit['mentions'];
    isStatic: boolean;
}) {
    return (
        <BodySegments
            segments={bodySegments(label)}
            mentions={mentions}
            isStatic={isStatic}
        />
    );
}

export function CommitPoll({
    commit,
    poll: initialPoll,
    isStatic = false,
    voteUrl,
}: {
    commit: Pick<Commit, 'id' | 'status'> & {
        author: Pick<Commit['author'], 'id'>;
    } & Partial<Pick<Commit, 'mentions'>>;
    poll: Poll;
    isStatic?: boolean;
    /**
     * Where to vote, for a poll shown on another site: its options lead there, and it isn't read again from the page.
     */
    voteUrl?: string;
}) {
    const { auth, now: serverNow } = usePage().props;
    const ownsCommit = useOwnsCommit();
    const commitNotFound = useCommitNotFound();
    const now = useNow();
    const id = useId();
    const [poll, setPoll] = useState(initialPoll);
    const request = useHttp<{ option: string }, { poll: Poll }>({
        option: '',
    });
    const choice = request.data.option;
    // A read started before a vote went out may come back without it, so only the latest of either one counts.
    const latest = useRef(0);
    // When the poll shown was read, on the server's clock, to ask whether votes came in since after missing updates.
    const readSince = useRef(Math.min(serverNow - 5_000, readAt()));

    const read = useEffectEvent(async (signal: AbortSignal) => {
        if (request.processing) {
            return;
        }

        const started = ++latest.current;
        const startedAt = readAt();
        const response = await fetch(CommitPollController.show.url(commit), {
            headers: { Accept: 'application/json' },
            cache: 'no-store',
            signal,
        });

        if (response.ok && started === latest.current && !request.processing) {
            readSince.current = startedAt;
            setPoll(((await response.json()) as { poll: Poll }).poll);
        }
    });
    const isApproved = commit.status === 'approved';
    const closesAt = poll.is_closed ? null : poll.closes_at;

    // Someone else voting changes the results the viewer may see, and closing shows the final ones to everyone, even
    // when nobody votes again. Choosing an option and not voting yet is kept either way.
    useEffect(() => {
        if (!isApproved || voteUrl !== undefined) {
            return;
        }

        const controller = new AbortController();
        const refresh = coalesce(() => read(controller.signal), 500);
        const stop = listen(
            commitChannel(commit.id),
            { poll: refresh.request },
            () =>
                void changesSince(commit.id, readSince.current).then(
                    (change) =>
                        (change === null || change.changed) &&
                        refresh.request(),
                ),
        );
        const closing =
            closesAt === null
                ? undefined
                : window.setTimeout(
                      refresh.request,
                      Math.max(new Date(closesAt).getTime() - Date.now(), 0) +
                          1_000,
                  );

        return () => {
            stop();
            refresh.cancel();
            window.clearTimeout(closing);
            controller.abort();
        };
    }, [commit.id, isApproved, closesAt, voteUrl]);

    const hasExpired =
        poll.is_closed ||
        (poll.closes_at !== null && new Date(poll.closes_at) <= now);
    const canVote =
        auth.user !== null &&
        commit.status === 'approved' &&
        !ownsCommit(commit) &&
        poll.closes_at !== null &&
        !poll.has_voted &&
        !hasExpired;

    const status =
        poll.closes_at === null
            ? 'Voting opens once approved'
            : hasExpired
              ? poll.total_votes === null
                  ? 'Poll closed'
                  : 'Final results'
              : timeLeft(poll.closes_at, now);

    const vote = async () => {
        if (choice === '' || request.processing) {
            return;
        }

        let isNotFound = false;

        try {
            const response = await request.submit(
                CommitPollVoteController.store(commit),
                {
                    onHttpException: (response) => {
                        isNotFound = commitNotFound(commit.id, response);
                    },
                    onError: (errors) =>
                        toast.error(
                            Object.values(errors)[0] ?? failedVoteMessage,
                        ),
                },
            );

            if (response === undefined) {
                return;
            }

            latest.current++;
            setPoll(response.poll);
            router.flushAll();
        } catch {
            if (!isNotFound) {
                toast.error(failedVoteMessage);
            }
        }
    };

    return (
        <div className="mt-3" data-test="commit-poll">
            {poll.total_votes === null && isStatic ? (
                <div role="group" aria-label="Poll options">
                    <ul className="space-y-2">
                        {poll.options.map((option) => (
                            <li key={option.id}>
                                {voteUrl === undefined ? (
                                    <span className="block rounded-md border px-3 py-2 text-[15px] wrap-anywhere">
                                        <OptionLabel
                                            label={option.label}
                                            mentions={commit.mentions ?? null}
                                            isStatic
                                        />
                                    </span>
                                ) : (
                                    <a
                                        href={voteUrl}
                                        className="block rounded-md border px-3 py-2 text-[15px] wrap-anywhere transition-colors hover:bg-accent"
                                    >
                                        <OptionLabel
                                            label={option.label}
                                            mentions={commit.mentions ?? null}
                                            isStatic
                                        />
                                    </a>
                                )}
                            </li>
                        ))}
                    </ul>
                    <p className="mt-2 font-mono text-[13px] text-muted-foreground">
                        {voteUrl !== undefined &&
                        poll.closes_at !== null &&
                        !hasExpired
                            ? `Vote on /dev/null · ${status}`
                            : status}
                    </p>
                </div>
            ) : poll.total_votes === null ? (
                <fieldset disabled={!canVote || request.processing}>
                    <legend className="sr-only">Poll options</legend>
                    <div className="space-y-2">
                        {poll.options.map((option) => (
                            <label
                                key={option.id}
                                className="flex cursor-pointer items-center gap-3 rounded-md border px-3 py-2 text-[15px] transition-colors hover:bg-accent has-checked:border-primary has-checked:bg-primary-soft has-focus-visible:ring-[3px] has-focus-visible:ring-ring/50 has-disabled:cursor-default has-disabled:hover:bg-transparent"
                            >
                                <input
                                    type="radio"
                                    name={`poll-${id}`}
                                    value={option.id}
                                    checked={choice === option.id}
                                    onChange={() =>
                                        request.setData('option', option.id)
                                    }
                                    className="size-4 accent-primary"
                                />
                                <span className="min-w-0 wrap-anywhere">
                                    <OptionLabel
                                        label={option.label}
                                        mentions={commit.mentions ?? null}
                                        isStatic={false}
                                    />
                                </span>
                            </label>
                        ))}
                    </div>
                    <div className="mt-2 flex items-center justify-between gap-3">
                        <p className="font-mono text-[13px] text-muted-foreground">
                            {status}
                        </p>
                        {canVote && (
                            <Button
                                type="button"
                                size="sm"
                                onClick={vote}
                                disabled={choice === '' || request.processing}
                                data-test="poll-vote-button"
                            >
                                <StableLabel
                                    label="Vote"
                                    pendingLabel="Voting…"
                                    isPending={request.processing}
                                />
                            </Button>
                        )}
                    </div>
                </fieldset>
            ) : (
                <div role="group" aria-label="Poll results">
                    <ul className="space-y-2">
                        {poll.options.map((option) => {
                            const share = percentage(
                                option.votes_count ?? 0,
                                poll.total_votes ?? 0,
                            );

                            return (
                                <li
                                    key={option.id}
                                    className="relative overflow-hidden rounded-md border px-3 py-2 text-[15px]"
                                >
                                    <span
                                        aria-hidden
                                        className={cn(
                                            'absolute inset-y-0 left-0 bg-muted transition-[width] duration-500 ease-out motion-reduce:transition-none',
                                            option.voted && 'bg-primary-soft',
                                        )}
                                        style={{ width: `${share}%` }}
                                    />
                                    <span className="relative flex items-center gap-2">
                                        <span
                                            className={cn(
                                                'min-w-0 flex-1 wrap-anywhere',
                                                option.voted && 'font-medium',
                                            )}
                                        >
                                            <OptionLabel
                                                label={option.label}
                                                mentions={
                                                    commit.mentions ?? null
                                                }
                                                isStatic={isStatic}
                                            />
                                            {option.voted && (
                                                <>
                                                    <Check
                                                        aria-hidden
                                                        className="ml-1.5 inline size-4 align-[-2px] text-primary"
                                                    />
                                                    <span className="sr-only">
                                                        , your vote
                                                    </span>
                                                </>
                                            )}
                                        </span>
                                        <span
                                            className="shrink-0 font-mono text-[13px] tabular-nums"
                                            title={votesLabel(
                                                option.votes_count ?? 0,
                                            )}
                                        >
                                            {share}%
                                            <span className="sr-only">
                                                ,{' '}
                                                {votesLabel(
                                                    option.votes_count ?? 0,
                                                )}
                                            </span>
                                        </span>
                                    </span>
                                </li>
                            );
                        })}
                    </ul>
                    <p
                        className="mt-2 font-mono text-[13px] text-muted-foreground"
                        role="status"
                        data-test="poll-summary"
                    >
                        <span
                            aria-hidden
                            title={
                                exactCountTitle(poll.total_votes) === undefined
                                    ? undefined
                                    : votesLabel(poll.total_votes)
                            }
                        >
                            {votesLabel(poll.total_votes, formatCount)}
                        </span>
                        <span className="sr-only">
                            {votesLabel(poll.total_votes)}
                        </span>
                        <span aria-hidden> · </span>
                        {status}
                    </p>
                </div>
            )}
        </div>
    );
}
