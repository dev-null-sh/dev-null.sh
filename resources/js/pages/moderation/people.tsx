import { Head, Link, router } from '@inertiajs/react';
import { Undo2 } from 'lucide-react';
import type { ReactNode } from 'react';
import BanController from '@/actions/App/Http/Controllers/BanController';
import { show } from '@/actions/App/Http/Controllers/UserController';
import UserTimeoutController from '@/actions/App/Http/Controllers/UserTimeoutController';
import { EmptyFeed } from '@/components/commit-feed';
import { ModerationTabs } from '@/components/moderation-tabs';
import { Button } from '@/components/ui/button';
import { UserAvatar } from '@/components/user-avatar';
import AppLayout from '@/layouts/app-layout';
import { formatFullDateTime } from '@/lib/utils';
import type { User } from '@/types';

type Timeout = {
    id: string;
    user: User;
    reason: string;
    ends_at: string;
    timeouts_count: number;
};

type Ban = {
    id: string;
    github_id: string;
    user: User | null;
    reason: string;
    created_at: string;
};

export default function People({
    timeouts,
    bans,
}: {
    timeouts: Timeout[];
    bans: Ban[];
}) {
    const endTimeout = (timeout: Timeout) =>
        router
            .optimistic((props: { timeouts: Timeout[] }) => ({
                timeouts: props.timeouts.filter(({ id }) => id !== timeout.id),
            }))
            .visit(UserTimeoutController.destroy(timeout.user), {
                preserveScroll: true,
            });
    const liftBan = (ban: Ban) =>
        router
            .optimistic((props: { bans: Ban[] }) => ({
                bans: props.bans.filter(({ id }) => id !== ban.id),
            }))
            .visit(BanController.destroy(ban.id), { preserveScroll: true });

    return (
        <>
            <Head title="Timeouts and bans" />
            <div className="sticky top-0 z-10 bg-card">
                <div className="flex h-14 items-center border-b px-4">
                    <h1 className="font-semibold">Moderation</h1>
                </div>
                <ModerationTabs active="people" />
            </div>

            <Section
                title="In timeout"
                count={timeouts.length}
                empty="Nobody is in timeout."
            >
                {timeouts.map((timeout) => (
                    <Row
                        key={timeout.id}
                        user={timeout.user}
                        details={
                            <>
                                {timeout.reason} · until{' '}
                                <time dateTime={timeout.ends_at}>
                                    {formatFullDateTime(timeout.ends_at)}
                                </time>{' '}
                                · {timeout.timeouts_count}{' '}
                                {timeout.timeouts_count === 1
                                    ? 'timeout'
                                    : 'timeouts'}
                            </>
                        }
                        action={
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => endTimeout(timeout)}
                                data-test="end-timeout-button"
                            >
                                <Undo2 />
                                End timeout
                            </Button>
                        }
                    />
                ))}
            </Section>

            <Section
                title="Banned"
                count={bans.length}
                empty="Nobody is banned."
            >
                {bans.map((ban) => (
                    <Row
                        key={ban.id}
                        user={ban.user}
                        githubId={ban.github_id}
                        details={
                            <>
                                {ban.reason} · since{' '}
                                <time dateTime={ban.created_at}>
                                    {formatFullDateTime(ban.created_at)}
                                </time>
                            </>
                        }
                        action={
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => liftBan(ban)}
                                data-test="unban-button"
                            >
                                <Undo2 />
                                {ban.user ? 'Unban' : 'Lift ban'}
                            </Button>
                        }
                    />
                ))}
            </Section>
        </>
    );
}

People.layout = AppLayout;

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
                <span className="font-mono text-xs text-muted-foreground">
                    {count}
                </span>
            </h2>
            {count === 0 ? (
                <div className="px-6 py-10 text-center">
                    <EmptyFeed command="nothing to lift">{empty}</EmptyFeed>
                </div>
            ) : (
                <ul className="divide-y">{children}</ul>
            )}
        </section>
    );
}

function Row({
    user,
    githubId,
    details,
    action,
}: {
    user: User | null;
    githubId?: string;
    details: ReactNode;
    action: ReactNode;
}) {
    return (
        <li className="flex items-center gap-3 px-4 py-3">
            {user ? (
                <Link
                    prefetch
                    href={show(user)}
                    className="flex min-w-0 flex-1 items-center gap-3"
                >
                    <UserAvatar user={user} className="size-9 shrink-0" />
                    <div className="min-w-0">
                        <p className="truncate text-sm font-medium">
                            {user.name}{' '}
                            <span className="font-mono text-[13px] font-normal text-muted-foreground">
                                @{user.handle}
                            </span>
                        </p>
                        <p className="text-xs text-muted-foreground">
                            {details}
                        </p>
                    </div>
                </Link>
            ) : (
                <div className="min-w-0 flex-1">
                    <p className="truncate text-sm font-medium text-muted-foreground">
                        Deleted account
                        {githubId && (
                            <>
                                {' '}
                                <a
                                    href={`https://api.github.com/user/${githubId}`}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="font-mono text-[13px] font-normal underline underline-offset-4"
                                >
                                    GitHub #{githubId}
                                </a>
                            </>
                        )}
                    </p>
                    <p className="text-xs text-muted-foreground">{details}</p>
                </div>
            )}
            {action}
        </li>
    );
}
