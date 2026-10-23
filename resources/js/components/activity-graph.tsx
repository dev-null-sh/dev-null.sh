import type { CSSProperties, MouseEvent } from 'react';
import { useRef, useState } from 'react';
import { useDateTime } from '@/hooks/use-date-time';
import { exactCountTitle, formatCount } from '@/lib/numbers';
import { cn } from '@/lib/utils';
import type { Activity, ActivityBucket, ActivityScale } from '@/types';

export type ActivityNoun = [singular: string, plural: string];

const commits: ActivityNoun = ['commit', 'commits'];

const monthNames = [
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'May',
    'Jun',
    'Jul',
    'Aug',
    'Sep',
    'Oct',
    'Nov',
    'Dec',
];

const weekdayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

const weekdayLabels = ['', 'Mon', '', 'Wed', '', 'Fri', ''];

const levelMix = [0, 32, 56, 80, 100];

export const activityWindows: Record<ActivityScale, string> = {
    day: 'in the last 24 hours',
    week: 'in the last 7 days',
    month: 'in the last 30 days',
    year: 'in the last year',
};

function levelColor(level: number): string {
    return level === 0
        ? 'var(--muted)'
        : `color-mix(in oklab, var(--lane) ${levelMix[level]}%, var(--card))`;
}

function levelOf(count: number, max: number): number {
    return count === 0 ? 0 : Math.max(1, Math.ceil((count / max) * 4));
}

function utcDay(date: string): Date {
    return new Date(`${date}T00:00:00Z`);
}

function monthOf(date: string): number {
    return utcDay(date).getUTCMonth();
}

function count(total: number, [singular, plural]: ActivityNoun): string {
    return `${formatCount(total)} ${total === 1 ? singular : plural}`;
}

export function activityTotal(activity: Activity): number {
    return activity.buckets.reduce((sum, bucket) => sum + bucket.count, 0);
}

export function activitySummary(
    activity: Activity,
    noun: ActivityNoun,
): string {
    const total = activityTotal(activity);

    return total === 0 && activity.scale !== 'year'
        ? `No ${noun[1]} yet`
        : `${count(total, noun)} ${activityWindows[activity.scale]}`;
}

function describe(
    bucket: ActivityBucket,
    scale: ActivityScale,
    noun: ActivityNoun,
    dateTime: ReturnType<typeof useDateTime>,
): string {
    const counted =
        bucket.count === 0 ? `No ${noun[1]}` : count(bucket.count, noun);

    if (scale !== 'day') {
        return `${counted} on ${dateTime.formatDate(utcDay(bucket.date), { timeZone: 'UTC', year: scale === 'year', weekday: scale !== 'year' })}`;
    }

    const start = new Date(bucket.date);
    const end = new Date(start.getTime() + 3_600_000);
    const isToday = start.toDateString() === new Date().toDateString();

    return `${counted} between ${dateTime.formatTime(start)} and ${dateTime.formatTime(end)} ${isToday ? 'today' : 'yesterday'}`;
}

function periodOf(bucket: ActivityBucket, scale: ActivityScale): string {
    return scale === 'day' ? bucket.date.slice(0, 13) : bucket.date;
}

function stripLabel(
    bucket: ActivityBucket,
    scale: ActivityScale,
    index: number,
    length: number,
    dateTime: ReturnType<typeof useDateTime>,
): string | null {
    if (scale === 'week') {
        const day = utcDay(bucket.date);

        return `${weekdayNames[day.getUTCDay()]} ${day.getUTCDate()}`;
    }

    if (scale === 'day') {
        const start = new Date(bucket.date);

        return start.getHours() % 6 === 0 && index < length - 2
            ? dateTime.formatTime(start)
            : null;
    }

    const day = utcDay(bucket.date);

    return day.getUTCDay() === 1 && index < length - 3
        ? dateTime.formatDate(day, { timeZone: 'UTC', year: false })
        : null;
}

type HoveredBucket = {
    bucket: ActivityBucket;
    left: number;
    top: number;
    align: 'start' | 'center' | 'end';
};

type CellsProps = {
    buckets: ActivityBucket[];
    max: number;
    onEnter: (
        bucket: ActivityBucket,
        event: MouseEvent<HTMLSpanElement>,
    ) => void;
    onLeave: () => void;
};

