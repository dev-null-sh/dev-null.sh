import { Link } from '@inertiajs/react';
import { ArrowDownRight, ArrowRight, ArrowUpRight } from 'lucide-react';
import ForecastController from '@/actions/App/Http/Controllers/ForecastController';
import { LineChart } from '@/components/operations/charts';
import { Panel } from '@/components/operations/section';
import { formatCount } from '@/lib/numbers';
import type { ForecastMetric, ForecastSeries } from '@/types';

const SHOWN: { metric: ForecastMetric; title: string; unit: string }[] = [
    { metric: 'active_users', title: 'Active users', unit: 'active users' },
    { metric: 'signups', title: 'Signups', unit: 'signups' },
];

function sum(values: number[]): number {
    return values.reduce((total, value) => total + value, 0);
}

function TrendIcon({ trend }: { trend: number }) {
    const Icon =
        trend > 0 ? ArrowUpRight : trend < 0 ? ArrowDownRight : ArrowRight;

    return <Icon aria-hidden className="size-3.5" />;
}

/**
 * What the next seven days should bring: the expected total (signups) or average day (active users) with its 80%
 * range, the fitted daily trend, and the last four weeks continued two weeks ahead.
 */
function ForecastPanel({
    series,
    title,
    unit,
}: {
    series: ForecastSeries;
    title: string;
    unit: string;
}) {
    const week = series.forecast.slice(0, 7);
    const isAverage = series.metric === 'active_users';
    const reduce = (values: number[]) =>
        Math.round(
            isAverage ? sum(values) / Math.max(1, values.length) : sum(values),
        );
    const expected = reduce(week.map((day) => day.value));
    const lower = reduce(week.map((day) => day.lower));
    const upper = reduce(week.map((day) => day.upper));

    return (
        <Panel
            title={title}
            meta={`forecast · ${series.method}`}
            actions={
                <Link
                    href={ForecastController.show()}
                    className="-mr-2 inline-flex h-7 items-center rounded-md px-2 text-sm text-primary underline-offset-4 hover:underline"
                >
                    Forecasts
                </Link>
            }
        >
            <div className="mb-3 flex flex-wrap items-end gap-x-6 gap-y-1">
                <div className="min-w-0">
                    <p className="text-sm text-muted-foreground">
                        {isAverage ? 'Average day, next 7 days' : 'Next 7 days'}
                    </p>
                    <p className="font-mono text-xl leading-7 font-medium tabular-nums">
                        {formatCount(expected)}
                        <span className="ml-2 text-[13px] font-normal text-muted-foreground">
                            {formatCount(lower)}–{formatCount(upper)}
                        </span>
                    </p>
                </div>
                <p className="flex items-center gap-1 pb-0.5 font-mono text-[13px] text-muted-foreground tabular-nums">
                    <TrendIcon trend={series.trend} />
                    trend{' '}
                    {series.trend > 0 ? '+' : series.trend < 0 ? '−' : '±'}
                    {Math.abs(series.trend)}/day
                </p>
            </div>
            <LineChart
                history={series.history.slice(-28)}
                forecast={series.forecast.slice(0, 14)}
                height={150}
                label={`${title} per day, the last 28 days and the next 14 expected`}
                unit={unit}
            />
        </Panel>
    );
}

export function OverviewForecasts({
    forecasts,
}: {
    forecasts: ForecastSeries[];
}) {
    return (
        <div className="grid gap-4 lg:grid-cols-2">
            {SHOWN.map(({ metric, title, unit }) => {
                const series = forecasts.find((item) => item.metric === metric);

                return (
                    series && (
                        <ForecastPanel
                            key={metric}
                            series={series}
                            title={title}
                            unit={unit}
                        />
                    )
                );
            })}
        </div>
    );
}
