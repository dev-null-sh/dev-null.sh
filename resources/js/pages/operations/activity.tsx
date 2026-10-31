import { Head, Link, router } from '@inertiajs/react';
import UserController from '@/actions/App/Http/Controllers/UserController';
import { ActivityCells, activityTotal } from '@/components/activity-graph';
import type { ActivityNoun } from '@/components/activity-graph';
import { Breakdown } from '@/components/operations/breakdown';
import { PresetButton } from '@/components/operations/preset-button';
import { Panel } from '@/components/operations/section';
import { Stats } from '@/components/operations/stats';
import { UserPreview } from '@/components/user-preview';
import { useDateTime } from '@/hooks/use-date-time';
import { usePicked } from '@/hooks/use-picked';
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

type ActivityChoice = Pick<SiteActivity, 'metric' | 'scale'>;

/**
 * Sends the series and the window together, so a pick made while the one before is still loading keeps both.
 */
function ActivityPicker({ activity }: { activity: SiteActivity }) {
    const { picked, pick } = usePicked<ActivityChoice>();
    const shown = picked ?? { metric: activity.metric, scale: activity.scale };

    const select = (next: Partial<ActivityChoice>) => {
        const choice = { ...shown, ...next };

        router.reload({
            data: choice,
            only:
                next.scale === undefined
                    ? ['activity']
                    : ['activity', 'breakdowns'],
            onFinish: pick(choice),
        });
    };

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
                        isActive={metric === shown.metric}
                        onClick={() => select({ metric })}
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
                        isActive={preset.scale === shown.scale}
                        onClick={() => select({ scale: preset.scale })}
                    >
                        {preset.label}
                    </PresetButton>
                ))}
            </div>
        </div>
    );
}

function count(total: number, [singular, plural]: ActivityNoun): string {
    return `${formatCount(total)} ${total === 1 ? singular : plural}`;
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
    const { formatDate } = useDateTime();
    const windowLabel = ACTIVITY_SCALES.find(
        (preset) => preset.scale === activity.scale,
    )?.label;
    const posted = breakdowns.statuses.reduce(
        (sum, status) => sum + status.count,
        0,
    );

    return (
        <>
            <Head title="Activity" />
            <div className="grid gap-4 xl:grid-cols-2">
                <Panel title="Today" meta="since 00:00 UTC">
                    <Stats
                        stats={COUNTERS.map((stat) => ({
                            label: stat.label,
                            value: formatCount(today[stat.key].today),
                            note: `${formatCount(today[stat.key].yesterday)} yesterday`,
                        }))}
                    />
                </Panel>

                <Panel
                    title="Overall"
                    meta={
                        overall.since
                            ? `since ${formatDate(overall.since, { timeZone: 'UTC', year: true })}`
                            : 'all time'
                    }
                >
                    <Stats
                        stats={COUNTERS.map((stat) => ({
                            label: stat.label,
                            value: formatCount(overall[stat.key]),
                        }))}
                    />
                </Panel>

                <Panel
                    className="xl:col-span-2"
                    title="Activity"
                    meta={`${count(activityTotal(activity), NOUNS[activity.metric])}, ${windowLabel}`}
                >
                    <ActivityPicker activity={activity} />
                    <ActivityCells
                        activity={activity}
                        color="var(--muted-foreground)"
                        noun={NOUNS[activity.metric]}
                    />
                </Panel>

                <Panel
                    className="xl:col-span-2"
                    title="Breakdown"
                    meta={`${count(posted, ['commit or reply', 'commits and replies'])}, ${windowLabel}`}
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
                            renderName={(handle) => (
                                <UserPreview user={{ handle }}>
                                    <Link
                                        href={UserController.show(handle)}
                                        className="underline-offset-4 hover:underline"
                                    >
                                        @{handle}
                                    </Link>
                                </UserPreview>
                            )}
                        />
                    </div>
                </Panel>
            </div>
        </>
    );
}

Show.layout = OperationsLayout;
