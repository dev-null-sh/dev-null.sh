import { Head, Link, router, usePoll } from '@inertiajs/react';
import {
    ArrowLeft,
    Clock3,
    ImageIcon,
    LockKeyhole,
    Trash2,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import LogController from '@/actions/App/Http/Controllers/LogController';
import ScheduledCommitController from '@/actions/App/Http/Controllers/ScheduledCommitController';
import ScheduledCommitSubmissionController from '@/actions/App/Http/Controllers/ScheduledCommitSubmissionController';
import { FoldedCommitBody } from '@/components/commit-body';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useDateTime } from '@/hooks/use-date-time';
import { useNow } from '@/hooks/use-now';
import AppLayout from '@/layouts/app-layout';
import { backgroundPollOptions } from '@/lib/background-poll';
import { emojify } from '@/lib/emoji';
import { readerClock } from '@/lib/utils';
import type { BodySegment } from '@/types';

type ScheduledCommit = {
    id: string;
    body: string | null;
    body_segments: BodySegment[];
    preview_segments: BodySegment[] | null;
    poll_options: string[] | null;
    image_count: number;
    scheduled_at: string;
    submitted_at: string | null;
    status: 'scheduled' | 'pending' | 'approved' | 'rejected' | 'deleted';
};

export default function Index({
    scheduledCommits,
}: {
    scheduledCommits: {
        data: ScheduledCommit[];
        prev_page_url: string | null;
        next_page_url: string | null;
    };
}) {
    const now = useNow();
    const { formatFullDateTime } = useDateTime();
    const [busy, setBusy] = useState<{
        id: string;
        action: 'commit' | 'cancel';
    } | null>(null);
    const returnFocusTo = useRef<string | null>(null);
    const [sent, setSent] = useState(new Map<string, ScheduledCommit>());
    // Rows keep the order they had when the page opened, and a row sent with Commit now keeps showing what was sent
    // (submission clears it), so it stays where it was at its size instead of jumping below the outstanding ones and
    // pulling the rows under it up. Submitted rows sort after older ones, so the refreshed page can leave it out: it
    // stays from what was sent. A fresh visit sorts and trims them again.
    const [order] = useState(() =>
        scheduledCommits.data.map((scheduled) => scheduled.id),
    );
    const rank = (id: string) => {
        const index = order.indexOf(id);

        return index === -1 ? order.length : index;
    };
    const missing = [...sent.values()]
        .filter(
            (before) =>
                !scheduledCommits.data.some(
                    (scheduled) => scheduled.id === before.id,
                ),
        )
        .map((before) => ({ ...before, status: 'pending' as const }));
    const rows = [...scheduledCommits.data, ...missing]
        .sort((a, b) => rank(a.id) - rank(b.id))
        .map((scheduled) => {
            const before = sent.get(scheduled.id);

            return before && scheduled.status !== 'scheduled'
                ? {
                      ...before,
                      submitted_at: scheduled.submitted_at,
                      status: scheduled.status,
                  }
                : scheduled;
        });
    usePoll(30_000, backgroundPollOptions({ only: ['scheduledCommits'] }));

    useEffect(() => {
        if (busy !== null || returnFocusTo.current === null) {
            return;
        }

        const row = document.querySelector<HTMLElement>(
            `[data-scheduled-commit="${returnFocusTo.current}"]`,
        );
        returnFocusTo.current = null;
        // The page has the new props by now: back on Commit now when the commit is still outstanding, on its row once
        // the button is gone.
        (row?.querySelector<HTMLElement>('[data-commit-now]') ?? row)?.focus({
            preventScroll: true,
        });
    }, [busy]);

    const commitNow = (scheduled: ScheduledCommit) => {
        let claimed = false;
        returnFocusTo.current = scheduled.id;
        // Kept before the response renders, and dropped again unless the server says this request claimed it.
        setSent((current) => new Map(current).set(scheduled.id, scheduled));
        setBusy({ id: scheduled.id, action: 'commit' });
        router.post(
            ScheduledCommitSubmissionController.store(scheduled.id),
            {},
            {
                preserveScroll: true,
                onSuccess: (page) => {
                    claimed = page.flash.submitted === scheduled.id;
                },
                onFinish: () => {
                    if (!claimed) {
                        setSent((current) => {
                            const next = new Map(current);
                            next.delete(scheduled.id);

                            return next;
                        });
                    }

                    setBusy(null);
                },
            },
        );
    };

    const labels = {
        pending: 'Submitted for review',
        approved: 'Approved',
        rejected: 'Rejected',
        deleted: 'Deleted',
    };

    return (
        <>
            <Head title="Scheduled commits" />
            <div className="flex h-14 items-center gap-3 border-b px-4">
                <Link
                    href={LogController.show('following')}
                    aria-label="Back to Log"
                    className="rounded-md p-2 text-muted-foreground hover:bg-accent"
                >
                    <ArrowLeft className="size-4" />
                </Link>
                <h1 className="font-semibold">Scheduled commits</h1>
                <LockKeyhole
                    className="ml-auto size-4 text-muted-foreground"
                    aria-label="Private"
                />
            </div>
            <div className="border-b px-5 py-4 sm:px-6">
                <p className="text-sm leading-relaxed text-muted-foreground">
                    Only you can see this list. At the scheduled time, each
                    commit goes through the usual review process. Scheduling is
                    not approval.
                </p>
                <p className="mt-2 font-mono text-xs text-muted-foreground">
                    Times in {readerClock().timeZone}
                </p>
            </div>
            {scheduledCommits.data.length === 0 ? (
                <div className="space-y-3 px-6 py-14 text-center">
                    <Clock3 className="mx-auto size-6 text-muted-foreground" />
                    <p className="font-medium">No scheduled commits</p>
                    <p className="text-sm text-muted-foreground">
                        Choose Schedule in the composer to submit later.
                    </p>
                    <Button asChild variant="outline" size="sm">
                        <Link href={LogController.show('following')}>
                            Back to Log
                        </Link>
                    </Button>
                </div>
            ) : (
                <ul className="divide-y">
                    {rows.map((scheduled) => {
                        const due =
                            new Date(scheduled.scheduled_at).getTime() <=
                            now.getTime();
                        const when = formatFullDateTime(scheduled.scheduled_at);
                        return (
                            <li
                                key={scheduled.id}
                                className="space-y-3 p-5 outline-none focus-visible:bg-accent sm:px-6"
                                tabIndex={-1}
                                data-scheduled-commit={scheduled.id}
                            >
                                <div className="flex items-start justify-between gap-3">
                                    <div className="min-w-0">
                                        <p className="text-sm font-medium">
                                            {scheduled.status === 'scheduled'
                                                ? due
                                                    ? 'Waiting for submission'
                                                    : 'Scheduled'
                                                : labels[scheduled.status]}
                                        </p>
                                        <time
                                            dateTime={scheduled.scheduled_at}
                                            className="mt-1 block font-mono text-xs text-muted-foreground"
                                        >
                                            {when}
                                        </time>
                                    </div>
                                    {scheduled.status === 'scheduled' && (
                                        <div className="flex shrink-0 gap-2">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                disabled={busy !== null}
                                                aria-label={`Commit now instead of at ${when}`}
                                                data-commit-now
                                                onClick={() =>
                                                    commitNow(scheduled)
                                                }
                                            >
                                                {busy?.id === scheduled.id &&
                                                busy.action === 'commit'
                                                    ? 'Committing…'
                                                    : 'Commit now'}
                                            </Button>
                                            <Tooltip>
                                                <TooltipTrigger asChild>
                                                    <button
                                                        type="button"
                                                        disabled={busy !== null}
                                                        aria-label={`Cancel the commit scheduled for ${when}`}
                                                        onClick={() => {
                                                            setBusy({
                                                                id: scheduled.id,
                                                                action: 'cancel',
                                                            });
                                                            router.delete(
                                                                ScheduledCommitController.destroy(
                                                                    scheduled.id,
                                                                ),
                                                                {
                                                                    preserveScroll: true,
                                                                    onFinish:
                                                                        () =>
                                                                            setBusy(
                                                                                null,
                                                                            ),
                                                                },
                                                            );
                                                        }}
                                                        className="self-center rounded-md p-1.5 text-muted-foreground transition hover:bg-destructive/10 hover:text-destructive disabled:pointer-events-none disabled:opacity-50"
                                                    >
                                                        <Trash2 className="size-4" />
                                                    </button>
                                                </TooltipTrigger>
                                                <TooltipContent>
                                                    Cancel
                                                </TooltipContent>
                                            </Tooltip>
                                        </div>
                                    )}
                                </div>
                                {scheduled.body && (
                                    <FoldedCommitBody
                                        segments={scheduled.body_segments}
                                        mentions={null}
                                        previewSegments={
                                            scheduled.preview_segments
                                        }
                                        className="text-[15px]"
                                    />
                                )}
                                {scheduled.poll_options && (
                                    <ul
                                        aria-label="Poll options"
                                        className="space-y-2"
                                    >
                                        {scheduled.poll_options.map(
                                            (option) => (
                                                <li
                                                    key={option}
                                                    className="rounded-md border px-3 py-2 text-[15px] wrap-anywhere"
                                                >
                                                    {emojify(option)}
                                                </li>
                                            ),
                                        )}
                                    </ul>
                                )}
                                {scheduled.image_count > 0 && (
                                    <p className="flex items-center gap-1.5 text-sm text-muted-foreground">
                                        <ImageIcon
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        {scheduled.image_count === 1
                                            ? '1 image'
                                            : `${scheduled.image_count} images`}
                                    </p>
                                )}
                                {scheduled.status === 'scheduled' ? (
                                    <p className="text-xs text-muted-foreground">
                                        {due
                                            ? 'Waiting for the scheduler. If submission fails, it is retried automatically.'
                                            : 'Private until submitted. You can commit it now or cancel it before submission.'}
                                    </p>
                                ) : (
                                    sent.has(scheduled.id) && (
                                        <p className="text-xs text-muted-foreground">
                                            Submitted just now, ahead of its
                                            scheduled time. It goes through the
                                            usual review process.
                                        </p>
                                    )
                                )}
                            </li>
                        );
                    })}
                </ul>
            )}
            {(scheduledCommits.prev_page_url ||
                scheduledCommits.next_page_url) && (
                <div className="flex justify-between border-t px-6 py-4 text-sm text-primary">
                    {scheduledCommits.prev_page_url ? (
                        <Link href={scheduledCommits.prev_page_url}>
                            Previous
                        </Link>
                    ) : (
                        <span />
                    )}
                    {scheduledCommits.next_page_url && (
                        <Link href={scheduledCommits.next_page_url}>Next</Link>
                    )}
                </div>
            )}
        </>
    );
}

Index.layout = AppLayout;
