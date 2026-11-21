import { Sparkline } from '@/components/operations/charts';
import { Delta } from '@/components/operations/overview-delta';
import { Panel } from '@/components/operations/section';
import { exactCountTitle, formatCount } from '@/lib/numbers';
import type { DailyPoint } from '@/types';

type Metric = keyof Omit<DailyPoint, 'date' | 'follows'>;

const METRICS: {
    key: Metric;
    label: string;
    isDaily?: boolean;
    isUpGood?: boolean;
}[] = [
    { key: 'active_users', label: 'active users', isDaily: true },
    { key: 'signups', label: 'signups' },
    { key: 'commits', label: 'commits' },
    { key: 'replies', label: 'replies' },
    { key: 'stars', label: 'stars' },
    { key: 'failed_jobs', label: 'failed jobs', isUpGood: false },
];

function sum(values: number[]): number {
    return values.reduce((total, value) => total + value, 0);
}

/**
 * The last complete week of each product metric beside the week before, with four weeks of days drawn under it.
 * Active users are counted per day, so theirs is yesterday against the average day of the week before. Today is
 * still running, so it only shows as a muted "so far".
 */
export function OverviewMetrics({ daily }: { daily: DailyPoint[] }) {
    const complete = daily.slice(0, -1);
    const today = daily.at(-1);

    return (
        <Panel title="Product" meta="complete UTC days, vs the period before">
            <ul className="grid grid-cols-2 gap-x-4 gap-y-5 md:grid-cols-3 xl:grid-cols-6">
                {METRICS.map((metric) => {
                    const values = complete.map((day) => day[metric.key]);
                    const current = metric.isDaily
                        ? (values.at(-1) ?? 0)
                        : sum(values.slice(-7));
                    const previous = metric.isDaily
                        ? sum(values.slice(-8, -1)) / 7
                        : sum(values.slice(-14, -7));

                    return (
                        <li key={metric.key} className="min-w-0">
                            <h3
                                className="truncate font-mono text-[13px] text-muted-foreground"
                                title={`${metric.label}, ${metric.isDaily ? 'yesterday' : 'last 7 complete days'}`}
                            >
                                {metric.label} · {metric.isDaily ? '1d' : '7d'}
                            </h3>
                            <p
                                className="font-mono text-xl leading-7 font-medium tabular-nums"
                                title={exactCountTitle(current)}
                            >
                                {formatCount(current)}
                            </p>
                            <p className="flex">
                                <Delta
                                    current={current}
                                    previous={previous}
                                    isUpGood={metric.isUpGood}
                                    label={
                                        metric.isDaily
                                            ? 'vs 7d avg'
                                            : 'vs prev 7d'
                                    }
                                />
                            </p>
                            <Sparkline
                                values={values}
                                label={`${metric.label} per day, last ${values.length} complete days`}
                                className="mt-2 text-muted-foreground"
                            />
                            <p className="mt-1 truncate font-mono text-xs text-muted-foreground tabular-nums">
                                today so far:{' '}
                                {formatCount(today?.[metric.key] ?? 0)}
                            </p>
                        </li>
                    );
                })}
            </ul>
        </Panel>
    );
}
