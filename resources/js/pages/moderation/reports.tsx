import { Head, InfiniteScroll, router } from '@inertiajs/react';
import { Check, X } from 'lucide-react';
import { useState } from 'react';
import { store as reject } from '@/actions/App/Http/Controllers/CommitRejectionController';
import { destroy } from '@/actions/App/Http/Controllers/CommitReportController';
import { CommitCard } from '@/components/commit-card';
import { EmptyFeed } from '@/components/commit-feed';
import { ModerationTabs } from '@/components/moderation-tabs';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { formatFullDateTime } from '@/lib/utils';
import type { Commit, CursorPaginated } from '@/types';

type Report = {
    id: string;
    reason: string;
    details: string | null;
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
    const [busy, setBusy] = useState(false);
    const resolve = (
        route: ReturnType<typeof destroy> | ReturnType<typeof reject>,
    ) =>
        router.visit(route, {
            preserveScroll: true,
            reset: ['reports'],
            onStart: () => setBusy(true),
            onFinish: () => setBusy(false),
        });

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
                        <CommitCard
                            key={report.id}
                            commit={report.commit}
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
                                            disabled={busy}
                                            onClick={() =>
                                                resolve(destroy(report.id))
                                            }
                                        >
                                            <Check />
                                            Dismiss report
                                        </Button>
                                        <Button
                                            variant="destructive"
                                            size="sm"
                                            disabled={busy}
                                            onClick={() =>
                                                resolve(reject(report.commit))
                                            }
                                        >
                                            <X />
                                            Reject commit
                                        </Button>
                                    </div>
                                </div>
                            }
                        />
                    ))}
                </InfiniteScroll>
            )}
        </>
    );
}

Reports.layout = AppLayout;
