import { Head, Link, router } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { show } from '@/actions/App/Http/Controllers/UserController';
import { activityWindows } from '@/components/activity-graph';
import { EmptyFeed } from '@/components/commit-feed';
import { ModerationTabs } from '@/components/moderation-tabs';
import { PresetButton } from '@/components/operations/preset-button';
import { UserAvatar } from '@/components/user-avatar';
import { UserModerationMenu } from '@/components/user-moderation-menu';
import type { UserModeration } from '@/components/user-moderation-menu';
import { useDateTime } from '@/hooks/use-date-time';
import AppLayout from '@/layouts/app-layout';
import { formatCount } from '@/lib/numbers';
import type { ActivityScale, User } from '@/types';

type BulkAccount = {
    user: User;
    count: number;
    reach: number | null;
    newcomers: number | null;
};

type BulkList = 'follows' | 'stars' | 'replies' | 'posts';

type BulkActivity = Record<BulkList, BulkAccount[]>;

type BulkThresholds = Record<BulkList, { perDay: number; total: number }>;

const SCALES: { label: string; scale: ActivityScale }[] = [
    { label: '24h', scale: 'day' },
    { label: '7d', scale: 'week' },
    { label: '30d', scale: 'month' },
];

/**
 * What each list counts, in the words of a row: whom it reached, how many of them are newcomers, and the threshold an
 * account has to reach to be listed, a day and over the window.
 */
const CATEGORIES: {
    key: BulkList;
    title: string;
    rule: (perDay: string) => string;
    empty: (total: string, window: string) => string;
    details: (account: BulkAccount) => string;
}[] = [
    {
        key: 'follows',
        title: 'Follows',
        rule: (perDay) => `${perDay}+ people followed a day`,
        empty: (total, window) => `Nobody followed ${total}+ people ${window}.`,
        details: (account) =>
            `followed ${formatCount(account.count)} people · ${formatCount(account.newcomers ?? 0)} newcomers`,
    },
    {
        key: 'stars',
        title: 'Stars',
        rule: (perDay) => `${perDay}+ people starred a day`,
        empty: (total, window) => `Nobody starred ${total}+ people ${window}.`,
        details: (account) =>
            `starred ${formatCount(account.reach ?? 0)} people · ${formatCount(account.count)} stars · ${formatCount(account.newcomers ?? 0)} newcomers`,
    },
    {
        key: 'replies',
        title: 'Replies',
        rule: (perDay) => `${perDay}+ people replied to a day`,
        empty: (total, window) =>
            `Nobody replied to ${total}+ people ${window}.`,
        details: (account) =>
            `replied to ${formatCount(account.reach ?? 0)} people · ${formatCount(account.count)} replies · ${formatCount(account.newcomers ?? 0)} newcomers`,
    },
    {
        key: 'posts',
        title: 'Posts',
        rule: (perDay) => `${perDay}+ posts a day`,
        empty: (total, window) => `Nobody posted ${total}+ times ${window}.`,
        details: (account) => `${formatCount(account.count)} posts`,
    },
];

export default function Bulk({
    scale,
    activity,
    thresholds,
    moderation,
}: {
    scale: ActivityScale;
    activity: BulkActivity;
    thresholds: BulkThresholds;
    moderation: Record<string, UserModeration>;
}) {
    return (
        <>
            <Head title="Bulk activity" />
            <div className="sticky top-0 z-10 bg-card">
                <div className="flex h-14 items-center border-b px-4">
                    <h1 className="font-semibold">Moderation</h1>
                </div>
                <ModerationTabs active="bulk" />
            </div>

            <div className="flex flex-wrap items-center gap-x-4 gap-y-2 border-b px-4 py-3">
                <p className="flex-1 text-xs text-muted-foreground">
                    The accounts that followed, starred or replied to more
                    people, or posted more, {activityWindows[scale]} than normal
                    use does. Newcomers signed up in that time too. GitHub
                    imports and bots are left out.
                </p>
                <div
                    role="group"
                    aria-label="Window"
                    className="flex items-center gap-2"
                >
                    {SCALES.map((preset) => (
                        <PresetButton
                            key={preset.scale}
                            isActive={preset.scale === scale}
                            onClick={() =>
                                router.reload({ data: { scale: preset.scale } })
                            }
                        >
                            {preset.label}
                        </PresetButton>
                    ))}
                </div>
            </div>

            {CATEGORIES.map((category) => (
                <Section
                    key={category.key}
                    title={category.title}
                    rule={category.rule(
                        formatCount(thresholds[category.key].perDay),
                    )}
                    empty={category.empty(
                        formatCount(thresholds[category.key].total),
                        activityWindows[scale],
                    )}
                    count={activity[category.key].length}
                >
                    {activity[category.key].map((account) => (
                        <Row
                            key={account.user.id}
                            user={account.user}
                            moderation={moderation[account.user.id]}
                            details={category.details(account)}
                            action={
                                moderation[account.user.id] && (
                                    <UserModerationMenu
                                        user={account.user}
                                        moderation={moderation[account.user.id]}
                                    />
                                )
                            }
                        />
                    ))}
                </Section>
            ))}
        </>
    );
}

Bulk.layout = AppLayout;

function Section({
    title,
    rule,
    empty,
    count,
    children,
}: {
    title: string;
    rule: string;
    empty: string;
    count: number;
    children: ReactNode;
}) {
    return (
        <section className="border-b">
            <h2 className="flex items-baseline justify-between gap-2 border-b px-4 py-3 text-sm font-medium">
                {title}
                <span className="font-mono text-xs font-normal text-muted-foreground">
                    {rule}
                </span>
            </h2>
            {count === 0 ? (
                <div className="px-6 py-10 text-center">
                    <EmptyFeed command="nothing in bulk">{empty}</EmptyFeed>
                </div>
            ) : (
                <ol className="divide-y">{children}</ol>
            )}
        </section>
    );
}

function Row({
    user,
    moderation,
    details,
    action,
}: {
    user: User;
    moderation?: UserModeration;
    details: string;
    action: ReactNode;
}) {
    const { formatFullDateTime } = useDateTime();

    return (
        <li className="flex items-center gap-3 px-4 py-3">
            <Link
                href={show(user)}
                className="flex min-w-0 flex-1 items-center gap-3"
            >
                <UserAvatar user={user} className="size-9 shrink-0" />
                <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-x-2 gap-y-0.5">
                        <p className="max-w-full truncate text-sm font-medium">
                            {user.name}{' '}
                            <span className="font-mono text-[13px] font-normal text-muted-foreground">
                                @{user.handle}
                            </span>
                        </p>
                        {moderation?.ban ? (
                            <span className="rounded bg-destructive/10 px-1.5 py-0.5 font-mono text-[11px] whitespace-nowrap text-destructive">
                                banned
                            </span>
                        ) : (
                            moderation?.timeout && (
                                <span className="rounded bg-amber-500/10 px-1.5 py-0.5 font-mono text-[11px] whitespace-nowrap text-amber-700 dark:text-amber-400">
                                    timeout until{' '}
                                    {formatFullDateTime(
                                        moderation.timeout.endsAt,
                                    )}
                                </span>
                            )
                        )}
                    </div>
                    <p className="font-mono text-xs text-muted-foreground tabular-nums">
                        {details}
                    </p>
                </div>
            </Link>
            {action}
        </li>
    );
}
