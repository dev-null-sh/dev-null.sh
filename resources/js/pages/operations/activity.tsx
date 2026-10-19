import { Head, Link, router } from '@inertiajs/react';
import UserController from '@/actions/App/Http/Controllers/UserController';
import {
    ActivityCells,
    activitySummary,
    activityWindows,
} from '@/components/activity-graph';
import type { ActivityNoun } from '@/components/activity-graph';
import { Breakdown } from '@/components/operations/breakdown';
import { PresetButton } from '@/components/operations/preset-button';
import { Section } from '@/components/operations/section';
import { Stats } from '@/components/operations/stats';
import { UserPreview } from '@/components/user-preview';
import AppLayout from '@/layouts/app-layout';
import OperationsLayout from '@/layouts/operations/layout';
import { formatCount } from '@/lib/numbers';
import type {
    ActivityBreakdowns,
    ActivityMetric,
    ActivityScale,
    Counter,
    Overall,
    SiteActivity,
    Today,
} from '@/types';

const ACTIVITY_SCALES: { label: string; scale: ActivityScale }[] = [
    { label: '24h', scale: 'day' },
    { label: '7d', scale: 'week' },
    { label: '30d', scale: 'month' },
    { label: '1y', scale: 'year' },
];

const ACTIVITY_METRICS: ActivityMetric[] = ['commits', 'replies', 'signups'];

const NOUNS: Record<ActivityMetric, ActivityNoun> = {
    commits: ['commit', 'commits'],
    replies: ['reply', 'replies'],
    signups: ['signup', 'signups'],
};

function ActivityPicker({ activity }: { activity: SiteActivity }) {
    return (
        <div className="mb-3 flex flex-wrap items-center gap-x-4 gap-y-2 font-mono text-[13px]">
            <div
                role="group"
                aria-label="Series"
                className="flex items-center gap-2"
            >
                {ACTIVITY_METRICS.map((metric) => (
                    <PresetButton
                        key={metric}
                        isActive={metric === activity.metric}
                        onClick={() =>
                            router.reload({
                                data: { metric },
                                only: ['activity'],
                            })
                        }
                    >
                        {metric}
                    </PresetButton>
                ))}
            </div>
            <div
                role="group"
                aria-label="Window"
                className="flex items-center gap-2 sm:ml-auto"
            >
                {ACTIVITY_SCALES.map((preset) => (
                    <PresetButton
                        key={preset.scale}
                        isActive={preset.scale === activity.scale}
                        onClick={() =>
                            router.reload({
                                data: { scale: preset.scale },
                                only: ['activity', 'breakdowns'],
                            })
                        }
                    >
                        {preset.label}
                    </PresetButton>
                ))}
            </div>
        </div>
    );
}

const COUNTERS: { key: Counter; label: string }[] = [
    { key: 'signups', label: 'signups' },
    { key: 'commits', label: 'commits' },
    { key: 'replies', label: 'replies' },
    { key: 'authors', label: 'active authors' },
];

export default function Show({
    today,
    overall,
    activity,
    breakdowns,
}: {
    today: Today;
    overall: Overall;
    activity: SiteActivity;
    breakdowns: ActivityBreakdowns;
}) {
    const posted = breakdowns.statuses.reduce(
        (sum, status) => sum + status.count,
        0,
    );

    return (
        <>
            <Head title="Site activity" />

            <Section title="Today" meta="since 00:00 UTC">
                <Stats
                    stats={COUNTERS.map((stat) => ({
                        label: stat.label,
                        value: formatCount(today[stat.key].today),
                        note: `${formatCount(today[stat.key].yesterday)} yesterday`,
                    }))}
                />
            </Section>

            <Section
                title="Overall"
                meta={overall.since ? `since ${overall.since}` : 'all time'}
            >
                <Stats
                    stats={COUNTERS.map((stat) => ({
                        label: stat.label,
                        value: formatCount(overall[stat.key]),
                    }))}
                />
            </Section>

            <Section
                title="Activity"
                meta={activitySummary(activity, NOUNS[activity.metric])}
            >
                <ActivityPicker activity={activity} />
                <ActivityCells
                    activity={activity}
                    color="var(--muted-foreground)"
                    noun={NOUNS[activity.metric]}
                />
            </Section>

            <Section
                title="Breakdown"
                meta={`${formatCount(posted)} ${posted === 1 ? 'commit or reply' : 'commits and replies'} ${activityWindows[activity.scale]}`}
            >
                <div className="grid gap-3 sm:grid-cols-2">
                    <Breakdown
                        title="status"
                        unit="commits"
                        empty="unknown"
                        rows={breakdowns.statuses}
                    />
                    <Breakdown
                        title="authors"
                        unit="commits"
                        empty="unknown"
                        rows={breakdowns.authors}
                        formatName={(name) => `@${name}`}
                        renderName={(username) => (
                            <UserPreview user={{ username }}>
                                <Link
                                    href={UserController.show(username)}
                                    className="underline-offset-4 hover:underline"
                                >
                                    @{username}
                                </Link>
                            </UserPreview>
                        )}
                    />
                </div>
            </Section>
        </>
    );
}

Show.layout = [AppLayout, OperationsLayout];