const tooltipHalfWidth = 128;

export function ActivityGraph({
    activity,
    lane,
    onPeriodHover,
}: {
    activity: Activity;
    lane: string;
    onPeriodHover?: (period: string | null) => void;
}) {
    const total = activityTotal(activity);
    const isEmpty = total === 0 && activity.scale !== 'year';

    return (
        <section
            aria-labelledby="activity-heading"
            className="border-b px-4 py-5 sm:px-6"
        >
            <div className="mb-3 flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                <h2 id="activity-heading" className="text-sm font-semibold">
                    Commit activity
                </h2>
                <p className="text-sm text-muted-foreground">
                    {isEmpty ? (
                        'No commits yet'
                    ) : (
                        <>
                            <span
                                className="font-mono text-foreground"
                                title={exactCountTitle(total)}
                            >
                                {formatCount(total)}
                            </span>{' '}
                            {total === 1 ? 'commit' : 'commits'}{' '}
                            {activityWindows[activity.scale]}
                        </>
                    )}
                </p>
            </div>

            <ActivityCells
                activity={activity}
                color={lane}
                noun={commits}
                onPeriodHover={onPeriodHover}
            />
        </section>
    );
}

/**
 * The cells, their tooltip and the legend alone, so a page can put them under its own heading. The color is any CSS
 * color the levels are mixed from: an author's lane on a profile, a neutral ink elsewhere.
 */
export function ActivityCells({
    activity,
    color,
    noun,
    onPeriodHover,
}: {
    activity: Activity;
    color: string;
    noun: ActivityNoun;
    onPeriodHover?: (period: string | null) => void;
}) {
    const containerRef = useRef<HTMLDivElement>(null);
    const dateTime = useDateTime();
    const [hovered, setHovered] = useState<HoveredBucket | null>(null);
    const { scale, buckets } = activity;

    const hoverBucket = (
        bucket: ActivityBucket,
        event: MouseEvent<HTMLSpanElement>,
    ) => {
        const container = containerRef.current?.getBoundingClientRect();
        const cell = event.currentTarget.getBoundingClientRect();

        if (!container) {
            return;
        }

        const left = cell.left - container.left + cell.width / 2;

        setHovered({
            bucket,
            left,
            top: cell.top - container.top,
            align:
                left < tooltipHalfWidth
                    ? 'start'
                    : left > container.width - tooltipHalfWidth
                      ? 'end'
                      : 'center',
        });
        onPeriodHover?.(bucket.count > 0 ? periodOf(bucket, scale) : null);
    };

    const clearHover = () => {
        setHovered(null);
        onPeriodHover?.(null);
    };

    const max = Math.max(1, ...buckets.map((bucket) => bucket.count));
    const summary = activitySummary(activity, noun);

    return (
        <div
            ref={containerRef}
            className="relative"
            style={{ '--lane': color } as CSSProperties}
        >
            {scale === 'year' ? (
                <YearGrid
                    buckets={buckets}
                    max={max}
                    summary={summary}
                    onEnter={hoverBucket}
                    onLeave={clearHover}
                />
            ) : (
                <Strip
                    scale={scale}
                    buckets={buckets}
                    max={max}
                    summary={summary}
                    onEnter={hoverBucket}
                    onLeave={clearHover}
                />
            )}

            {hovered && (
                <div
                    aria-hidden
                    className={cn(
                        'pointer-events-none absolute z-10 -translate-y-full rounded-md bg-foreground px-2 py-1 text-xs whitespace-nowrap text-background shadow-md',
                        {
                            '-translate-x-3': hovered.align === 'start',
                            '-translate-x-1/2': hovered.align === 'center',
                            'translate-x-[calc(-100%+0.75rem)]':
                                hovered.align === 'end',
                        },
                    )}
                    data-tooltip
                    style={{ left: hovered.left, top: hovered.top - 6 }}
                >
                    {describe(hovered.bucket, scale, noun, dateTime)}
                </div>
            )}

            <div
                aria-hidden
                className="mt-2 flex items-center justify-end gap-1 text-xs text-muted-foreground"
            >
                <span className="mr-1">Less</span>
                {levelMix.map((_, level) => (
                    <span
                        key={level}
                        className="size-2.5 rounded-[2px]"
                        style={{ background: levelColor(level) }}
                    />
                ))}
                <span className="ml-1">More</span>
            </div>
        </div>
    );
}

