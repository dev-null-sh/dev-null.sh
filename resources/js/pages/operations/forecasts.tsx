import { Head } from '@inertiajs/react';
import { Minus, TrendingDown, TrendingUp } from 'lucide-react';
import { useState } from 'react';
import { EmptyFeed } from '@/components/commit-feed';
import { LineChart } from '@/components/operations/charts';
import { PresetButton } from '@/components/operations/preset-button';
import { utcDate } from '@/components/operations/range-picker';
import { Panel } from '@/components/operations/section';
import { useDateTime } from '@/hooks/use-date-time';
import { useNow } from '@/hooks/use-now';
import OperationsLayout from '@/layouts/operations/layout';
import { formatCount, formatExactCount } from '@/lib/numbers';
import { cn, dateLocale } from '@/lib/utils';
import type {
    ForecastMetric,
    ForecastSeries,
    Milestone,
    MonthProjection,
} from '@/types';

const METRICS: { metric: ForecastMetric; title: string; unit: string }[] = [
    { metric: 'active_users', title: 'Active users', unit: 'active users' },
    { metric: 'signups', title: 'Signups', unit: 'signups' },
    { metric: 'commits', title: 'Commits', unit: 'commits' },
    { metric: 'replies', title: 'Replies', unit: 'replies' },
];

const METHODS: Record<ForecastSeries['method'], string> = {
    seasonal: 'seasonal · weekly pattern',
    linear: 'linear trend',
    flat: 'flat · not enough history',
};

const HISTORY_WINDOWS = [28, 56];

const DAY = 86_400_000;

function sum(values: number[]): number {
    return values.reduce((total, value) => total + value, 0);
}

function formatValue(value: number): string {
    return value > 0 && value < 10 && !Number.isInteger(value)
        ? value.toFixed(1)
        : formatCount(Math.round(value));
}

function signed(value: number, digits = 0): string {
    const rounded = Number(Math.abs(value).toFixed(digits));

    return `${value < 0 && rounded > 0 ? '−' : '+'}${rounded.toFixed(digits)}`;
}

function trendLabel(trend: number): string {
    return Math.abs(trend) < 0.05 ? 'flat' : `${signed(trend, 1)}/day`;
}

function daysFromToday(date: string): string {
    const days = Math.round(
        (Date.parse(`${date.slice(0, 10)}T00:00:00Z`) -
            Date.parse(`${utcDate()}T00:00:00Z`)) /
            DAY,
    );

    if (days <= 0) {
        return 'today';
    }

    return days === 1 ? 'tomorrow' : `in ${formatCount(days)} days`;
}

function daysLeftIn(month: string): string {
    const today = utcDate();
    const end = new Date(
        Date.UTC(Number(month.slice(0, 4)), Number(month.slice(5, 7)), 0),
    ).getUTCDate();
    const days =
        month.slice(0, 7) === today.slice(0, 7)
            ? end - Number(today.slice(8, 10)) + 1
            : 0;

    if (days <= 0) {
        return 'month over';
    }

    return days === 1 ? 'last day' : `${days} days to go`;
}

function monthName(month: string): string {
    const value = new Date(`${month.slice(0, 7)}-01T00:00:00Z`);

    return Number.isNaN(value.getTime())
        ? month
        : value.toLocaleDateString(dateLocale, {
              month: 'long',
              timeZone: 'UTC',
          });
}

/**
 * Today so far against what the forecast expects by this time of the UTC day, assuming the day fills evenly: ahead or
 * behind only once it leaves the expected range for this point in the day.
 */
