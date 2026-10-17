import { useLang } from '@erag/lang-sync-inertia/react';
import type { CSSProperties, MouseEvent } from 'react';
import { useRef, useState } from 'react';
import { cn } from '@/lib/utils';
import type { Activity, ActivityBucket, ActivityScale } from '@/types';

type Lang = ReturnType<typeof useLang>;

const monthKeys = [
    'jan',
    'feb',
    'mar',
    'apr',
    'may',
    'jun',
    'jul',
    'aug',
    'sep',
    'oct',
    'nov',
    'dec',
];

const weekdayKeys = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'];

const weekdayLabels = [null, 1, null, 3, null, 5, null];

const levelMix = [0, 32, 56, 80, 100];

function monthName(lang: Lang, month: number): string {
    return lang.__(`users.activity.months.${monthKeys[month]}`);
}

function weekdayName(lang: Lang, weekday: number): string {
    return lang.__(`users.activity.weekdays.${weekdayKeys[weekday]}`);
}

function monthDay(lang: Lang, day: Date): string {
    return lang.__('users.activity.month_day', {
        month: monthName(lang, day.getUTCMonth()),
        day: day.getUTCDate(),
    });
}

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

function formatDay(lang: Lang, date: string, withYear: boolean): string {
    const day = utcDay(date);
    const month = monthName(lang, day.getUTCMonth());

    return withYear
        ? lang.__('users.activity.date_with_year', {
              month,
              day: day.getUTCDate(),
              year: day.getUTCFullYear(),
          })
        : lang.__('users.activity.date_with_weekday', {
              weekday: weekdayName(lang, day.getUTCDay()),
              month,
              day: day.getUTCDate(),
          });
}

function formatTime(time: Date): string {
    return [time.getHours(), time.getMinutes()]
        .map((part) => String(part).padStart(2, '0'))
        .join(':');
}

function describe(
    lang: Lang,
    bucket: ActivityBucket,
    scale: ActivityScale,
): string {
    const commits =
        bucket.count === 0
            ? lang.__('users.activity.no_commits')
            : lang.transChoice('users.activity.commit_count', bucket.count);

    if (scale !== 'day') {
        return lang.__('users.activity.on_date', {
            commits,
            date: formatDay(lang, bucket.date, scale === 'year'),
        });
    }

    const start = new Date(bucket.date);
    const end = new Date(start.getTime() + 3_600_000);
    const isToday = start.toDateString() === new Date().toDateString();

    return lang.__(
        isToday
            ? 'users.activity.between_today'
            : 'users.activity.between_yesterday',
        { commits, start: formatTime(start), end: formatTime(end) },
    );
}

function periodOf(bucket: ActivityBucket, scale: ActivityScale): string {
    return scale === 'day' ? bucket.date.slice(0, 13) : bucket.date;
}

function stripLabel(
    lang: Lang,
    bucket: ActivityBucket,
    scale: ActivityScale,
    index: number,
    length: number,
): string | null {
    if (scale === 'week') {
        const day = utcDay(bucket.date);

        return lang.__('users.activity.weekday_day', {
            weekday: weekdayName(lang, day.getUTCDay()),
            day: day.getUTCDate(),
        });
    }

    if (scale === 'day') {
        const start = new Date(bucket.date);

        return start.getHours() % 6 === 0 && index < length - 2
            ? formatTime(start)
            : null;
    }

    const day = utcDay(bucket.date);

    return day.getUTCDay() === 1 && index < length - 3
        ? monthDay(lang, day)
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
    const lang = useLang();
    const { __, transChoice } = lang;
    const sectionRef = useRef<HTMLElement>(null);
    const [hovered, setHovered] = useState<HoveredBucket | null>(null);
    const { scale, buckets } = activity;

    const hoverBucket = (
        bucket: ActivityBucket,
        event: MouseEvent<HTMLSpanElement>,
    ) => {
        const section = sectionRef.current?.getBoundingClientRect();
        const cell = event.currentTarget.getBoundingClientRect();

        if (!section) {
            return;
        }

        const left = cell.left - section.left + cell.width / 2;

        setHovered({
            bucket,
            left,
            top: cell.top - section.top,
            align:
                left < tooltipHalfWidth
                    ? 'start'
                    : left > section.width - tooltipHalfWidth
                      ? 'end'
                      : 'center',
        });
        onPeriodHover?.(bucket.count > 0 ? periodOf(bucket, scale) : null);
    };

    const clearHover = () => {
        setHovered(null);
        onPeriodHover?.(null);
    };

    const total = buckets.reduce((sum, bucket) => sum + bucket.count, 0);
    const max = Math.max(1, ...buckets.map((bucket) => bucket.count));
    const isEmpty = total === 0 && scale !== 'year';
    const timeWindow = __(`users.activity.windows.${scale}`);
    const summary = isEmpty
        ? __('users.activity.no_commits_yet')
        : __('users.activity.summary', {
              commits: transChoice('users.activity.commit_count', total),
              window: timeWindow,
          });

    return (
        <section
            ref={sectionRef}
            aria-labelledby="activity-heading"
            className="relative border-b px-4 py-5 sm:px-6"
            style={{ '--lane': lane } as CSSProperties}
        >
            <div className="mb-3 flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                <h2 id="activity-heading" className="text-sm font-semibold">
                    {__('users.activity.heading')}
                </h2>
                <p className="text-sm text-muted-foreground">
                    {isEmpty ? (
                        __('users.activity.no_commits_yet')
                    ) : (
                        <>
                            <span className="font-mono text-foreground">
                                {total}
                            </span>{' '}
                            {transChoice('users.counts.commits', total)}{' '}
                            {timeWindow}
                        </>
                    )}
                </p>
            </div>

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
                    {describe(lang, hovered.bucket, scale)}
                </div>
            )}

            <div
                aria-hidden
                className="mt-2 flex items-center justify-end gap-1 text-xs text-muted-foreground"
            >
                <span className="mr-1">{__('users.activity.less')}</span>
                {levelMix.map((_, level) => (
                    <span
                        key={level}
                        className="size-2.5 rounded-[2px]"
                        style={{ background: levelColor(level) }}
                    />
                ))}
                <span className="ml-1">{__('users.activity.more')}</span>
            </div>
        </section>
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
    const lang = useLang();
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
                        {stripLabel(lang, bucket, scale, index, buckets.length)}
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
    const lang = useLang();
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
            ? monthName(lang, monthOf(day.date))
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
                className="grid min-w-[34rem] grid-cols-[auto_minmax(0,1fr)] gap-x-2 gap-y-1.5"
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
                            {label !== null && weekdayName(lang, label)}
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
