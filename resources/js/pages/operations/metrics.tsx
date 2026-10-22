import { Deferred, Head, router } from '@inertiajs/react';
import { useLayoutEffect, useRef, useState } from 'react';
import type { CSSProperties, ReactNode } from 'react';
import ProductMetricsController from '@/actions/App/Http/Controllers/ProductMetricsController';
import { PresetButton } from '@/components/operations/preset-button';
import { RangePicker, utcDate } from '@/components/operations/range-picker';
import { Section } from '@/components/operations/section';
import { Stats } from '@/components/operations/stats';
import { Skeleton } from '@/components/ui/skeleton';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import AppLayout from '@/layouts/app-layout';
import OperationsLayout from '@/layouts/operations/layout';
import { formatCount } from '@/lib/numbers';
import { cn, formatZonedDate } from '@/lib/utils';
import type {
    AcquisitionDay,
    ActivationDay,
    ActiveUsers,
    ActivityBucket,
    CommitResponses,
    Funnel,
    FunnelRate,
    MetricsRange,
    NewcomerFollows,
    TrafficRange,
} from '@/types';

const RANGE_PRESETS = [
    { label: '7d', days: 7 },
    { label: '30d', days: 30 },
    { label: '90d', days: 90 },
];

function percent(rate: number | null): string {
    return rate === null ? '–' : `${(rate * 100).toFixed(1)}%`;
}

function ratio(count: number, total: number): number | null {
    return total === 0 ? null : count / total;
}

function MetricsRangePicker({ range }: { range: MetricsRange }) {
    const select = (next: TrafficRange | null) =>
        router.get(ProductMetricsController.show().url, next ?? {}, {
            preserveScroll: true,
        });

    return (
        <RangePicker
            range={range}
            presets={RANGE_PRESETS}
            onSelect={select}
            isPresetRange={!range.all_time}
        >
            <PresetButton
                isActive={range.all_time}
                onClick={() => select(null)}
            >
                all time
            </PresetButton>
        </RangePicker>
    );
}

/**
 * A slice of the funnel, narrower at the bottom. Its width only draws the funnel: the rates beside it carry the numbers.
 */
function FunnelSlice({
    top,
    bottom,
    className,
    value,
}: {
    top: number;
    bottom: number;
    className: string;
    value: string;
}) {
    const inset = (width: number) => (100 - width) / 2;
    const style: CSSProperties = {
        clipPath: `polygon(${inset(top)}% 0, ${100 - inset(top)}% 0, ${100 - inset(bottom)}% 100%, ${inset(bottom)}% 100%)`,
    };

    return (
        <div
            className={cn(
                'flex h-20 items-center justify-center px-4 text-[22px] font-semibold tracking-tight tabular-nums',
                className,
            )}
            style={style}
        >
            {value}
        </div>
    );
}

function Rate({
    rate,
    label,
    change,
    isWorst,
}: {
    rate: number | null;
    label: string;
    change: number | null;
    isWorst: boolean;
}) {
    return (
        <div
            className={cn(
                '-mr-2 self-center justify-self-end rounded-lg px-2 py-1.5 text-right font-mono text-xs',
                isWorst && 'bg-(--lane-0)/10',
            )}
        >
            <p className="font-sans text-xl leading-tight font-semibold tracking-tight tabular-nums">
                {percent(rate)}
            </p>
            <p className="text-muted-foreground">{label}</p>
            {change !== null && (
                <p
                    className={cn(
                        'tabular-nums',
                        isWorst ? 'font-semibold' : 'text-muted-foreground',
                    )}
                >
                    {`${change >= 0 ? '+' : '−'}${Math.abs(change * 100).toFixed(1)} pt`}
                </p>
            )}
        </div>
    );
}

/**
 * A step's name and definition. On wide screens it is cut to the height of its slice, so the funnel stays in one piece,
 * and hovering a definition that doesn't fit shows it whole. On phones it sits whole under its slice.
 */