function TodayPace({ series, now }: { series: ForecastSeries; now: Date }) {
    const expected = series.forecast[0];
    const elapsed =
        (now.getTime() -
            Date.UTC(
                now.getUTCFullYear(),
                now.getUTCMonth(),
                now.getUTCDate(),
            )) /
        DAY;

    if (expected === undefined) {
        return null;
    }

    if (elapsed < 1 / 24) {
        return (
            <p className="font-mono text-[13px] text-muted-foreground tabular-nums">
                {formatCount(series.today)} today · the day just started
            </p>
        );
    }

    const byNow = expected.value * elapsed;
    const pace =
        series.today > expected.upper * elapsed
            ? 'ahead'
            : series.today < expected.lower * elapsed
              ? 'behind'
              : 'on pace';
    const Icon =
        pace === 'ahead'
            ? TrendingUp
            : pace === 'behind'
              ? TrendingDown
              : Minus;

    return (
        <p
            className="flex min-w-0 items-center gap-1.5 font-mono text-[13px] tabular-nums"
            title={`${formatValue(byNow)} expected by now, out of ${formatValue(expected.value)} for the whole day`}
        >
            <Icon
                aria-hidden
                className={cn(
                    'size-3.5 shrink-0',
                    pace === 'ahead' && 'text-success',
                    pace === 'behind' && 'text-destructive',
                    pace === 'on pace' && 'text-muted-foreground',
                )}
            />
            <span className="truncate">
                {formatCount(series.today)} today{' '}
                <span className="text-muted-foreground">
                    ·{' '}
                    {pace === 'on pace'
                        ? 'on pace'
                        : `${signed(series.today - byNow)} ${pace}`}
                </span>
            </span>
        </p>
    );
}

function Summary({
    series,
    title,
    now,
}: {
    series: ForecastSeries;
    title: string;
    now: Date;
}) {
    const week = series.forecast.slice(0, 7);
    const isAverage = series.metric === 'active_users';
    const reduce = (values: number[]) =>
        isAverage ? sum(values) / Math.max(1, values.length) : sum(values);

    return (
        <div className="flex min-w-0 flex-col gap-1 bg-card px-4 py-3">
            <div className="flex items-baseline justify-between gap-3">
                <dt className="truncate text-sm font-medium">{title}</dt>
                <dd
                    className="shrink-0 font-mono text-[13px] text-muted-foreground tabular-nums"
                    title="The trend over the last four weeks"
                >
                    {trendLabel(series.trend)}
                </dd>
            </div>
            <dd className="flex flex-wrap items-baseline gap-x-2">
                <span className="font-mono text-xl leading-7 font-medium tabular-nums">
                    {week.length === 0
                        ? '–'
                        : formatValue(reduce(week.map((day) => day.value)))}
                </span>
                {week.length > 0 && (
                    <span className="font-mono text-[13px] text-muted-foreground tabular-nums">
                        {formatValue(reduce(week.map((day) => day.lower)))}–
                        {formatValue(reduce(week.map((day) => day.upper)))}
                    </span>
                )}
            </dd>
            <dd className="text-xs text-muted-foreground">
                {isAverage
                    ? 'a day on average, next 7 days'
                    : 'expected in the next 7 days'}
            </dd>
            <dd className="mt-1 min-w-0">
                <TodayPace series={series} now={now} />
            </dd>
        </div>
    );
}

function ChartKey({
    historyDays,
    forecastDays,
}: {
    historyDays: number;
    forecastDays: number;
}) {
    return (
        <ul className="flex flex-wrap gap-x-4 gap-y-1 font-mono text-xs text-muted-foreground tabular-nums">
            <li className="flex items-center gap-1.5">
                <svg
                    aria-hidden
                    width={16}
                    height={8}
                    className="text-foreground"
                >
                    <line
                        x1={1}
                        x2={15}
                        y1={4}
                        y2={4}
                        stroke="currentColor"
                        strokeWidth={2}
                        strokeLinecap="round"
                    />
                </svg>
                last {historyDays} days
            </li>
            <li className="flex items-center gap-1.5">
                <svg aria-hidden width={16} height={8}>
                    <line
                        x1={1}
                        x2={15}
                        y1={4}
                        y2={4}
                        stroke="currentColor"
                        strokeWidth={2}
                        strokeDasharray="4 4"
                        strokeLinecap="round"
                    />
                </svg>
                next {forecastDays} days, expected
            </li>
            <li className="flex items-center gap-1.5">
                <span
                    aria-hidden
                    className="size-2.5 rounded-xs bg-muted-foreground/15"
                />
                80% range
            </li>
        </ul>
    );
}