function Strip({
    scale,
    buckets,
    max,
    summary,
    onEnter,
    onLeave,
}: CellsProps & { scale: ActivityScale; summary: string }) {
    const dateTime = useDateTime();
    const columns = {
        gridTemplateColumns: `repeat(${buckets.length}, minmax(0, 1fr))`,
    };

    return (
        <div role="img" aria-label={summary}>
            <div
                onMouseLeave={onLeave}
                className="grid gap-[3px]"
                style={columns}
            >
                {buckets.map((bucket) => (
                    <span
                        key={bucket.date}
                        onMouseEnter={(event) => onEnter(bucket, event)}
                        className="h-10 rounded-[2px] transition-shadow hover:shadow-[0_0_0_1.5px_var(--foreground)] sm:h-12"
                        style={{
                            background: levelColor(levelOf(bucket.count, max)),
                        }}
                    />
                ))}
            </div>

            <div
                aria-hidden
                className={cn(
                    'mt-1.5 grid text-xs text-muted-foreground',
                    scale === 'day' && 'font-mono',
                )}
                style={columns}
            >
                {buckets.map((bucket, index) => (
                    <span
                        key={bucket.date}
                        className="overflow-visible whitespace-nowrap"
                    >
                        {stripLabel(
                            bucket,
                            scale,
                            index,
                            buckets.length,
                            dateTime,
                        )}
                    </span>
                ))}
            </div>
        </div>
    );
}

function YearGrid({
    buckets,
    max,
    summary,
    onEnter,
    onLeave,
}: CellsProps & { summary: string }) {
    const weekStarts = buckets.filter((_, index) => index % 7 === 0);
    const monthLabels = weekStarts.map((day, index) => {
        const changesMonth =
            index === 0 ||
            monthOf(day.date) !== monthOf(weekStarts[index - 1].date);
        const nextLabelIsClose =
            index === 0 &&
            weekStarts
                .slice(1, 3)
                .some((week) => monthOf(week.date) !== monthOf(day.date));

        return changesMonth && !nextLabelIsClose
            ? monthNames[monthOf(day.date)]
            : null;
    });

    const columns = {
        gridTemplateColumns: `repeat(${weekStarts.length}, minmax(0, 1fr))`,
    };

    return (
        <div
            ref={(node) => {
                if (node) {
                    node.scrollLeft = node.scrollWidth;
                }
            }}
            className="overflow-x-auto pb-1"
        >
            <div
                role="img"
                aria-label={summary}
                className="grid min-w-136 grid-cols-[auto_minmax(0,1fr)] gap-x-2 gap-y-1.5"
            >
                <span aria-hidden />
                <div
                    aria-hidden
                    className="grid text-xs text-muted-foreground"
                    style={columns}
                >
                    {monthLabels.map((label, index) => (
                        <span
                            key={weekStarts[index].date}
                            className="overflow-visible whitespace-nowrap"
                        >
                            {label}
                        </span>
                    ))}
                </div>

                <div
                    aria-hidden
                    className="grid grid-rows-7 gap-[3px] text-xs text-muted-foreground"
                >
                    {weekdayLabels.map((label, index) => (
                        <span
                            key={index}
                            className="flex items-center leading-none"
                        >
                            {label}
                        </span>
                    ))}
                </div>

                <div
                    onMouseLeave={onLeave}
                    className="grid grid-flow-col grid-rows-7 gap-[3px]"
                    style={columns}
                >
                    {buckets.map((day) => (
                        <span
                            key={day.date}
                            onMouseEnter={(event) => onEnter(day, event)}
                            className="aspect-square rounded-[2px] transition-shadow hover:shadow-[0_0_0_1.5px_var(--foreground)]"
                            style={{
                                background: levelColor(levelOf(day.count, max)),
                            }}
                        />
                    ))}
                </div>
            </div>
        </div>
    );
}
