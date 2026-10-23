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
import AppLayout from '@/layouts/app-layout';
import { formatCount } from '@/lib/numbers';
import type { ActivityScale, User } from '@/types';

type BulkAccount = {
    user: User;
    count: number;
    reach: number | null;
    newcomers: number | null;
};

type BulkActivity = Record<
    'follows' | 'stars' | 'replies' | 'posts',
    BulkAccount[]
>;

const SCALES: { label: string; scale: ActivityScale }[] = [
    { label: '24h', scale: 'day' },
    { label: '7d', scale: 'week' },
    { label: '30d', scale: 'month' },
];

/**
 * What each list counts, in the words of a row: the count, then whom it reached and how many of them are newcomers.
 */
const CATEGORIES: {
    key: keyof BulkActivity;
    title: string;
    details: (account: BulkAccount) => string;
}[] = [
    {
        key: 'follows',
        title: 'Follows',
        details: (account) =>
            `${formatCount(account.count)} followed · ${formatCount(account.newcomers ?? 0)} newcomers`,
    },
    {
        key: 'stars',
        title: 'Stars',
        details: (account) =>
            `${formatCount(account.count)} stars to ${formatCount(account.reach ?? 0)} people · ${formatCount(account.newcomers ?? 0)} newcomers`,
    },
    {
        key: 'replies',
        title: 'Replies',
        details: (account) =>
            `${formatCount(account.count)} replies to ${formatCount(account.reach ?? 0)} people · ${formatCount(account.newcomers ?? 0)} newcomers`,
    },
    {
        key: 'posts',
        title: 'Posts',
        details: (account) => `${formatCount(account.count)} posts`,
    },
];

export default function Bulk({
    scale,
    activity,
    moderation,
}: {
    scale: ActivityScale;
    activity: BulkActivity;
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
                    The accounts that followed, starred, replied and posted the
                    most {activityWindows[scale]}. Newcomers signed up in that
                    time too. GitHub imports and bots are left out.
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
                    count={activity[category.key].length}
                >
                    {activity[category.key].map((account) => (
                        <Row
                            key={account.user.id}
                            user={account.user}
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
    count,
    children,
}: {
    title: string;
    count: number;
    children: ReactNode;
}) {
    return (
        <section className="border-b">
            <h2 className="border-b px-4 py-3 text-sm font-medium">{title}</h2>
            {count === 0 ? (
                <div className="px-6 py-10 text-center">
                    <EmptyFeed command="nothing in bulk">
                        Nobody did any of this in these days.
                    </EmptyFeed>
                </div>
            ) : (
                <ol className="divide-y">{children}</ol>
            )}
        </section>
    );
}

function Row({
    user,
    details,
    action,
}: {
    user: User;
    details: string;
    action: ReactNode;
}) {
    return (
        <li className="flex items-center gap-3 px-4 py-3">
            <Link
                href={show(user)}
                className="flex min-w-0 flex-1 items-center gap-3"
            >
                <UserAvatar user={user} className="size-9 shrink-0" />
                <div className="min-w-0">
                    <p className="truncate text-sm font-medium">
                        {user.name}{' '}
                        <span className="font-mono text-[13px] font-normal text-muted-foreground">
                            @{user.username}
                        </span>
                    </p>
                    <p className="font-mono text-xs text-muted-foreground tabular-nums">
                        {details}
                    </p>
                </div>
            </Link>
            {action}
        </li>
    );
}
