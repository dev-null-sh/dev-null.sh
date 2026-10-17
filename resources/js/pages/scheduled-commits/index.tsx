import { useLang } from '@erag/lang-sync-inertia/react';
import { Head, Link, router, usePoll } from '@inertiajs/react';
import { ArrowLeft, Clock3, LockKeyhole } from 'lucide-react';
import { useState } from 'react';
import LogController from '@/actions/App/Http/Controllers/LogController';
import ScheduledCommitController from '@/actions/App/Http/Controllers/ScheduledCommitController';
import { FoldedCommitBody } from '@/components/commit-body';
import { Button } from '@/components/ui/button';
import { useNow } from '@/hooks/use-now';
import AppLayout from '@/layouts/app-layout';
import type { BodySegment } from '@/types';

type ScheduledCommit = {
    id: string;
    body: string | null;
    body_segments: BodySegment[];
    preview_segments: BodySegment[] | null;
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
    const [canceling, setCanceling] = useState<string | null>(null);
    usePoll(30_000, { only: ['scheduledCommits'] });
    const { __ } = useLang();

    const labels = {
        pending: __('settings.scheduled.status.pending'),
        approved: __('settings.scheduled.status.approved'),
        rejected: __('settings.scheduled.status.rejected'),
        deleted: __('settings.scheduled.status.deleted'),
    };

    return (
        <>
            <Head title={__('settings.scheduled.title')} />
            <div className="flex h-14 items-center gap-3 border-b px-4">
                <Link
                    href={LogController.show('following')}
                    aria-label={__('settings.scheduled.back_to_log')}
                    className="rounded-md p-2 text-muted-foreground hover:bg-accent"
                >
                    <ArrowLeft className="size-4" />
                </Link>
                <h1 className="font-semibold">
                    {__('settings.scheduled.title')}
                </h1>
                <LockKeyhole
                    className="ml-auto size-4 text-muted-foreground"
                    aria-label={__('settings.scheduled.private')}
                />
            </div>
            <div className="border-b px-5 py-4 sm:px-6">
                <p className="text-sm leading-relaxed text-muted-foreground">
                    {__('settings.scheduled.intro')}
                </p>
                <p className="mt-2 font-mono text-xs text-muted-foreground">
                    {__('settings.scheduled.times_in', {
                        timezone:
                            Intl.DateTimeFormat().resolvedOptions().timeZone,
                    })}
                </p>
            </div>
            {scheduledCommits.data.length === 0 ? (
                <div className="space-y-3 px-6 py-14 text-center">
                    <Clock3 className="mx-auto size-6 text-muted-foreground" />
                    <p className="font-medium">
                        {__('settings.scheduled.empty_title')}
                    </p>
                    <p className="text-sm text-muted-foreground">
                        {__('settings.scheduled.empty_description')}
                    </p>
                    <Button asChild variant="outline" size="sm">
                        <Link href={LogController.show('following')}>
                            {__('settings.scheduled.back_to_log')}
                        </Link>
                    </Button>
                </div>
            ) : (
                <ul className="divide-y">
                    {scheduledCommits.data.map((scheduled) => {
                        const due =
                            new Date(scheduled.scheduled_at).getTime() <=
                            now.getTime();
                        return (
                            <li
                                key={scheduled.id}
                                className="space-y-3 px-5 py-5 sm:px-6"
                            >
                                <div className="flex items-start justify-between gap-3">
                                    <div className="min-w-0">
                                        <p className="text-sm font-medium">
                                            {scheduled.status === 'scheduled'
                                                ? due
                                                    ? __(
                                                          'settings.scheduled.status.waiting',
                                                      )
                                                    : __(
                                                          'settings.scheduled.status.scheduled',
                                                      )
                                                : labels[scheduled.status]}
                                        </p>
                                        <time
                                            dateTime={scheduled.scheduled_at}
                                            className="mt-1 block font-mono text-xs text-muted-foreground"
                                        >
                                            {new Date(
                                                scheduled.scheduled_at,
                                            ).toLocaleString(undefined, {
                                                dateStyle: 'medium',
                                                timeStyle: 'short',
                                            })}
                                        </time>
                                    </div>
                                    {scheduled.status === 'scheduled' && (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            disabled={canceling !== null}
                                            aria-label={__(
                                                'settings.scheduled.cancel_label',
                                                {
                                                    time: new Date(
                                                        scheduled.scheduled_at,
                                                    ).toLocaleString(
                                                        undefined,
                                                        {
                                                            dateStyle: 'medium',
                                                            timeStyle: 'short',
                                                        },
                                                    ),
                                                },
                                            )}
                                            onClick={() => {
                                                setCanceling(scheduled.id);
                                                router.delete(
                                                    ScheduledCommitController.destroy(
                                                        scheduled.id,
                                                    ),
                                                    {
                                                        preserveScroll: true,
                                                        onFinish: () =>
                                                            setCanceling(null),
                                                    },
                                                );
                                            }}
                                        >
                                            {canceling === scheduled.id
                                                ? __(
                                                      'settings.scheduled.canceling',
                                                  )
                                                : __(
                                                      'settings.scheduled.cancel',
                                                  )}
                                        </Button>
                                    )}
                                </div>
                                {scheduled.body && (
                                    <FoldedCommitBody
                                        segments={scheduled.body_segments}
                                        previewSegments={
                                            scheduled.preview_segments
                                        }
                                        className="text-[15px]"
                                    />
                                )}
                                {scheduled.status === 'scheduled' && (
                                    <p className="text-xs text-muted-foreground">
                                        {due
                                            ? __('settings.scheduled.due_note')
                                            : __(
                                                  'settings.scheduled.private_note',
                                              )}
                                    </p>
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
                            {__('settings.scheduled.previous')}
                        </Link>
                    ) : (
                        <span />
                    )}
                    {scheduledCommits.next_page_url && (
                        <Link href={scheduledCommits.next_page_url}>
                            {__('settings.scheduled.next')}
                        </Link>
                    )}
                </div>
            )}
        </>
    );
}

Index.layout = AppLayout;