function Milestones({ milestones }: { milestones: Milestone[] }) {
    const { formatDate } = useDateTime();
    return (
        <Panel title="Milestones" meta="on the current trend">
            {milestones.length === 0 ? (
                <p className="py-4 text-center font-mono text-[13px] text-muted-foreground">
                    $ git tag --list # none yet
                </p>
            ) : (
                <ul className="-m-4 divide-y">
                    {milestones.map((milestone) => (
                        <li
                            key={`${milestone.metric}-${milestone.target}`}
                            className="flex flex-col gap-2 px-4 py-3"
                        >
                            <div className="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-0.5">
                                <p className="font-mono text-[13px] font-medium tabular-nums">
                                    {formatExactCount(milestone.target)}{' '}
                                    {milestone.metric}
                                </p>
                                {milestone.eta === null ? (
                                    <p className="text-sm text-muted-foreground">
                                        Not on the current trend
                                    </p>
                                ) : (
                                    <p className="font-mono text-[13px] tabular-nums">
                                        ≈{' '}
                                        {formatDate(
                                            `${milestone.eta.slice(0, 10)}T00:00:00Z`,
                                            {
                                                timeZone: 'UTC',
                                                year:
                                                    Number(
                                                        milestone.eta.slice(
                                                            0,
                                                            4,
                                                        ),
                                                    ) !==
                                                    new Date().getUTCFullYear(),
                                            },
                                        )}
                                        <span className="text-muted-foreground">
                                            {' '}
                                            · {daysFromToday(milestone.eta)}
                                        </span>
                                    </p>
                                )}
                            </div>
                            <div className="flex items-center gap-3">
                                <div
                                    aria-hidden
                                    className="h-1 flex-1 overflow-hidden rounded-full bg-muted"
                                >
                                    <div
                                        className="h-full rounded-full bg-foreground"
                                        style={{
                                            width: `${Math.min(100, (milestone.current / Math.max(1, milestone.target)) * 100)}%`,
                                        }}
                                    />
                                </div>
                                <p className="shrink-0 font-mono text-[13px] text-muted-foreground tabular-nums">
                                    {formatExactCount(milestone.current)} now
                                </p>
                            </div>
                        </li>
                    ))}
                </ul>
            )}
        </Panel>
    );
}

function MonthBar({ projection }: { projection: MonthProjection }) {
    const scale = Math.max(1, projection.upper, projection.to_date);
    const at = (value: number) => `${(Math.min(value, scale) / scale) * 100}%`;
    const title = projection.metric === 'signups' ? 'Signups' : 'Commits';

    return (
        <li className="flex flex-col gap-2 px-4 py-3">
            <div className="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-0.5">
                <p className="text-sm font-medium">
                    {title}{' '}
                    <span className="font-normal text-muted-foreground">
                        in {monthName(projection.month)}
                    </span>
                </p>
                <p className="font-mono text-[13px] tabular-nums">
                    ≈ {formatCount(Math.round(projection.projected))}
                    <span className="text-muted-foreground">
                        {' '}
                        ({formatCount(Math.round(projection.lower))}–
                        {formatCount(Math.round(projection.upper))})
                    </span>
                </p>
            </div>
            <div
                aria-hidden
                className="relative h-2 overflow-hidden rounded-full bg-muted"
            >
                <div
                    className="absolute inset-y-0 bg-muted-foreground/25"
                    style={{
                        left: at(projection.lower),
                        width: `calc(${at(projection.upper)} - ${at(projection.lower)})`,
                    }}
                />
                <div
                    className="absolute inset-y-0 left-0 rounded-full bg-foreground"
                    style={{ width: at(projection.to_date) }}
                />
                <div
                    className="absolute inset-y-0 w-0.5 -translate-x-1/2 bg-muted-foreground"
                    style={{ left: at(projection.projected) }}
                />
            </div>
            <p className="font-mono text-[13px] text-muted-foreground tabular-nums">
                {formatCount(projection.to_date)} so far ·{' '}
                {daysLeftIn(projection.month)}
            </p>
        </li>
    );
}

