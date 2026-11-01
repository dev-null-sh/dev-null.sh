import { Deferred, Head, router } from '@inertiajs/react';
import { useId, useLayoutEffect, useRef, useState } from 'react';
import type { CSSProperties, ReactNode } from 'react';
import ProductMetricsController from '@/actions/App/Http/Controllers/ProductMetricsController';
import { Placeholder } from '@/components/operations/placeholder';
import { PresetButton } from '@/components/operations/preset-button';
import { RangePicker, utcDate } from '@/components/operations/range-picker';
import { Panel } from '@/components/operations/section';
import { Stats } from '@/components/operations/stats';
import { Skeleton } from '@/components/ui/skeleton';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useDateTime } from '@/hooks/use-date-time';
import { usePicked } from '@/hooks/use-picked';
import OperationsLayout, { PAGE_PROPS } from '@/layouts/operations/layout';
import { formatCount } from '@/lib/numbers';
import { cn } from '@/lib/utils';
import type {
    AcquisitionDay,
    ActivationDay,
    ActiveUserCount,
    ActiveUsers,
    CommitResponses,
    RetentionByDay as RetentionByDayData,
    RetentionCell,
    RetentionCohort,
    RetentionCohorts as RetentionCohortsData,
    RetentionDay,
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

/**
 * Picking a range reloads every prop at once, the deferred funnel included, so the page keeps showing the old range
 * until the new one has fully arrived. The picker shows the pick right away.
 */
function MetricsRangePicker({ range }: { range: MetricsRange }) {
    const { picked, pick } = usePicked<MetricsRange>();
    const shown = picked ?? range;

    const select = (next: TrafficRange | null) =>
        router.get(
            ProductMetricsController.show().url,
            next ?? { all_time: 1 },
            {
                only: ['range', ...PAGE_PROPS['operations/metrics']],
                preserveState: true,
                preserveScroll: true,
                onFinish: pick(
                    next
                        ? { ...next, all_time: false }
                        : { ...range, all_time: true },
                ),
            },
        );

    return (
        <RangePicker
            range={shown}
            presets={RANGE_PRESETS}
            onSelect={select}
            isPresetRange={!shown.all_time}
        >
            <PresetButton
                isActive={shown.all_time}
                onClick={() => select(null)}
            >
                all time
            </PresetButton>
        </RangePicker>
    );
}

/**
 * A slice of the funnel, narrower at the bottom. Its width only draws the funnel: the rates beside it carry the numbers.
 * Without a value yet, the slice pulses in its place.
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
    value?: string;
}) {
    const inset = (width: number) => (100 - width) / 2;
    const style: CSSProperties = {
        clipPath: `polygon(${inset(top)}% 0, ${100 - inset(top)}% 0, ${100 - inset(bottom)}% 100%, ${inset(bottom)}% 100%)`,
    };

    if (value === undefined) {
        return (
            <div
                aria-hidden
                className="h-20 animate-in delay-300 duration-300 fill-mode-both fade-in"
                style={style}
            >
                <div className="size-full animate-pulse bg-primary/10" />
            </div>
        );
    }

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

/**
 * A step's rate, with its change since the period before on a line kept even without one, so every step is as tall.
 */
function Rate({
    rate,
    label,
    change,
    isWorst,
}: {
    rate: ReactNode;
    label: string;
    change: ReactNode;
    isWorst: boolean;
}) {
    return (
        <div
            className={cn(
                '-mr-2 self-center justify-self-end rounded-lg px-2 py-1.5 text-right font-mono text-xs',
                isWorst && 'bg-warning/10',
            )}
        >
            <p className="font-sans text-xl leading-tight font-semibold tracking-tight tabular-nums">
                {rate}
            </p>
            <p className="text-muted-foreground">{label}</p>
            <p
                className={cn(
                    'tabular-nums',
                    isWorst ? 'font-semibold' : 'text-muted-foreground',
                )}
            >
                {change}
            </p>
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

/**
 * The funnel, or while it loads, its layout with the definitions already in place and the numbers pulsing, so it takes
 * the same room at every width.
 */
function FunnelValue({ funnel }: { funnel?: Funnel }) {
    const count = (value: number | undefined) =>
        value === undefined ? undefined : formatCount(value);
    const inline = (value: number | undefined) =>
        count(value) ?? <Placeholder className="w-[3ch]" />;
    const worst = Object.entries(funnel?.changes ?? {})
        .filter(
            (entry): entry is [FunnelRate, number] =>
                entry[1] !== null && entry[1] < 0,
        )
        .sort(([, a], [, b]) => a - b)[0]?.[0];

    const rate = (key: FunnelRate, label: string) => {
        const change = funnel?.changes[key];

        return (
            <Rate
                rate={
                    funnel ? (
                        percent(funnel.rates[key])
                    ) : (
                        <Placeholder className="w-[3.5em]" />
                    )
                }
                label={label}
                change={
                    !funnel ? (
                        <Placeholder className="w-[7ch]" />
                    ) : change === null || change === undefined ? (
                        ' '
                    ) : (
                        `${change >= 0 ? '+' : '−'}${Math.abs(change * 100).toFixed(1)} pt`
                    )
                }
                isWorst={worst === key}
            />
        );
    };

    return (
        <div className="grid grid-cols-[96px_minmax(0,1fr)] gap-x-4 gap-y-3 sm:grid-cols-[128px_minmax(0,1fr)_minmax(0,1fr)] sm:gap-y-0.5">
            <div />
            <FunnelSlice
                top={100}
                bottom={86}
                className="bg-muted"
                value={
                    funnel?.visitors === null ? '–' : count(funnel?.visitors)
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
                value={count(funnel?.signups)}
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
                value={count(funnel?.activated)}
            />
            <Definition title="Activation">
                Got going in their first 12 hours: starred posts by 2+ people,
                followed 10+ people, or someone replied to, quoted or starred
                their post. Activation rate = activated ÷ the{' '}
                {inline(funnel?.activation_eligible)} sign-ups at least 12 hours
                old.
            </Definition>

            {rate('retention', 'retention rate')}
            <FunnelSlice
                top={58}
                bottom={46}
                className="bg-primary text-primary-foreground"
                value={count(funnel?.retained)}
            />
            <Definition title="Retention">
                Came back at least once from the day after signing up, day 1,
                while /dev/null is days old; day 14 later.{' '}
                {funnel?.retention_eligible === 0 ? (
                    'Not measurable yet: no signup has got through its day 1 since tracking started.'
                ) : (
                    <>
                        Retention rate = retained ÷ the{' '}
                        {inline(funnel?.retention_eligible)} sign-ups old enough
                        to tell.
                    </>
                )}
            </Definition>
        </div>
    );
}

/**
 * Stripes of the panel's color over a mark whose value was backfilled, and is a floor: the mark keeps its color, so its
 * size still reads as its value.
 */
const BACKFILLED_STRIPES =
    'bg-[image:repeating-linear-gradient(135deg,transparent_0_4px,color-mix(in_oklab,var(--card)_70%,transparent)_4px_6px)]';

type DailySegment = {
    value: number;
    className: string;
};

type DailyBar = {
    date: string;
    value: number;
    isPartial: boolean;
    isBackfilled?: boolean;
    label: string;
    details?: string[];
    segments?: DailySegment[];
    shadow?: number;
};

function DateAxis({ range }: { range: TrafficRange }) {
    const { formatZonedDate } = useDateTime();
    return (
        <div className="mt-1.5 flex justify-between font-mono text-xs text-muted-foreground tabular-nums">
            <span>{formatZonedDate(range.from, 'UTC')}</span>
            <span>{formatZonedDate(range.to, 'UTC')}</span>
        </div>
    );
}

function DailyBarsSkeleton({ range }: { range: TrafficRange }) {
    return (
        <div aria-hidden className="flex flex-1 flex-col pt-5">
            <div className="flex min-h-48 flex-1 animate-in flex-col border-b border-rail delay-300 duration-300 fill-mode-both fade-in">
                <Skeleton className="flex-1 rounded-b-none" />
            </div>
            <DateAxis range={range} />
        </div>
    );
}

/**
 * A shadow's outline in a 100 by 100 box stretched over the gap between two bars: from the top of the bar before, at
 * `from`, curving down (or up) to `to` on the next one.
 */
function shadowPath(from: number, to: number): string {
    return `M0 ${from} C55 ${from} 45 ${to} 100 ${to} L100 100 L0 100 Z`;
}

/**
 * One slot per day of the range, so charts of the same range line up, with a bar on the days that have one, as tall as
 * its value against the tallest, and a line at the average of the range, to tell the days above it from the days below.
 * A partial day, still counting, is lighter, unless the bar is stacked from segments, the first at the bottom, whose
 * own colors carry the meaning. A backfilled day is striped, as its value is a floor. A bar with a shadow is narrower,
 * and the bar of the day before casts a shadow onto it, curving from its top down to the shadow's value. Tooltips of
 * the bars near an edge align with it, so they stay on screen. The chart takes the height left in its panel, 12rem at
 * least.
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
    const { formatZonedDate } = useDateTime();
    const shadowId = useId().replaceAll(/[^\w-]/g, '');

    if (bars.length === 0) {
        return (
            <div className="flex flex-1 flex-col pt-5">
                <div className="flex min-h-48 flex-1 items-center justify-center border-b border-rail px-4">
                    <p className="max-w-sm text-center font-mono text-[13px] text-balance text-muted-foreground">
                        {empty}
                    </p>
                </div>
                <DateAxis range={range} />
            </div>
        );
    }

    const top = Math.max(
        Number.MIN_VALUE,
        average ?? 0,
        ...bars.map((bar) => bar.value),
    );
    const label = (date: string) => formatZonedDate(date, 'UTC');
    const byDate = new Map(bars.map((bar) => [bar.date, bar]));
    const hasShadows = bars.some((bar) => bar.shadow !== undefined);
    const dates: string[] = [];

    for (
        let day = Date.parse(range.from);
        day <= Date.parse(range.to);
        day += 86_400_000
    ) {
        dates.push(new Date(day).toISOString().slice(0, 10));
    }

    return (
        <figure className="@container flex flex-1 flex-col pt-5">
            <div className="relative min-h-48 flex-1">
                <div
                    key={`${range.from}:${range.to}`}
                    className="absolute inset-0 flex items-end gap-(--bar-gap) border-b border-rail [--bar-gap:min(0.25rem,var(--fitting-gap))] sm:[--bar-gap:min(0.5rem,var(--fitting-gap))]"
                    style={
                        {
                            '--fitting-gap': `${25 / dates.length}cqw`,
                        } as CSSProperties
                    }
                >
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
                        const before = byDate.get(dates[index - 1]);

                        return (
                            <div
                                key={date}
                                className="group relative flex h-full flex-1 items-end justify-center"
                            >
                                {bar && (
                                    <>
                                        {bar.shadow !== undefined && before && (
                                            <svg
                                                aria-hidden
                                                viewBox="0 0 100 100"
                                                preserveAspectRatio="none"
                                                className="absolute bottom-0 h-full text-muted-foreground"
                                                style={{
                                                    left: 'calc(-20% - var(--bar-gap))',
                                                    width: 'calc(40% + var(--bar-gap))',
                                                }}
                                            >
                                                <defs>
                                                    <linearGradient
                                                        id={`${shadowId}-${date}`}
                                                    >
                                                        <stop
                                                            offset="0"
                                                            stopColor="currentColor"
                                                            stopOpacity={0.32}
                                                        />
                                                        <stop
                                                            offset="1"
                                                            stopColor="currentColor"
                                                            stopOpacity={0.08}
                                                        />
                                                    </linearGradient>
                                                </defs>
                                                <path
                                                    d={shadowPath(
                                                        100 -
                                                            (before.value /
                                                                top) *
                                                                100,
                                                        100 -
                                                            (bar.shadow / top) *
                                                                100,
                                                    )}
                                                    fill={`url(#${shadowId}-${date})`}
                                                />
                                            </svg>
                                        )}
                                        {bar.segments ? (
                                            <div
                                                className={cn(
                                                    'relative flex flex-col-reverse gap-0.5 overflow-hidden rounded-t',
                                                    hasShadows
                                                        ? 'w-3/5'
                                                        : 'w-full',
                                                )}
                                                style={{
                                                    height: `${(bar.value / top) * 100}%`,
                                                }}
                                            >
                                                {bar.segments
                                                    .filter(
                                                        (segment) =>
                                                            segment.value > 0,
                                                    )
                                                    .map((segment) => (
                                                        <div
                                                            key={
                                                                segment.className
                                                            }
                                                            className={
                                                                segment.className
                                                            }
                                                            style={{
                                                                flex: `${segment.value} 1 0`,
                                                            }}
                                                        />
                                                    ))}
                                            </div>
                                        ) : (
                                            <div
                                                className={cn(
                                                    'w-full rounded-t',
                                                    bar.isPartial
                                                        ? 'bg-primary/40'
                                                        : 'bg-primary',
                                                    bar.isBackfilled &&
                                                        BACKFILLED_STRIPES,
                                                )}
                                                style={{
                                                    height: `${(bar.value / top) * 100}%`,
                                                }}
                                            />
                                        )}
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
                                            {bar.details?.map((detail) => (
                                                <p
                                                    key={detail}
                                                    className="text-muted-foreground tabular-nums"
                                                >
                                                    {detail}
                                                </p>
                                            ))}
                                        </div>
                                    </>
                                )}
                            </div>
                        );
                    })}
                </div>
            </div>
            <DateAxis range={range} />
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
                                <th scope="row">{label(bar.date)}</th>
                                <td>
                                    {[bar.label, ...(bar.details ?? [])].join(
                                        ', ',
                                    )}
                                </td>
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
type AcquisitionMeasure = 'signups' | 'conversion';

function AcquisitionMeasurePicker({
    measure,
    onChange,
}: {
    measure: AcquisitionMeasure;
    onChange: (measure: AcquisitionMeasure) => void;
}) {
    return (
        <div role="group" aria-label="Measure" className="flex gap-1">
            <PresetButton
                isActive={measure === 'signups'}
                onClick={() => onChange('signups')}
            >
                sign-ups
            </PresetButton>
            <PresetButton
                isActive={measure === 'conversion'}
                onClick={() => onChange('conversion')}
            >
                conversion rate
            </PresetButton>
        </div>
    );
}

function AcquisitionByDay({
    range,
    days,
    conversion,
    measure,
}: {
    range: TrafficRange;
    days: AcquisitionDay[];
    conversion: number | null;
    measure: AcquisitionMeasure;
}) {
    const complete = days.filter((day) => day.complete);
    const converting = days.filter(
        (
            day,
        ): day is AcquisitionDay & { visitors: number; converted: number } =>
            day.visitors !== null && day.visitors > 0 && day.converted !== null,
    );

    return measure === 'signups' ? (
        <DailyBars
            range={range}
            average={
                complete.length === 0
                    ? null
                    : Math.round(
                          complete.reduce((sum, day) => sum + day.signups, 0) /
                              complete.length,
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
    );
}

const DEVICES = ['Phone', 'Desktop', 'Tablet'];

/**
 * The conversion rate of each type of device, with the visitors behind it, so a shift towards phones can be told
 * apart from phones converting worse, next to the rate of all devices from the funnel. The visitors who tapped to sign
 * in, and the share of them who finished, tell visitors who never tap from those who give up at GitHub. Until the
 * funnel arrives, it holds the room of the usual three.
 */
function ConversionByDevice({ funnel }: { funnel?: Funnel }) {
    const devices = funnel?.conversion_by_device;

    if (devices === null || devices?.length === 0) {
        return (
            <p className="font-mono text-[13px] text-muted-foreground">
                nothing yet: Fathom isn't set up, didn't answer, or had no
                visitors in these dates
            </p>
        );
    }

    return (
        <Stats
            stats={[
                ...(devices?.map((device) => ({
                    label: device.device.toLowerCase() || 'unknown',
                    value: percent(ratio(device.converted, device.visitors)),
                    note: `${formatCount(device.converted)} of ${formatCount(device.visitors)} visitors · ${formatCount(device.tapped)} tapped sign in, ${percent(ratio(device.converted, device.tapped))} finished`,
                })) ??
                    DEVICES.map((device) => ({ label: device.toLowerCase() }))),
                {
                    label: 'all devices',
                    value: funnel && percent(funnel.rates.conversion),
                    note:
                        funnel && funnel.visitors !== null
                            ? `of ${formatCount(funnel.visitors)} visitors`
                            : undefined,
                },
            ]}
        />
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

/**
 * The parts of a day's active users, from the bottom of the bar: returning users in blue, then the day's signups in
 * amber, darker when they activated. Signups too young to tell are hatched, as they are not settled yet. The colors are
 * this chart's own, so they live on its wrapper, not in the theme.
 */
const ACTIVE_USER_PARTS = [
    { key: 'returning', label: 'returning users', className: 'bg-primary' },
    {
        key: 'activated',
        label: 'new, activated',
        className: 'bg-(--new)',
    },
    {
        key: 'not_activated',
        label: 'new, not activated',
        className: 'bg-(--new-soft)',
    },
    {
        key: 'pending',
        label: 'new, under 12h',
        className:
            'bg-[image:repeating-linear-gradient(135deg,var(--new)_0_2px,transparent_2px_5px)]',
    },
] as const;

const NEW_USER_PARTS = [
    { key: 'activated', label: 'activated' },
    { key: 'not_activated', label: 'not' },
    { key: 'pending', label: 'under 12h' },
] as const;

/**
 * Each bar after the first is shadowed by the day before's, from its total down to the day's returning users. They may
 * have been there the day before or come back after a longer gap, so the drop is a net change: the people who stopped
 * coming, minus the people back after a break. Today, still counting, gets no shadow.
 */
function ActiveUserCounts({
    range,
    counts,
}: {
    range: TrafficRange;
    counts: ActiveUserCount[];
}) {
    const { formatZonedDate } = useDateTime();
    const today = utcDate();
    const complete = counts.filter((day) => day.date !== today);

    return (
        <div className="flex flex-1 flex-col [--new-soft:#f0c48c] [--new:#c96f12] dark:[--new-soft:#6b4a26] dark:[--new:#e0893a]">
            <ul className="flex flex-wrap gap-x-4 gap-y-1 font-mono text-xs text-muted-foreground">
                {ACTIVE_USER_PARTS.map((part) => (
                    <li key={part.key} className="flex items-center gap-1.5">
                        <span
                            aria-hidden
                            className={cn(
                                'size-2.5 rounded-sm',
                                part.className,
                            )}
                        />
                        {part.label}
                    </li>
                ))}
                <li className="flex items-center gap-1.5">
                    <svg
                        aria-hidden
                        viewBox="0 0 100 100"
                        preserveAspectRatio="none"
                        className="h-2.5 w-4 text-muted-foreground"
                    >
                        <path
                            d={shadowPath(0, 60)}
                            fill="currentColor"
                            fillOpacity={0.3}
                        />
                    </svg>
                    change since the day before, net
                </li>
            </ul>
            <DailyBars
                range={range}
                average={
                    complete.length === 0
                        ? null
                        : Math.round(
                              complete.reduce(
                                  (sum, day) => sum + day.count,
                                  0,
                              ) / complete.length,
                          )
                }
                bars={counts.map((day, index) => {
                    const before = counts[index - 1];
                    const isPartial = day.date === today;
                    const isShadowed =
                        !isPartial &&
                        before !== undefined &&
                        Date.parse(day.date) - Date.parse(before.date) ===
                            86_400_000;
                    const change = isShadowed
                        ? before.count - day.returning
                        : 0;
                    const fresh = NEW_USER_PARTS.filter(
                        (part) => day[part.key] > 0,
                    );

                    return {
                        date: day.date,
                        value: ACTIVE_USER_PARTS.reduce(
                            (sum, part) => sum + day[part.key],
                            0,
                        ),
                        isPartial,
                        label: `${formatCount(day.count)} active`,
                        details: [
                            `${formatCount(day.returning)} returning users, back after any gap`,
                            ...(isShadowed
                                ? [
                                      `${formatCount(Math.abs(change))} ${change >= 0 ? 'fewer' : 'more'} than the ${formatCount(before.count)} of ${formatZonedDate(before.date, 'UTC')}, net`,
                                  ]
                                : []),
                            ...(fresh.length > 0
                                ? [
                                      `${formatCount(fresh.reduce((sum, part) => sum + day[part.key], 0))} new: ${fresh.map((part) => `${formatCount(day[part.key])} ${part.label}`).join(', ')}`,
                                  ]
                                : []),
                        ],
                        segments: ACTIVE_USER_PARTS.map((part) => ({
                            value: day[part.key],
                            className: part.className,
                        })),
                        shadow: isShadowed ? day.returning : undefined,
                    };
                })}
                caption="Daily active users"
                column="Active users"
                format={formatCount}
                empty="nothing yet: days are counted from the day tracking started"
            />
        </div>
    );
}

type RetentionDayNumber = keyof RetentionByDayData;

const RETENTION_DAYS: { key: RetentionDayNumber; label: string }[] = [
    { key: 'day_1', label: 'day 1' },
    { key: 'day_7', label: 'day 7' },
];

function RetentionDayPicker({
    day,
    onChange,
}: {
    day: RetentionDayNumber;
    onChange: (day: RetentionDayNumber) => void;
}) {
    return (
        <div role="group" aria-label="Day" className="flex gap-1">
            {RETENTION_DAYS.map((option) => (
                <PresetButton
                    key={option.key}
                    isActive={day === option.key}
                    onClick={() => onChange(option.key)}
                >
                    {option.label}
                </PresetButton>
            ))}
        </div>
    );
}

/**
 * What marks a backfilled value, shown only when the chart has some.
 */
function BackfilledNote({
    recordedSince,
    marker,
}: {
    recordedSince: string;
    marker: ReactNode;
}) {
    const { formatZonedDate } = useDateTime();
    return (
        <p className="flex items-center gap-1.5 font-mono text-xs text-muted-foreground">
            {marker}
            approximate before {formatZonedDate(recordedSince, 'UTC')}
        </p>
    );
}

const STRIPES_SWATCH = (
    <span
        aria-hidden
        className={cn('size-3 rounded-sm bg-primary', BACKFILLED_STRIPES)}
    />
);

/**
 * The share of each signup day's sign-ups seen again exactly N days later, once that day is over, and the line at the
 * share of the whole range. The note about backfilled bars depends on both days, so switching days doesn't move the
 * chart.
 */
function RetentionByDay({
    range,
    days,
    day,
    recordedSince,
    hasBackfilled,
}: {
    range: TrafficRange;
    days: RetentionDay[];
    day: RetentionDayNumber;
    recordedSince: string;
    hasBackfilled: boolean;
}) {
    const label = RETENTION_DAYS.find((option) => option.key === day)?.label;
    const eligible = days.reduce((sum, item) => sum + item.eligible, 0);
    const retained = days.reduce((sum, item) => sum + item.retained, 0);

    return (
        <div className="flex flex-1 flex-col">
            {hasBackfilled && (
                <BackfilledNote
                    recordedSince={recordedSince}
                    marker={STRIPES_SWATCH}
                />
            )}
            <DailyBars
                range={range}
                average={ratio(retained, eligible)}
                bars={days.map((item) => {
                    const rate = item.retained / item.eligible;

                    return {
                        date: item.date,
                        value: rate,
                        isPartial: false,
                        isBackfilled: item.backfilled,
                        label: `${percent(rate)} of ${formatCount(item.eligible)} sign-ups`,
                        details: [
                            `active on ${label}`,
                            ...(item.backfilled ? ['approximate'] : []),
                        ],
                    };
                })}
                caption={`Retention on ${label} by signup day`}
                column={`Retention on ${label}`}
                format={percent}
                empty={`nothing yet: a signup day shows once its ${label} is over`}
            />
        </div>
    );
}

/**
 * The tooltip of a cell or a point: what it is, its rate, and how much of it is a floor.
 */
function RetentionTip({
    title,
    cell,
    signups,
    className,
}: {
    title: string;
    cell: RetentionCell;
    signups?: number;
    className?: string;
}) {
    return (
        <div
            className={cn(
                'pointer-events-none absolute bottom-full z-10 mb-2 hidden rounded-md border bg-popover px-2.5 py-1.5 text-left font-mono text-xs font-normal whitespace-nowrap text-popover-foreground shadow-sm group-hover:block',
                className,
            )}
        >
            <p className="text-muted-foreground">{title}</p>
            <p className="font-medium tabular-nums">
                {percent(cell.retained / cell.eligible)} of{' '}
                {formatCount(cell.eligible)} sign-ups
            </p>
            {signups !== undefined && cell.eligible < signups && (
                <p className="text-muted-foreground tabular-nums">
                    {formatCount(signups - cell.eligible)} more not there yet
                </p>
            )}
            {cell.backfilled > 0 && (
                <p className="text-muted-foreground tabular-nums">
                    {cell.backfilled === cell.eligible
                        ? 'approximate'
                        : `approximate for ${formatCount(cell.backfilled)} of them`}
                </p>
            )}
        </div>
    );
}

/**
 * Weekly cohorts down, days since signing up across: each cell is the share of the week's sign-ups seen again on that
 * exact day, among the ones who reached it, darker when higher, against the highest of the table, in whole percents
 * (the tooltip has the decimal). The color stops where the text on it would get hard to read, earlier in dark mode.
 * Reading down a column tells whether newer cohorts stay better. A cell with backfilled days reads "≥", as it is a
 * floor.
 */
function RetentionTable({ cohorts }: { cohorts: RetentionCohort[] }) {
    const { formatZonedDate } = useDateTime();
    const top = Math.max(
        Number.MIN_VALUE,
        ...cohorts.flatMap((cohort) =>
            cohort.days
                .filter((cell) => cell.eligible > 0)
                .map((cell) => cell.retained / cell.eligible),
        ),
    );
    const days = cohorts[0]?.days.map((cell) => cell.day) ?? [];

    return (
        <table className="w-full table-fixed border-separate border-spacing-0.5 font-mono text-xs tabular-nums [--heat-max:88%] dark:[--heat-max:56%]">
            <caption className="sr-only">
                Share of each week's sign-ups active on each day after signing
                up
            </caption>
            <thead>
                <tr className="text-muted-foreground">
                    <th
                        scope="col"
                        className="w-24 pb-1 text-left font-normal sm:w-28"
                    >
                        signed up
                    </th>
                    {days.map((day) => (
                        <th
                            key={day}
                            scope="col"
                            className="pb-1 font-normal whitespace-nowrap"
                        >
                            <span className="sm:hidden">d</span>
                            <span className="hidden sm:inline">day </span>
                            {day}
                        </th>
                    ))}
                </tr>
            </thead>
            <tbody>
                {cohorts.map((cohort, row) => (
                    <tr key={cohort.week}>
                        <th
                            scope="row"
                            className="pr-2 text-left font-normal whitespace-nowrap"
                        >
                            <span>{formatZonedDate(cohort.week, 'UTC')}</span>
                            <span className="ml-1.5 text-muted-foreground">
                                {formatCount(cohort.signups)}
                                <span className="sr-only"> sign-ups</span>
                            </span>
                        </th>
                        {cohort.days.map((cell, column) => {
                            if (cell.eligible === 0) {
                                return (
                                    <td
                                        key={cell.day}
                                        className="h-8 rounded-sm bg-muted/40 text-center text-muted-foreground"
                                    >
                                        <span aria-hidden>·</span>
                                        <span className="sr-only">
                                            not there yet
                                        </span>
                                    </td>
                                );
                            }

                            const rate = cell.retained / cell.eligible;

                            return (
                                <td
                                    key={cell.day}
                                    className="group relative h-8 rounded-sm text-center whitespace-nowrap"
                                    style={
                                        {
                                            '--heat': rate / top,
                                            backgroundColor:
                                                'color-mix(in oklab, var(--primary) calc(10% + var(--heat) * (var(--heat-max) - 10%)), var(--card))',
                                        } as CSSProperties
                                    }
                                >
                                    {cell.backfilled > 0 && '≥\u00a0'}
                                    {Math.round(rate * 100)}%
                                    <RetentionTip
                                        title={`week of ${formatZonedDate(cohort.week, 'UTC')}, day ${cell.day}`}
                                        cell={cell}
                                        signups={cohort.signups}
                                        className={cn(
                                            column < days.length / 2
                                                ? 'left-0'
                                                : 'right-0',
                                            row === 0 &&
                                                'top-full bottom-auto mt-2 mb-0',
                                        )}
                                    />
                                </td>
                            );
                        })}
                    </tr>
                ))}
            </tbody>
        </table>
    );
}

/**
 * The share of all the range's sign-ups seen again on each day after signing up, from everyone on the day they signed
 * up. A point mostly backfilled is hollow, as it is a floor.
 */
function RetentionCurve({ curve }: { curve: RetentionCell[] }) {
    const last = Math.max(30, ...curve.map((point) => point.day));
    const points = [
        { x: 0, y: 100 },
        ...curve.map((point) => ({
            x: (point.day / last) * 100,
            y: (point.retained / point.eligible) * 100,
        })),
    ];

    return (
        <figure>
            <figcaption className="mb-2 font-mono text-xs text-muted-foreground">
                all cohorts, active on each day after signing up
            </figcaption>
            <div className="relative ml-9 h-40 border-b border-l border-rail">
                {[25, 50, 75, 100].map((tick) => (
                    <div
                        key={tick}
                        aria-hidden
                        className="absolute inset-x-0 border-t border-rail/60"
                        style={{ bottom: `${tick}%` }}
                    >
                        <span className="absolute top-0 right-full mr-1.5 -translate-y-1/2 font-mono text-xs text-muted-foreground tabular-nums">
                            {tick}%
                        </span>
                    </div>
                ))}
                <svg
                    aria-hidden
                    viewBox="0 0 100 100"
                    preserveAspectRatio="none"
                    className="absolute inset-0 size-full overflow-visible text-primary"
                >
                    <polyline
                        points={points
                            .map((point) => `${point.x},${100 - point.y}`)
                            .join(' ')}
                        fill="none"
                        stroke="currentColor"
                        strokeWidth={2}
                        strokeLinejoin="round"
                        vectorEffect="non-scaling-stroke"
                    />
                </svg>
                {curve.map((point) => (
                    <div
                        key={point.day}
                        className="group absolute flex size-4 -translate-x-1/2 translate-y-1/2 items-center justify-center"
                        style={{
                            left: `${(point.day / last) * 100}%`,
                            bottom: `${(point.retained / point.eligible) * 100}%`,
                        }}
                    >
                        <span
                            className={cn(
                                'size-2 rounded-full border-2 border-primary ring-2 ring-card',
                                point.backfilled * 2 > point.eligible
                                    ? 'bg-card'
                                    : 'bg-primary',
                            )}
                        />
                        <RetentionTip
                            title={`day ${point.day}`}
                            cell={point}
                            className={
                                point.day < last / 2 ? 'left-0' : 'right-0'
                            }
                        />
                    </div>
                ))}
            </div>
            <div className="mt-1.5 ml-9 flex justify-between font-mono text-xs text-muted-foreground tabular-nums">
                <span>day 0</span>
                <span>day {last}</span>
            </div>
            <div className="sr-only">
                <table>
                    <caption>Retention on each day after signing up</caption>
                    <thead>
                        <tr>
                            <th scope="col">Day</th>
                            <th scope="col">Retention</th>
                        </tr>
                    </thead>
                    <tbody>
                        {curve.map((point) => (
                            <tr key={point.day}>
                                <th scope="row">{point.day}</th>
                                <td>
                                    {percent(point.retained / point.eligible)}{' '}
                                    of {formatCount(point.eligible)} sign-ups
                                    {point.backfilled > 0 &&
                                        `, approximate for ${formatCount(point.backfilled)} of them`}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </figure>
    );
}

function RetentionCohorts({
    cohorts,
    recordedSince,
}: {
    cohorts: RetentionCohortsData;
    recordedSince: string;
}) {
    if (cohorts.curve.length === 0) {
        return (
            <div className="flex min-h-48 flex-1 items-center justify-center">
                <p className="max-w-sm text-center font-mono text-[13px] text-balance text-muted-foreground">
                    nothing yet: a cohort shows once its first sign-ups are a
                    day old
                </p>
            </div>
        );
    }

    return (
        <div className="space-y-5">
            {cohorts.cohorts.some((cohort) =>
                cohort.days.some((cell) => cell.backfilled > 0),
            ) && (
                <BackfilledNote
                    recordedSince={recordedSince}
                    marker={
                        <span aria-hidden className="text-foreground">
                            ≥
                        </span>
                    }
                />
            )}
            <RetentionTable cohorts={cohorts.cohorts} />
            <RetentionCurve curve={cohorts.curve} />
        </div>
    );
}

export default function Show({
    range,
    funnel,
    activeUsers,
    responses,
    newcomerFollows,
    activeUserCounts,
    retentionByDay,
    retentionCohorts,
    retentionRecordedSince,
}: {
    range: MetricsRange;
    funnel?: Funnel;
    activeUsers: ActiveUsers;
    responses: CommitResponses;
    newcomerFollows: NewcomerFollows;
    activeUserCounts: ActiveUserCount[];
    retentionByDay: RetentionByDayData;
    retentionCohorts: RetentionCohortsData;
    retentionRecordedSince: string;
}) {
    const days =
        Math.round(
            (Date.parse(range.to) - Date.parse(range.from)) / 86_400_000,
        ) + 1;
    const [measure, setMeasure] = useState<AcquisitionMeasure>('signups');
    const [retentionDay, setRetentionDay] =
        useState<RetentionDayNumber>('day_1');

    return (
        <>
            <Head title="Growth" />

            <div className="mb-4">
                <MetricsRangePicker range={range} />
            </div>

            <div className="grid gap-4 xl:grid-cols-2">
                <Panel
                    title="Funnel"
                    meta={
                        range.all_time
                            ? 'changes: last 7d vs 7d before'
                            : `changes: vs the ${days} days before`
                    }
                >
                    <Deferred data="funnel" fallback={<FunnelValue />}>
                        <FunnelValue
                            key={`${range.from}:${range.to}`}
                            funnel={funnel}
                        />
                    </Deferred>
                </Panel>

                <Panel title="Daily active users" meta="utc" fill>
                    <ActiveUserCounts range={range} counts={activeUserCounts} />
                </Panel>

                <Panel
                    title="Acquisition by day"
                    meta="utc"
                    fill
                    actions={
                        <AcquisitionMeasurePicker
                            measure={measure}
                            onChange={setMeasure}
                        />
                    }
                >
                    <Deferred
                        data="funnel"
                        fallback={<DailyBarsSkeleton range={range} />}
                    >
                        {funnel && (
                            <AcquisitionByDay
                                range={range}
                                days={funnel.acquisition_by_day}
                                conversion={funnel.rates.conversion}
                                measure={measure}
                            />
                        )}
                    </Deferred>
                </Panel>

                <Panel title="Activation by signup day" meta="utc" fill>
                    <Deferred
                        data="funnel"
                        fallback={<DailyBarsSkeleton range={range} />}
                    >
                        {funnel && (
                            <ActivationByDay
                                range={range}
                                days={funnel.activation_by_day}
                                average={funnel.rates.activation}
                            />
                        )}
                    </Deferred>
                </Panel>

                <Panel
                    title="Retention by signup day"
                    meta="utc"
                    fill
                    actions={
                        <RetentionDayPicker
                            day={retentionDay}
                            onChange={setRetentionDay}
                        />
                    }
                >
                    <RetentionByDay
                        range={range}
                        days={retentionByDay[retentionDay]}
                        day={retentionDay}
                        recordedSince={retentionRecordedSince}
                        hasBackfilled={[
                            ...retentionByDay.day_1,
                            ...retentionByDay.day_7,
                        ].some((item) => item.backfilled)}
                    />
                </Panel>

                <Panel title="Retention by cohort" meta="by week">
                    <RetentionCohorts
                        cohorts={retentionCohorts}
                        recordedSince={retentionRecordedSince}
                    />
                </Panel>

                <Panel
                    className="xl:col-span-2"
                    title="Conversion by device"
                    meta="sign-ups ÷ visitors, both from Fathom"
                >
                    <Deferred data="funnel" fallback={<ConversionByDevice />}>
                        <ConversionByDevice funnel={funnel} />
                    </Deferred>
                </Panel>

                <Panel
                    className="xl:col-span-2"
                    title="Also watch"
                    meta="active users: today, live"
                >
                    <p className="mb-3 max-w-2xl text-sm text-muted-foreground">
                        Active means signed in and opening /dev/null on the web,
                        the installed app, the mobile app or the terminal.
                        Background refreshes don't count. Answers are replies,
                        quotes or stars from someone else within 24 hours of a
                        top-level commit; follows leave out the ones imported
                        from GitHub.
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
                </Panel>
            </div>
        </>
    );
}

Show.layout = OperationsLayout;