function Definition({
    title,
    children,
}: {
    title: string;
    children: ReactNode;
}) {
    const text = useRef<HTMLParagraphElement>(null);
    const [isClamped, setIsClamped] = useState(false);

    useLayoutEffect(() => {
        const element = text.current;

        if (!element) {
            return;
        }

        const measure = () =>
            setIsClamped(element.scrollHeight > element.clientHeight);
        const observer = new ResizeObserver(measure);

        measure();
        observer.observe(element);

        return () => observer.disconnect();
    }, []);

    return (
        <Tooltip open={isClamped ? undefined : false}>
            <TooltipTrigger asChild>
                <div
                    tabIndex={isClamped ? 0 : undefined}
                    className={cn(
                        'col-span-2 mb-3 self-center sm:col-span-1 sm:mb-0',
                        isClamped && 'cursor-help',
                    )}
                >
                    <p className="font-semibold sm:text-sm">{title}</p>
                    <p
                        ref={text}
                        className="mt-0.5 text-[13px] leading-snug text-muted-foreground sm:line-clamp-3 sm:text-xs"
                    >
                        {children}
                    </p>
                </div>
            </TooltipTrigger>
            <TooltipContent side="left" className="max-w-xs leading-snug">
                {children}
            </TooltipContent>
        </Tooltip>
    );
}

function retentionDefinition(funnel: Funnel): string {
    const definition =
        'Came back at least once from the day after signing up, day 1, while /dev/null is days old; day 14 later.';

    return funnel.retention_eligible === 0
        ? `${definition} Not measurable yet: no signup has got through its day 1 since tracking started.`
        : `${definition} Retention rate = retained ÷ the ${formatCount(funnel.retention_eligible)} sign-ups old enough to tell.`;
}

function FunnelValue({ funnel }: { funnel: Funnel }) {
    const changes = Object.entries(funnel.changes).filter(
        (entry): entry is [FunnelRate, number] => entry[1] !== null,
    );
    const worst = changes
        .filter(([, change]) => change < 0)
        .sort(([, a], [, b]) => a - b)[0]?.[0];

    const rate = (key: FunnelRate, label: string) => (
        <Rate
            rate={funnel.rates[key]}
            label={label}
            change={funnel.changes[key]}
            isWorst={worst === key}
        />
    );

    return (
        <div className="grid grid-cols-[96px_minmax(0,1fr)] gap-x-4 gap-y-3 sm:grid-cols-[128px_minmax(0,1fr)_minmax(0,1fr)] sm:gap-y-0.5">
            <div />
            <FunnelSlice
                top={100}
                bottom={86}
                className="bg-muted"
                value={
                    funnel.visitors === null
                        ? '–'
                        : formatCount(funnel.visitors)
                }
            />
            <Definition title="Visitors">
                People not signed in who opened dev-null.sh in these dates,
                counted by Fathom, anonymously. Ad blockers hide some of them.
            </Definition>

            {rate('conversion', 'conversion rate')}
            <FunnelSlice
                top={86}
                bottom={72}
                className="bg-primary-soft"
                value={formatCount(funnel.signups)}
            />
            <Definition title="Acquisition">
                Created an account with GitHub, counted by /dev/null. Conversion
                rate = sign-ups ÷ visitors, both counted by Fathom, so ad
                blockers hide as many of each.
            </Definition>

            {rate('activation', 'activation rate')}
            <FunnelSlice
                top={72}
                bottom={58}
                className="bg-primary/40"
                value={formatCount(funnel.activated)}
            />
            <Definition title="Activation">
                Got something from the network in their first 12 hours: starred
                by 3+ people, or someone replied to, quoted or starred their
                post. Activation rate = activated ÷ the{' '}
                {formatCount(funnel.activation_eligible)} sign-ups at least 12
                hours old.
            </Definition>

            {rate('retention', 'retention rate')}
            <FunnelSlice
                top={58}
                bottom={46}
                className="bg-primary text-primary-foreground"
                value={formatCount(funnel.retained)}
            />
            <Definition title="Retention">
                {retentionDefinition(funnel)}
            </Definition>
        </div>
    );
}

function FunnelSkeleton() {
    return (
        <div
            aria-hidden
            className="animate-in space-y-3 delay-300 duration-300 fill-mode-both fade-in"
        >
            {[0, 1, 2, 3].map((row) => (
                <Skeleton key={row} className="h-24 w-full rounded-lg" />
            ))}
        </div>
    );
}

