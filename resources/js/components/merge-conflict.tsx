import { Link, router, useHttp, usePage } from '@inertiajs/react';
import { Check, GitMerge } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import MergeConflictVoteController from '@/actions/App/Http/Controllers/MergeConflictVoteController';
import { timeLeft } from '@/components/commit-poll';
import { Button } from '@/components/ui/button';
import { UserAvatar } from '@/components/user-avatar';
import { useCommitNotFound } from '@/hooks/use-deleted-resources';
import { useNow } from '@/hooks/use-now';
import { formatExactCount } from '@/lib/numbers';
import { cn } from '@/lib/utils';
import { show } from '@/routes/user';
import type {
    Commit,
    MergeConflict as MergeConflictData,
    MergeConflictSide,
} from '@/types';

type Side = 'ours' | 'theirs';

const failedVoteMessage = 'Could not record your pick. Try again.';

function handleOf(side: MergeConflictSide): string {
    return side.user === null ? 'someone' : `@${side.user.handle}`;
}

function winnerOf(mergeConflict: MergeConflictData): Side | null {
    const ours = mergeConflict.ours.votes ?? 0;
    const theirs = mergeConflict.theirs.votes ?? 0;

    if (ours === theirs) {
        return null;
    }

    return ours > theirs ? 'ours' : 'theirs';
}

function ConflictSide({
    marker,
    side,
    isPicked,
    isWinner,
    total,
}: {
    marker: string;
    side: MergeConflictSide;
    isPicked: boolean;
    isWinner: boolean;
    total: number | null;
}) {
    const share =
        total === null || side.votes === null
            ? null
            : total === 0
              ? 0
              : Math.round((side.votes / total) * 100);

    return (
        <div className="px-3 py-2">
            <p
                className={cn(
                    'flex items-center gap-2 text-muted-foreground',
                    isWinner && 'text-foreground',
                )}
            >
                <span aria-hidden>{marker}</span>
                {side.user !== null && (
                    <Link
                        href={show(side.user)}
                        className="flex min-w-0 items-center gap-1.5 hover:underline"
                    >
                        <UserAvatar
                            user={side.user}
                            className="size-4"
                            pixels={32}
                        />
                        <span className="truncate">{handleOf(side)}</span>
                    </Link>
                )}
                {isPicked && (
                    <span className="flex items-center gap-0.5 text-primary">
                        <Check className="size-3" aria-hidden />
                        your pick
                    </span>
                )}
                {share !== null && (
                    <span className="ml-auto shrink-0 tabular-nums">
                        {share}% · {formatExactCount(side.votes ?? 0)}
                    </span>
                )}
            </p>
            <p className="mt-1 font-sans text-[15px] wrap-anywhere whitespace-pre-wrap text-foreground">
                {side.body}
            </p>
        </div>
    );
}

/**
 * Two replies Tom found taking opposite sides, shown like the markers of a git merge conflict for readers to resolve.
 */
export function MergeConflict({
    commit,
    mergeConflict: initialMergeConflict,
}: {
    commit: Pick<Commit, 'id'>;
    mergeConflict: MergeConflictData;
}) {
    const { auth } = usePage().props;
    const commitNotFound = useCommitNotFound();
    const now = useNow();
    const [mergeConflict, setMergeConflict] = useState(initialMergeConflict);
    const [previousInitial, setPreviousInitial] =
        useState(initialMergeConflict);
    if (initialMergeConflict !== previousInitial) {
        setPreviousInitial(initialMergeConflict);
        setMergeConflict(initialMergeConflict);
    }
    const request = useHttp<
        { side: Side | '' },
        { mergeConflict: MergeConflictData | null }
    >({ side: '' });

    const isClosed =
        mergeConflict.is_closed || new Date(mergeConflict.closes_at) <= now;
    const hasCounts = mergeConflict.ours.votes !== null;
    const total = hasCounts
        ? (mergeConflict.ours.votes ?? 0) + (mergeConflict.theirs.votes ?? 0)
        : null;
    const winner = isClosed && hasCounts ? winnerOf(mergeConflict) : null;
    const canVote = auth.user !== null && mergeConflict.can_vote && !isClosed;
    const nowTimestamp = now.getTime();

    // Voting closed while the page was open: fetch the final count, as the one the reader has may be missing or stale.
    useEffect(() => {
        if (isClosed && !mergeConflict.is_closed) {
            router.reload({ only: ['mergeConflict'] });
        }
    }, [isClosed, mergeConflict.is_closed, nowTimestamp]);

    const status = !isClosed
        ? timeLeft(mergeConflict.closes_at, now)
        : winner === null
          ? hasCounts && total !== 0
              ? 'Resolved: a tie'
              : 'Closed'
          : `Resolved: kept ${handleOf(mergeConflict[winner])}`;

    const pick = async (side: Side) => {
        if (request.processing) {
            return;
        }

        let isNotFound = false;
        request.transform(() => ({ side }));

        try {
            const response = await request.submit(
                MergeConflictVoteController.store(commit),
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

            if (response?.mergeConflict) {
                setMergeConflict(response.mergeConflict);
                router.flushAll();
            }
        } catch {
            if (!isNotFound) {
                toast.error(failedVoteMessage);
            }
        }
    };

    return (
        <section
            aria-label="Merge conflict in the replies"
            className="mt-3 overflow-hidden rounded-md border font-mono text-[13px]"
            data-test="merge-conflict"
        >
            <header className="flex items-center gap-2 border-b bg-muted/40 px-3 py-1.5">
                <GitMerge className="size-3.5 shrink-0" aria-hidden />
                <span className="font-semibold">CONFLICT</span>
                <span className="truncate text-muted-foreground">
                    in the replies
                </span>
                <span className="ml-auto shrink-0 text-muted-foreground">
                    {status}
                </span>
            </header>

            <ConflictSide
                marker="<<<<<<<"
                side={mergeConflict.ours}
                isPicked={mergeConflict.side === 'ours'}
                isWinner={winner === 'ours'}
                total={total}
            />
            <p aria-hidden className="px-3 text-muted-foreground">
                =======
            </p>
            <ConflictSide
                marker=">>>>>>>"
                side={mergeConflict.theirs}
                isPicked={mergeConflict.side === 'theirs'}
                isWinner={winner === 'theirs'}
                total={total}
            />

            {canVote && (
                <div className="flex flex-wrap gap-2 border-t px-3 py-2">
                    {(['ours', 'theirs'] as const).map((side) => (
                        <Button
                            key={side}
                            type="button"
                            size="sm"
                            variant="outline"
                            disabled={request.processing}
                            onClick={() => pick(side)}
                            className="min-w-0 font-mono"
                            data-test={`merge-conflict-${side}`}
                        >
                            <span className="truncate">
                                Accept {handleOf(mergeConflict[side])}
                            </span>
                        </Button>
                    ))}
                </div>
            )}
        </section>
    );
}