function Month({ month }: { month: MonthProjection[] }) {
    return (
        <Panel title="This month" meta="to date and projected">
            {month.length === 0 ? (
                <p className="py-4 text-center font-mono text-[13px] text-muted-foreground">
                    $ cal # nothing to project yet
                </p>
            ) : (
                <ul className="-m-4 divide-y">
                    {month.map((projection) => (
                        <MonthBar
                            key={projection.metric}
                            projection={projection}
                        />
                    ))}
                </ul>
            )}
        </Panel>
    );
}

export default function Show({
    forecasts,
    milestones,
    month,
}: {
    forecasts: ForecastSeries[];
    milestones: Milestone[];
    month: MonthProjection[];
}) {
    const now = useNow();
    const [historyDays, setHistoryDays] = useState(28);
    const shown = METRICS.flatMap((item) => {
        const series = forecasts.find(
            (forecast) => forecast.metric === item.metric,
        );

        return series ? [{ ...item, series }] : [];
    });

    return (
        <>
            <Head title="Forecasts" />

            <div className="flex flex-col gap-4">
                {shown.length === 0 ? (
                    <div className="rounded-lg border bg-card px-4 py-10 text-center">
                        <EmptyFeed command="$ forecast --days=14">
                            There isn't enough history to forecast yet.
                        </EmptyFeed>
                    </div>
                ) : (
                    <>
                        <dl className="grid gap-px overflow-hidden rounded-lg border bg-border sm:grid-cols-2 xl:grid-cols-4">
                            {shown.map((item) => (
                                <Summary
                                    key={item.metric}
                                    series={item.series}
                                    title={item.title}
                                    now={now}
                                />
                            ))}
                        </dl>

                        <div className="flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
                            <ChartKey
                                historyDays={historyDays}
                                forecastDays={shown[0].series.forecast.length}
                            />
                            <div
                                role="group"
                                aria-label="History shown"
                                className="flex items-center gap-1"
                            >
                                {HISTORY_WINDOWS.map((days) => (
                                    <PresetButton
                                        key={days}
                                        isActive={historyDays === days}
                                        onClick={() => setHistoryDays(days)}
                                    >
                                        {days}d
                                    </PresetButton>
                                ))}
                            </div>
                        </div>

                        <div className="grid gap-4 lg:grid-cols-2">
                            {shown.map((item) => (
                                <Panel
                                    key={item.metric}
                                    title={item.title}
                                    meta={METHODS[item.series.method]}
                                >
                                    <LineChart
                                        history={item.series.history.slice(
                                            -historyDays,
                                        )}
                                        forecast={item.series.forecast}
                                        format={formatValue}
                                        label={`${item.title} per day, the last ${historyDays} days and the next ${item.series.forecast.length} expected`}
                                        unit={item.unit}
                                    />
                                </Panel>
                            ))}
                        </div>
                    </>
                )}

                <div className="grid gap-4 lg:grid-cols-2">
                    <Milestones milestones={milestones} />
                    <Month month={month} />
                </div>

                <Panel title="How these are made">
                    <div className="max-w-prose space-y-2 text-sm text-pretty text-muted-foreground">
                        <p>
                            Each forecast fits a least-squares trend to the last
                            four weeks and carries it forward. Once a metric has
                            three weeks of history, it also follows the weekly
                            pattern, so quiet weekends stay quiet.
                        </p>
                        <p>
                            The shaded band is the 80% range: on about four days
                            out of five, the real value should land inside it.
                            Read the trend rather than single days, since one
                            busy or quiet day says little.
                        </p>
                        <p>
                            Days are UTC calendar days, and bots are left out.
                        </p>
                    </div>
                </Panel>
            </div>
        </>
    );
}

Show.layout = OperationsLayout;
