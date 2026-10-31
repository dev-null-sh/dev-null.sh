import { useLayoutEffect, useRef, useState } from 'react';
import type { PointerEvent, ReactNode } from 'react';
import { useDateTime } from '@/hooks/use-date-time';
import { formatCount } from '@/lib/numbers';
import { cn } from '@/lib/utils';

const AXIS_WIDTH = 40;
const X_AXIS_HEIGHT = 20;
const TOP_PADDING = 8;

/**
 * The rendered width of a chart's box, so its marks and labels are drawn at their real size instead of being scaled.
 */
function useWidth<T extends HTMLElement>() {
    const ref = useRef<T>(null);
    const [width, setWidth] = useState(0);

    useLayoutEffect(() => {
        const element = ref.current;

        if (!element) {
            return;
        }

        const observer = new ResizeObserver(([entry]) =>
            setWidth(Math.round(entry.contentRect.width)),
        );

        setWidth(Math.round(element.getBoundingClientRect().width));
        observer.observe(element);

        return () => observer.disconnect();
    }, []);

    return [ref, width] as const;
}

/**
 * Round axis steps (1, 2 or 5 × 10^n, never under 1, as every chart counts) from zero to at least the largest value.
 */
export function niceTicks(max: number, count = 4): number[] {
    if (max <= 0) {
        return [0, 1];
    }

    const raw = max / count;
    const magnitude = 10 ** Math.floor(Math.log10(raw));
    const step = Math.max(
        1,
        [1, 2, 2.5, 5, 10]
            .map((factor) => factor * magnitude)
            .find((candidate) => candidate >= raw) ?? magnitude * 10,
    );
    const ticks: number[] = [];

    for (let tick = 0; tick < max + step * 0.999; tick += step) {
        ticks.push(Number(tick.toPrecision(12)));
    }

    return ticks;
}

export function compactNumber(value: number): string {
    return new Intl.NumberFormat('en', {
        notation: 'compact',
        maximumFractionDigits: 1,
    }).format(value);
}

function Tooltip({
    x,
    width,
    children,
}: {
    x: number;
    width: number;
    children: ReactNode;
}) {
    const edge =
        x < width * 0.2 ? 'left' : x > width * 0.8 ? 'right' : 'center';

    return (
        <div
            role="presentation"
            className={cn(
                'pointer-events-none absolute top-0 z-10 w-max max-w-56 rounded-md bg-foreground px-2.5 py-1.5 text-xs leading-5 text-background shadow-md',
                edge === 'center' && '-translate-x-1/2',
                edge === 'right' && '-translate-x-full',
            )}
            style={{ left: x }}
        >
            {children}
        </div>
    );
}

export function Sparkline({
    values,
    className,
    label,
}: {
    values: number[];
    className?: string;
    label: string;
}) {
    const width = 100;
    const height = 32;
    const max = Math.max(1, ...values);
    const step = values.length > 1 ? width / (values.length - 1) : width;
    const points = values.map(
        (value, index) =>
            [index * step, height - 2 - (value / max) * (height - 4)] as const,
    );
    const line = points.map(([x, y]) => `${x},${y}`).join(' ');
    const last = points.at(-1);

    return (
        <svg
            role="img"
            aria-label={label}
            viewBox={`0 0 ${width} ${height}`}
            preserveAspectRatio="none"
            className={cn(
                'h-8 w-full overflow-visible text-foreground',
                className,
            )}
        >
            {points.length > 1 && (
                <>
                    <polygon
                        points={`0,${height} ${line} ${width},${height}`}
                        className="fill-current opacity-[0.08]"
                    />
                    <polyline
                        points={line}
                        fill="none"
                        stroke="currentColor"
                        strokeWidth={1.5}
                        strokeLinejoin="round"
                        strokeLinecap="round"
                        vectorEffect="non-scaling-stroke"
                    />
                </>
            )}
            {last && (
                <circle
                    cx={last[0]}
                    cy={last[1]}
                    r={2.5}
                    className="fill-current"
                    vectorEffect="non-scaling-stroke"
                />
            )}
        </svg>
    );
}

export type LinePoint = { date: string; value: number };

export type ForecastPoint = LinePoint & { lower: number; upper: number };

/**
 * A daily line, solid for what happened and dashed for what is expected next, with the expected range as a wash
 * behind the dashes. Hovering any day shows its value, and, for a forecast day, its range.
 */
