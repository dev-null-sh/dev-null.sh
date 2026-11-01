import { Head, InfiniteScroll, Link, router } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { destroy } from '@/actions/App/Http/Controllers/UserReportController';
import { EmptyFeed } from '@/components/commit-feed';
import { ModerationTabs } from '@/components/moderation-tabs';
import { Button } from '@/components/ui/button';
import { UserAvatar } from '@/components/user-avatar';
import {
    UserModerationMenu,
    UserModerationStatus,
} from '@/components/user-moderation-menu';
import type { UserModeration } from '@/components/user-moderation-menu';
import AppLayout from '@/layouts/app-layout';
import { cn, formatFullDateTime } from '@/lib/utils';
import { show } from '@/routes/user';
import type { CursorPaginated, User } from '@/types';

type Report = {
    id: string;
    reason: string;
    details: string | null;
    created_at: string;
    user: User;
    moderation: UserModeration | null;
};

export default function ProfileReports({
    reports,
    reasons,
}: {
    reports: CursorPaginated<Report>;
    reasons: Record<string, string>;
}) {
    const [busy, setBusy] = useState(false);

    const dismiss = (report: Report) =>
        router.visit(destroy(report.id), {
            preserveScroll: true,
            reset: ['reports'],
            onStart: () => setBusy(true),
            onFinish: () => setBusy(false),
            onHttpException: (response) => {
                if (response.status !== 404) {
                    return;
                }

                toast('This report is no longer in the queue.');
                router.reload({ only: ['reports'], reset: ['reports'] });

                return false;
            },
        });

    return (
        <>
            <Head title="Reported profiles" />
            <div className="sticky top-0 z-10 bg-card">
                <div className="flex h-14 items-center border-b px-4">
                    <h1 className="font-semibold">Moderation</h1>
                    <p className="ml-auto text-sm text-muted-foreground">
                        Child safety first, then oldest
                    </p>
                </div>
                <ModerationTabs active="profile-reports" />
            </div>
            <p className="border-b px-4 py-3 text-sm text-muted-foreground">
                Private reports about people, from their profile, a private chat
                or an org's member list. They carry no messages. Dismiss a
                report, or put the person in timeout or ban them.
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
                        <article
                            key={report.id}
                            className="grid gap-2 border-b p-4"
                            data-test="profile-report"
                        >
                            <Link
                                href={show(report.user)}
                                className="flex min-w-0 items-center gap-3"
                            >
                                <UserAvatar
                                    user={report.user}
                                    className="size-9"
                                />
                                <span className="min-w-0">
                                    <span
                                        className="block truncate font-semibold hover:underline"
                                        title={report.user.name}
                                    >
                                        {report.user.name}
                                    </span>
                                    <span className="block truncate font-mono text-xs text-muted-foreground">
                                        @{report.user.handle}
                                    </span>
                                </span>
                            </Link>
                            {report.moderation && (
                                <UserModerationStatus
                                    moderation={report.moderation}
                                />
                            )}
                            <p
                                className={cn(
                                    'text-sm font-medium',
                                    report.reason === 'child_safety' &&
                                        'text-destructive',
                                )}
                            >
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
                                    {formatFullDateTime(report.created_at)}
                                </time>
                            </p>
                            <div className="flex flex-wrap items-center gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    disabled={busy}
                                    onClick={() => dismiss(report)}
                                >
                                    <Check />
                                    Dismiss report
                                </Button>
                                {report.moderation && (
                                    <UserModerationMenu
                                        user={report.user}
                                        moderation={report.moderation}
                                    />
                                )}
                            </div>
                        </article>
                    ))}
                </InfiniteScroll>
            )}
        </>
    );
}

ProfileReports.layout = AppLayout;