type DailyBar = {
    date: string;
    value: number;
    isPartial: boolean;
    label: string;
};

/**
 * One slot per day of the range, so charts of the same range line up, with a bar on the days that have one, as tall as
 * its value against the tallest, and a line at the average of the range, to tell the days above it from the days below.
 * A partial day, still counting, is lighter. Tooltips of the bars near an edge align with it, so they stay on screen.
 */
function DailyBars({
    range,
    bars,
    average,
    caption,
    column,
    empty,
    format,
}: {
    range: TrafficRange;
    bars: DailyBar[];
    average: number | null;
    caption: string;
    column: string;
    empty: string;
    format: (value: number) => string;
}) {
    if (bars.length === 0) {
        return (
            <p className="rounded-lg border px-4 py-2.5 font-mono text-[13px] text-muted-foreground">
                {empty}
            </p>
        );
    }

    const top = Math.max(
        Number.MIN_VALUE,
        average ?? 0,
        ...bars.map((bar) => bar.value),
    );
    const label = (date: string) => formatZonedDate(date, 'UTC');
    const byDate = new Map(bars.map((bar) => [bar.date, bar]));
    const dates: string[] = [];

    for (
        let day = Date.parse(range.from);
        day <= Date.parse(range.to);
        day += 86_400_000
    ) {
        dates.push(new Date(day).toISOString().slice(0, 10));
    }

    return (
        <figure className="pt-5">
            <div className="relative flex h-48 items-end gap-1 border-b border-rail sm:gap-2">
                {average !== null && (
                    <div
                        className="pointer-events-none absolute inset-x-0 z-[1] border-t border-dashed border-foreground/50"
                        style={{ bottom: `${(average / top) * 100}%` }}
                    >
                        <span className="absolute bottom-full left-0 mb-0.5 rounded-sm bg-background/90 px-1 font-mono text-xs text-muted-foreground tabular-nums">
                            avg {format(average)}
                        </span>
                    </div>
                )}
                {dates.map((date, index) => {
                    const bar = byDate.get(date);

                    return (
                        <div
                            key={date}
                            className="group relative flex h-full flex-1 items-end"
                        >
                            {bar && (
                                <>
                                    <div
                                        className={cn(
                                            'w-full rounded-t',
                                            bar.isPartial
                                                ? 'bg-primary/40'
                                                : 'bg-primary',
                                        )}
                                        style={{
                                            height: `${(bar.value / top) * 100}%`,
                                        }}
                                    />
                                    <div
                                        className={cn(
                                            'pointer-events-none absolute bottom-full z-10 mb-2 hidden rounded-md border bg-popover px-2.5 py-1.5 font-mono text-xs whitespace-nowrap shadow-sm group-hover:block',
                                            index < dates.length / 3
                                                ? 'left-0'
                                                : index >=
                                                    (dates.length * 2) / 3
                                                  ? 'right-0'
                                                  : 'left-1/2 -translate-x-1/2',
                                        )}
                                    >
                                        <p className="text-muted-foreground">
                                            {label(date)}
                                            {bar.isPartial && ', so far'}
                                        </p>
                                        <p className="font-medium tabular-nums">
                                            {bar.label}
                                        </p>
                                    </div>
                                </>
                            )}
                        </div>
                    );
                })}
            </div>
            <div className="mt-1.5 flex justify-between font-mono text-xs text-muted-foreground">
                <span>{label(range.from)}</span>
                <span>{label(range.to)}</span>
            </div>
            <div className="sr-only">
                <table>
                    <caption>{caption}</caption>
                    <thead>
                        <tr>
                            <th scope="col">Day</th>
                            <th scope="col">{column}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {bars.map((bar) => (
                            <tr key={bar.date}>
                                <th scope="row">{bar.date}</th>
                                <td>{bar.label}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </figure>
    );
}

/**
 * Signups or the conversion rate of each day, one at a time, rather than both on one chart with two scales: each keeps
 * its own zero, so neither trend is drawn against the other's. The tooltip tells both.
 */
function AcquisitionByDay({
    range,
    days,
    conversion,
}: {
    range: TrafficRange;
    days: AcquisitionDay[];
    conversion: number | null;
}) {
    const [measure, setMeasure] = useState<'signups' | 'conversion'>('signups');
    const complete = days.filter((day) => day.complete);
    const converting = days.filter(
        (
            day,
        ): day is AcquisitionDay & { visitors: number; converted: number } =>
            day.visitors !== null && day.visitors > 0 && day.converted !== null,
    );

    return (
        <>
            <div role="group" aria-label="Measure" className="flex gap-1">
                <PresetButton
                    isActive={measure === 'signups'}
                    onClick={() => setMeasure('signups')}
                >
                    sign-ups
                </PresetButton>
                <PresetButton
                    isActive={measure === 'conversion'}
                    onClick={() => setMeasure('conversion')}
                >
                    conversion rate
                </PresetButton>
            </div>
            {measure === 'signups' ? (
                <DailyBars
                    range={range}
                    average={
                        complete.length === 0
                            ? null
                            : Math.round(
                                  complete.reduce(
                                      (sum, day) => sum + day.signups,
                                      0,
                                  ) / complete.length,
                              )
                    }
                    bars={days
                        .filter((day) => day.signups > 0)
                        .map((day) => ({
                            date: day.date,
                            value: day.signups,
                            isPartial: !day.complete,
                            label:
                                day.visitors && day.converted !== null
                                    ? `${formatCount(day.signups)} sign-ups, ${percent(day.converted / day.visitors)} conversion`
                                    : `${formatCount(day.signups)} sign-ups`,
                        }))}
                    caption="Sign-ups by day"
                    column="Sign-ups"
                    format={formatCount}
                    empty="no sign-ups in these dates"
                />
            ) : (
                <DailyBars
                    range={range}
                    average={conversion}
                    bars={converting.map((day) => {
                        const rate = day.converted / day.visitors;

                        return {
                            date: day.date,
                            value: rate,
                            isPartial: !day.complete,
                            label: `${percent(rate)} of ${formatCount(day.visitors)} visitors, ${formatCount(day.signups)} sign-ups`,
                        };
                    })}
                    caption="Conversion rate by day"
                    column="Conversion rate"
                    format={percent}
                    empty="nothing yet: Fathom isn't set up, didn't answer, or had no visitors in these dates"
                />
            )}
        </>
    );
}

function ActivationByDay({
    range,
    days,
    average,
}: {
    range: TrafficRange;
    days: ActivationDay[];
    average: number | null;
}) {
    return (
        <DailyBars
            range={range}
            average={average}
            bars={days.map((day) => {
                const rate = day.activated / day.eligible;

                return {
                    date: day.date,
                    value: rate,
                    isPartial: !day.complete,
                    label: `${percent(rate)} of ${formatCount(day.eligible)} sign-ups`,
                };
            })}
            caption="Activation rate by signup day"
            column="Activation rate"
            format={percent}
            empty="nothing yet: a signup day shows once its first sign-ups are 12 hours old"
        />
    );
}

function ActiveUserCounts({
    range,
    counts,
}: {
    range: TrafficRange;
    counts: ActivityBucket[];
}) {
    const today = utcDate();
    const complete = counts.filter((day) => day.date !== today);

    return (
        <DailyBars
            range={range}
            average={
                complete.length === 0
                    ? null
                    : Math.round(
                          complete.reduce((sum, day) => sum + day.count, 0) /
                              complete.length,
                      )
            }
            bars={counts.map((day) => ({
                date: day.date,
                value: day.count,
                isPartial: day.date === today,
                label: `${formatCount(day.count)} active`,
            }))}
            caption="Daily active users"
            column="Active users"
            format={formatCount}
            empty="nothing yet: days are counted from the day tracking started"
        />
    );
}

export default function Show({
    range,
    funnel,
    activeUsers,
    responses,
    newcomerFollows,
    activeUserCounts,
}: {
    range: MetricsRange;
    funnel?: Funnel;
    activeUsers: ActiveUsers;
    responses: CommitResponses;
    newcomerFollows: NewcomerFollows;
    activeUserCounts: ActivityBucket[];
}) {
    const days =
        Math.round(
            (Date.parse(range.to) - Date.parse(range.from)) / 86_400_000,
        ) + 1;

    return (
        <>
            <Head title="Product metrics" />

            <Section title="Metrics">
                <MetricsRangePicker range={range} />
            </Section>

            <Section
                title="Funnel"
                meta={
                    range.all_time
                        ? 'changes: last 7 days vs the 7 before'
                        : `changes: vs the ${days} days before`
                }
            >
                <Deferred data="funnel" fallback={<FunnelSkeleton />}>
                    {funnel && <FunnelValue funnel={funnel} />}
                </Deferred>
            </Section>

            <Section title="Daily active users" meta="by day, utc">
                <ActiveUserCounts range={range} counts={activeUserCounts} />
            </Section>

            <Section title="Acquisition by day" meta="by day, utc">
                <Deferred
                    data="funnel"
                    fallback={<Skeleton className="h-56 w-full rounded-lg" />}
                >
                    {funnel && (
                        <AcquisitionByDay
                            range={range}
                            days={funnel.acquisition_by_day}
                            conversion={funnel.rates.conversion}
                        />
                    )}
                </Deferred>
            </Section>

            <Section title="Activation by signup day" meta="by day, utc">
                <Deferred
                    data="funnel"
                    fallback={<Skeleton className="h-48 w-full rounded-lg" />}
                >
                    {funnel && (
                        <ActivationByDay
                            range={range}
                            days={funnel.activation_by_day}
                            average={funnel.rates.activation}
                        />
                    )}
                </Deferred>
            </Section>

            <Section title="Also watch" meta="active users: today, live">
                <p className="mb-3 max-w-2xl text-sm text-muted-foreground">
                    Active means signed in and opening /dev/null on the web, the
                    installed app, the mobile app or the terminal. Background
                    refreshes don't count. Answers are replies, quotes or stars
                    from someone else within 24 hours of a top-level commit;
                    follows leave out the ones imported from GitHub.
                </p>
                <div className="space-y-3">
                    <Stats
                        stats={[
                            {
                                label: 'active today',
                                value: formatCount(activeUsers.daily),
                            },
                            {
                                label: 'active, 7 days',
                                value: formatCount(activeUsers.weekly),
                                note: `${percent(ratio(activeUsers.weekly_authors, activeUsers.weekly))} posted`,
                            },
                            {
                                label: 'active, 30 days',
                                value: formatCount(activeUsers.monthly),
                            },
                            {
                                label: 'come daily',
                                value: percent(
                                    ratio(
                                        activeUsers.daily,
                                        activeUsers.monthly,
                                    ),
                                ),
                                note: 'today ÷ 30 days',
                            },
                        ]}
                    />
                    <Stats
                        stats={[
                            {
                                label: 'commits answered',
                                value: percent(
                                    ratio(
                                        responses.answered,
                                        responses.commits,
                                    ),
                                ),
                                note:
                                    responses.median_minutes === null
                                        ? 'no answer yet'
                                        : `first after ${responses.median_minutes} min`,
                            },
                            {
                                label: 'first posts answered',
                                value: percent(
                                    ratio(
                                        responses.first_answered,
                                        responses.first_commits,
                                    ),
                                ),
                                note: `of ${formatCount(responses.first_commits)}`,
                            },
                            {
                                label: 'follows, first 24h',
                                value:
                                    newcomerFollows.median_follows === null
                                        ? '–'
                                        : String(
                                              newcomerFollows.median_follows,
                                          ),
                                note: `median of ${formatCount(newcomerFollows.newcomers)} newcomers`,
                            },
                            {
                                label: 'followed back',
                                value: percent(
                                    ratio(
                                        newcomerFollows.followed_back,
                                        newcomerFollows.follows,
                                    ),
                                ),
                                note: `of ${formatCount(newcomerFollows.follows)} follows`,
                            },
                        ]}
                    />
                </div>
            </Section>
        </>
    );
}

Show.layout = [AppLayout, OperationsLayout];