export function LineChart({
    history,
    forecast = [],
    height = 200,
    format = compactNumber,
    label,
    unit,
}: {
    history: LinePoint[];
    forecast?: ForecastPoint[];
    height?: number;
    format?: (value: number) => string;
    label: string;
    unit: string;
}) {
    const { formatDate } = useDateTime();
    const [ref, width] = useWidth<HTMLDivElement>();
    const [hovered, setHovered] = useState<number | null>(null);
    const days: (LinePoint &
        Partial<ForecastPoint> & { isForecast: boolean })[] = [
        ...history.map((point) => ({ ...point, isForecast: false })),
        ...forecast.map((point) => ({ ...point, isForecast: true })),
    ];
    const ticks = niceTicks(
        Math.max(1, ...days.map((day) => day.upper ?? day.value)),
    );
    const top = ticks.at(-1) ?? 1;
    const plotWidth = Math.max(0, width - AXIS_WIDTH);
    const plotHeight = height - X_AXIS_HEIGHT - TOP_PADDING;
    const x = (index: number) =>
        AXIS_WIDTH +
        (days.length > 1
            ? (index / (days.length - 1)) * plotWidth
            : plotWidth / 2);
    const y = (value: number) =>
        TOP_PADDING + plotHeight - (value / top) * plotHeight;
    const path = (points: { index: number; value: number }[]) =>
        points
            .map(
                (point, at) =>
                    `${at === 0 ? 'M' : 'L'}${x(point.index).toFixed(1)},${y(point.value).toFixed(1)}`,
            )
            .join(' ');
    const historyPath = path(
        history.map((point, index) => ({ index, value: point.value })),
    );
    const joined =
        history.length > 0
            ? [{ index: history.length - 1, value: history.at(-1)!.value }]
            : [];
    const forecastPath = path([
        ...joined,
        ...forecast.map((point, at) => ({
            index: history.length + at,
            value: point.value,
        })),
    ]);
    const band =
        forecast.length > 0
            ? [
                  ...joined.map(
                      (point) => `${x(point.index)},${y(point.value)}`,
                  ),
                  ...forecast.map(
                      (point, at) =>
                          `${x(history.length + at)},${y(point.upper)}`,
                  ),
                  ...forecast
                      .map(
                          (point, at) =>
                              `${x(history.length + at)},${y(point.lower)}`,
                      )
                      .reverse(),
              ].join(' ')
            : '';
    const labelEvery = Math.max(
        1,
        Math.ceil(days.length / Math.max(2, Math.floor(plotWidth / 72))),
    );
    const active = hovered === null ? null : days[hovered];

    const onPointerMove = (event: PointerEvent<SVGSVGElement>) => {
        const box = event.currentTarget.getBoundingClientRect();
        const offset = event.clientX - box.left - AXIS_WIDTH;
        const index = Math.round(
            (offset / Math.max(1, plotWidth)) * (days.length - 1),
        );

        setHovered(Math.min(days.length - 1, Math.max(0, index)));
    };

    return (
        <figure className="relative" ref={ref}>
            {width > 0 && days.length > 0 && (
                <svg
                    role="img"
                    aria-label={label}
                    width={width}
                    height={height}
                    className="block touch-pan-y overflow-visible"
                    onPointerMove={onPointerMove}
                    onPointerLeave={() => setHovered(null)}
                >
                    {ticks.map((tick) => (
                        <g key={tick}>
                            <line
                                x1={AXIS_WIDTH}
                                x2={width}
                                y1={y(tick)}
                                y2={y(tick)}
                                className="stroke-border"
                            />
                            <text
                                x={AXIS_WIDTH - 8}
                                y={y(tick)}
                                dy="0.32em"
                                textAnchor="end"
                                className="fill-muted-foreground font-mono text-[11px] tabular-nums"
                            >
                                {compactNumber(tick)}
                            </text>
                        </g>
                    ))}
                    {days.map(
                        (day, index) =>
                            index % labelEvery === 0 && (
                                <text
                                    key={day.date}
                                    x={x(index)}
                                    y={height - 4}
                                    textAnchor={
                                        x(index) > width - 24 ? 'end' : 'middle'
                                    }
                                    className="fill-muted-foreground font-mono text-[11px]"
                                >
                                    {formatDate(day.date, {
                                        timeZone: 'UTC',
                                        year: false,
                                    })}
                                </text>
                            ),
                    )}
                    {forecast.length > 0 && history.length > 0 && (
                        <line
                            x1={x(history.length - 0.5)}
                            x2={x(history.length - 0.5)}
                            y1={TOP_PADDING}
                            y2={TOP_PADDING + plotHeight}
                            className="stroke-rail"
                            strokeDasharray="2 3"
                        />
                    )}
                    {band && (
                        <polygon
                            points={band}
                            className="fill-muted-foreground opacity-15"
                        />
                    )}
                    <path
                        d={historyPath}
                        fill="none"
                        className="stroke-foreground"
                        strokeWidth={2}
                        strokeLinejoin="round"
                        strokeLinecap="round"
                    />
                    {forecast.length > 0 && (
                        <path
                            d={forecastPath}
                            fill="none"
                            className="stroke-muted-foreground"
                            strokeWidth={2}
                            strokeDasharray="4 4"
                            strokeLinejoin="round"
                            strokeLinecap="round"
                        />
                    )}
                    {active && hovered !== null && (
                        <g>
                            <line
                                x1={x(hovered)}
                                x2={x(hovered)}
                                y1={TOP_PADDING}
                                y2={TOP_PADDING + plotHeight}
                                className="stroke-muted-foreground/50"
                            />
                            <circle
                                cx={x(hovered)}
                                cy={y(active.value)}
                                r={4.5}
                                strokeWidth={2}
                                className={cn(
                                    'stroke-card',
                                    active.isForecast
                                        ? 'fill-muted-foreground'
                                        : 'fill-foreground',
                                )}
                            />
                        </g>
                    )}
                </svg>
            )}
            {(width === 0 || days.length === 0) && (
                <div
                    style={{ height }}
                    className="flex items-center justify-center font-mono text-[13px] text-muted-foreground"
                >
                    {width > 0 && 'nothing to draw yet'}
                </div>
            )}
            {active && hovered !== null && (
                <Tooltip x={x(hovered)} width={width}>
                    <p className="font-medium">
                        {formatDate(active.date, {
                            timeZone: 'UTC',
                            year: false,
                        })}
                        {active.isForecast && (
                            <span className="font-normal opacity-70">
                                {' '}
                                · expected
                            </span>
                        )}
                    </p>
                    <p className="font-mono tabular-nums">
                        {format(active.value)} {unit}
                    </p>
                    {active.isForecast &&
                        active.lower !== undefined &&
                        active.upper !== undefined && (
                            <p className="font-mono tabular-nums opacity-70">
                                {format(active.lower)} – {format(active.upper)}
                            </p>
                        )}
                </Tooltip>
            )}
            <div className="sr-only">
                <table>
                    <caption>{label}</caption>
                    <thead>
                        <tr>
                            <th scope="col">Day</th>
                            <th scope="col">{unit}</th>
                            <th scope="col">Range</th>
                        </tr>
                    </thead>
                    <tbody>
                        {days.map((day) => (
                            <tr key={day.date}>
                                <th scope="row">
                                    <time dateTime={day.date}>
                                        {formatDate(day.date, {
                                            timeZone: 'UTC',
                                            year: true,
                                        })}
                                    </time>
                                    {day.isForecast ? ' (expected)' : ''}
                                </th>
                                <td>{format(day.value)}</td>
                                <td>
                                    {day.isForecast &&
                                    day.lower !== undefined &&
                                    day.upper !== undefined
                                        ? `${format(day.lower)} – ${format(day.upper)}`
                                        : ''}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </figure>
    );
}

export type ColumnSeries<K extends string> = {
    key: K;
    label: string;
    /**
     * Colors both the columns and the legend and tooltip swatches, so it pairs a `fill-*` with its `bg-*`.
     */
    className: string;
};

/**
 * Stacked columns over time, the first series at the bottom, split by a 2px gap in the surface color. Each column is
 * capped at 24px wide, and hovering one lists its series.
 */
export function ColumnChart<K extends string>({
    buckets,
    series,
    height = 160,
    label,
    formatBucket,
    unit,
}: {
    buckets: ({ at: string } & Record<K, number>)[];
    series: ColumnSeries<K>[];
    height?: number;
    label: string;
    formatBucket: (at: string) => string;
    unit: string;
}) {
    const [ref, width] = useWidth<HTMLDivElement>();
    const [hovered, setHovered] = useState<number | null>(null);
    const totals = buckets.map((bucket) =>
        series.reduce((sum, item) => sum + bucket[item.key], 0),
    );
    const ticks = niceTicks(Math.max(1, ...totals), 3);
    const top = ticks.at(-1) ?? 1;
    const plotWidth = Math.max(0, width - AXIS_WIDTH);
    const plotHeight = height - X_AXIS_HEIGHT - TOP_PADDING;
    const slot = buckets.length > 0 ? plotWidth / buckets.length : 0;
    const barWidth = Math.max(2, Math.min(24, slot - Math.max(2, slot * 0.25)));
    const y = (value: number) =>
        TOP_PADDING + plotHeight - (value / top) * plotHeight;
    const labelEvery = Math.max(
        1,
        Math.ceil(buckets.length / Math.max(2, Math.floor(plotWidth / 64))),
    );
    const active = hovered === null ? null : buckets[hovered];

    return (
        <figure className="relative" ref={ref}>
            {width > 0 && (
                <svg
                    role="img"
                    aria-label={label}
                    width={width}
                    height={height}
                    className="block overflow-visible"
                    onPointerLeave={() => setHovered(null)}
                >
                    {ticks.map((tick) => (
                        <g key={tick}>
                            <line
                                x1={AXIS_WIDTH}
                                x2={width}
                                y1={y(tick)}
                                y2={y(tick)}
                                className="stroke-border"
                            />
                            <text
                                x={AXIS_WIDTH - 8}
                                y={y(tick)}
                                dy="0.32em"
                                textAnchor="end"
                                className="fill-muted-foreground font-mono text-[11px] tabular-nums"
                            >
                                {compactNumber(tick)}
                            </text>
                        </g>
                    ))}
                    {buckets.map((bucket, index) => {
                        const center = AXIS_WIDTH + slot * index + slot / 2;
                        let base = 0;

                        return (
                            <g
                                key={bucket.at}
                                onPointerEnter={() => setHovered(index)}
                            >
                                <rect
                                    x={AXIS_WIDTH + slot * index}
                                    y={TOP_PADDING}
                                    width={slot}
                                    height={plotHeight}
                                    className={cn(
                                        'fill-transparent',
                                        hovered === index &&
                                            'fill-muted-foreground/8',
                                    )}
                                />
                                {series.map((item) => {
                                    const value = bucket[item.key];

                                    if (value <= 0) {
                                        return null;
                                    }

                                    const from = y(base);
                                    const to = y(base + value);
                                    base += value;

                                    return (
                                        <rect
                                            key={item.key}
                                            x={center - barWidth / 2}
                                            y={to}
                                            width={barWidth}
                                            height={Math.max(
                                                1,
                                                from -
                                                    to -
                                                    (base > value ? 2 : 0),
                                            )}
                                            rx={Math.min(2, barWidth / 4)}
                                            className={item.className}
                                        />
                                    );
                                })}
                                {index % labelEvery === 0 && (
                                    <text
                                        x={center}
                                        y={height - 4}
                                        textAnchor={
                                            center > width - 24
                                                ? 'end'
                                                : 'middle'
                                        }
                                        className="fill-muted-foreground font-mono text-[11px]"
                                    >
                                        {formatBucket(bucket.at)}
                                    </text>
                                )}
                            </g>
                        );
                    })}
                </svg>
            )}
            {width === 0 && <div style={{ height }} />}
            {active && hovered !== null && (
                <Tooltip
                    x={AXIS_WIDTH + slot * hovered + slot / 2}
                    width={width}
                >
                    <p className="font-medium">{formatBucket(active.at)}</p>
                    {series.map((item) => (
                        <p
                            key={item.key}
                            className="flex items-center gap-2 font-mono tabular-nums"
                        >
                            <span
                                aria-hidden
                                className={cn(
                                    'size-2 rounded-xs',
                                    item.className,
                                )}
                            />
                            {item.label}
                            <span className="ml-auto pl-3">
                                {formatCount(active[item.key])}
                            </span>
                        </p>
                    ))}
                </Tooltip>
            )}
            <div className="sr-only">
                <table>
                    <caption>{label}</caption>
                    <thead>
                        <tr>
                            <th scope="col">Time</th>
                            {series.map((item) => (
                                <th key={item.key} scope="col">
                                    {item.label} {unit}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {buckets.map((bucket) => (
                            <tr key={bucket.at}>
                                <th scope="row">{formatBucket(bucket.at)}</th>
                                {series.map((item) => (
                                    <td key={item.key}>{bucket[item.key]}</td>
                                ))}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </figure>
    );
}

export function Legend<K extends string>({
    series,
}: {
    series: ColumnSeries<K>[];
}) {
    return (
        <ul className="flex flex-wrap gap-x-4 gap-y-1 font-mono text-xs text-muted-foreground">
            {series.map((item) => (
                <li key={item.key} className="flex items-center gap-1.5">
                    <span
                        aria-hidden
                        className={cn('size-2.5 rounded-xs', item.className)}
                    />
                    {item.label}
                </li>
            ))}
        </ul>
    );
}
