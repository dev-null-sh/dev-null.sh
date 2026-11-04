import { Head, InfiniteScroll, router } from '@inertiajs/react';
import { Check, X } from 'lucide-react';
import { toast } from 'sonner';
import { store as reject } from '@/actions/App/Http/Controllers/CommitRejectionController';
import { destroy } from '@/actions/App/Http/Controllers/CommitReportController';
import { CommitCard } from '@/components/commit-card';
import { EmptyFeed } from '@/components/commit-feed';
import { CommitPoster } from '@/components/commit-poster';
import { GraphCanvas } from '@/components/graph-canvas';
import { ModerationTabs } from '@/components/moderation-tabs';
import { Button } from '@/components/ui/button';
import { useMarkCommitDeleted } from '@/hooks/use-deleted-resources';
import AppLayout from '@/layouts/app-layout';
import { formatFullDateTime } from '@/lib/utils';
import type { Commit, CursorPaginated } from '@/types';

type Report = {
    id: string;
    reason: string;
    details: string | null;
    attachments: {
        url: string;
        description: string | null;
        width: number;
        height: number;
    }[];
    created_at: string;
    commit: Commit;
};

export default function Reports({
    reports,
    reasons,
}: {
    reports: CursorPaginated<Report>;
    reasons: Record<string, string>;
}) {
    const markCommitDeleted = useMarkCommitDeleted();
    const resolve = (
        route: ReturnType<typeof destroy> | ReturnType<typeof reject>,
        isResolved: (report: Report) => boolean,
        onNotFound: () => void,
    ) =>
        router
            .optimistic((props: { reports: CursorPaginated<Report> }) => ({
                reports: {
                    ...props.reports,
                    data: props.reports.data.filter(
                        (report) => !isResolved(report),
                    ),
                },
            }))
            .visit(route, {
                preserveScroll: true,
                reset: ['reports'],
                onHttpException: (response) => {
                    if (response.status !== 404) {
                        return;
                    }

                    onNotFound();

                    return false;
                },
            });

    /**
     * A dismissal is about the report, which leaves the queue once its commit is gone or no longer published, while the
     * commit itself may still be live.
     */
    const dismiss = (report: Report) =>
        resolve(
            destroy(report.id),
            ({ id }) => id === report.id,
            () => {
                toast('This report is no longer in the queue.');
                router.reload({ only: ['reports'], reset: ['reports'] });
            },
        );

    return (
        <>
            <Head title="Reported commits" />
            <div className="sticky top-0 z-10 bg-card">
                <div className="flex h-14 items-center border-b px-4">
                    <h1 className="font-semibold">Moderation</h1>
                    <p className="ml-auto text-sm text-muted-foreground">
                        Oldest first
                    </p>
                </div>
                <ModerationTabs active="reports" />
            </div>
            <p className="border-b px-4 py-3 text-sm text-muted-foreground">
                Private reports on published commits. Dismiss a report to keep
                the commit, or reject the original.
            </p>
            {reports.data.length === 0 ? (
                <div className="px-6 py-16 text-center">
                    <EmptyFeed command="nothing to review">
                        No reports are waiting for review.
                    </EmptyFeed>
                </div>
            ) : (
                <InfiniteScroll
                    data="reports"
                    onlyNext
                    preserveUrl
                    loading={
                        <p
                            className="p-4 text-sm text-muted-foreground"
                            role="status"
                        >
                            Loading reports…
                        </p>
                    }
                >
                    {reports.data.map((report) => (
                        // A canvas each: reports are unrelated to each other, and two can be about the same commit,
                        // whose node they would otherwise share.
                        <GraphCanvas key={report.id}>
                            <CommitCard
                                commit={report.commit}
                                endsWithDot
                                canDelete={false}
                                actions={
                                    <div className="mt-3 grid gap-2 border-t pt-3">
                                        <p className="text-sm font-medium">
                                            {reasons[report.reason]}
                                        </p>
                                        {report.details && (
                                            <p className="text-sm leading-relaxed wrap-anywhere whitespace-pre-wrap">
                                                {report.details}
                                            </p>
                                        )}
                                        {report.attachments?.map((image) => (
                                            <a
                                                key={image.url}
                                                href={image.url}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className="block"
                                            >
                                                <img
                                                    src={image.url}
                                                    alt={
                                                        image.description ||
                                                        'Report attachment'
                                                    }
                                                    width={image.width}
                                                    height={image.height}
                                                    loading="lazy"
                                                    className="max-h-80 rounded-md object-contain"
                                                />
                                            </a>
                                        ))}
                                        <CommitPoster commit={report.commit} />
                                        <p className="text-xs text-muted-foreground">
                                            Reported{' '}
                                            <time dateTime={report.created_at}>
                                                {formatFullDateTime(
                                                    report.created_at,
                                                )}
                                            </time>
                                        </p>
                                        <div className="flex flex-wrap gap-2">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() => dismiss(report)}
                                            >
                                                <Check />
                                                Dismiss report
                                            </Button>
                                            <Button
                                                variant="destructive"
                                                size="sm"
                                                onClick={() =>
                                                    resolve(
                                                        reject(report.commit),
                                                        ({ commit }) =>
                                                            commit.id ===
                                                            report.commit.id,
                                                        () =>
                                                            markCommitDeleted(
                                                                report.commit
                                                                    .id,
                                                            ),
                                                    )
                                                }
                                            >
                                                <X />
                                                Reject commit
                                            </Button>
                                        </div>
                                    </div>
                                }
                            />
                        </GraphCanvas>
                    ))}
                </InfiniteScroll>
            )}
        </>
    );
}

Reports.layout = AppLayout;
