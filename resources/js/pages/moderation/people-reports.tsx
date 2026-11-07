import { Head, Link, router } from '@inertiajs/react';
import { Check } from 'lucide-react';
import type { ReactNode } from 'react';
import { destroy as dismissMessageReport } from '@/actions/App/Http/Controllers/MessageReportController';
import { show } from '@/actions/App/Http/Controllers/UserController';
import { destroy as dismissUserReport } from '@/actions/App/Http/Controllers/UserReportController';
import { EmptyFeed } from '@/components/commit-feed';
import { ModerationTabs } from '@/components/moderation-tabs';
import { Button } from '@/components/ui/button';
import { UserAvatar } from '@/components/user-avatar';
import AppLayout from '@/layouts/app-layout';
import { formatFullDateTime } from '@/lib/utils';
import type { User } from '@/types';

type Report = {
    id: string;
    reason: string;
    details: string | null;
    created_at: string;
};

type MessageReport = Report & {
    message: { id: number; body: string; created_at: string; user: User };
};

type UserReport = Report & { user: User };

export default function PeopleReports({
    messageReports,
    userReports,
    reasons,
}: {
    messageReports: MessageReport[];
    userReports: UserReport[];
    reasons: Record<string, string>;
}) {
    const dismissMessage = (report: MessageReport) =>
        router
            .optimistic((props: { messageReports: MessageReport[] }) => ({
                messageReports: props.messageReports.filter(
                    ({ id }) => id !== report.id,
                ),
            }))
            .visit(dismissMessageReport(report.id), { preserveScroll: true });
    const dismissUser = (report: UserReport) =>
        router
            .optimistic((props: { userReports: UserReport[] }) => ({
                userReports: props.userReports.filter(
                    ({ id }) => id !== report.id,
                ),
            }))
            .visit(dismissUserReport(report.id), { preserveScroll: true });

    return (
        <>
            <Head title="Reported messages and people" />
            <div className="sticky top-0 z-10 bg-card">
                <div className="flex h-14 items-center border-b px-4">
                    <h1 className="font-semibold">Moderation</h1>
                    <p className="ml-auto text-sm text-muted-foreground">
                        Oldest first
                    </p>
                </div>
                <ModerationTabs active="people-reports" />
            </div>
            <p className="border-b px-4 py-3 text-sm text-muted-foreground">
                Private reports on remotes messages and people. Time out or ban
                from the profile, then dismiss the report.
            </p>

            <Section
                title="Messages"
                count={messageReports.length}
                empty="No reported messages."
            >
                {messageReports.map((report) => (
                    <Row
                        key={report.id}
                        user={report.message.user}
                        report={report}
                        reasons={reasons}
                        onDismiss={() => dismissMessage(report)}
                    >
                        <p className="rounded-md border bg-muted/40 px-3 py-2 text-sm leading-relaxed wrap-anywhere whitespace-pre-wrap">
                            {report.message.body}
                        </p>
                    </Row>
                ))}
            </Section>

            <Section
                title="People"
                count={userReports.length}
                empty="No reported people."
            >
                {userReports.map((report) => (
                    <Row
                        key={report.id}
                        user={report.user}
                        report={report}
                        reasons={reasons}
                        onDismiss={() => dismissUser(report)}
                    />
                ))}
            </Section>
        </>
    );
}

PeopleReports.layout = AppLayout;

function Section({
    title,
    count,
    empty,
    children,
}: {
    title: string;
    count: number;
    empty: string;
    children: ReactNode;
}) {
    return (
        <section className="border-b">
            <h2 className="flex items-baseline gap-2 border-b px-4 py-3 text-sm font-medium">
                {title}
                <span className="font-mono text-xs text-muted-foreground tabular-nums">
                    {count}
                </span>
            </h2>
            {count === 0 ? (
                <div className="px-6 py-10 text-center">
                    <EmptyFeed command="nothing to review">{empty}</EmptyFeed>
                </div>
            ) : (
                <ul className="divide-y">{children}</ul>
            )}
        </section>
    );
}

function Row({
    user,
    report,
    reasons,
    onDismiss,
    children,
}: {
    user: User;
    report: Report;
    reasons: Record<string, string>;
    onDismiss: () => void;
    children?: ReactNode;
}) {
    return (
        <li className="grid gap-2 px-4 py-3">
            <Link
                prefetch
                href={show(user)}
                className="flex min-w-0 items-center gap-3"
            >
                <UserAvatar user={user} className="size-9 shrink-0" />
                <p className="min-w-0 truncate text-sm font-medium">
                    {user.name}{' '}
                    <span className="font-mono text-[13px] font-normal text-muted-foreground">
                        @{user.handle}
                    </span>
                </p>
            </Link>
            {children}
            <p className="text-sm font-medium">{reasons[report.reason]}</p>
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
            <div>
                <Button variant="outline" size="sm" onClick={onDismiss}>
                    <Check />
                    Dismiss report
                </Button>
            </div>
        </li>
    );
}
