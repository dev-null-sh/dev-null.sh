import { Deferred, Head, router } from '@inertiajs/react';
import { X } from 'lucide-react';
import { useRef, useState } from 'react';
import TrafficController from '@/actions/App/Http/Controllers/TrafficController';
import { Breakdown } from '@/components/operations/breakdown';
import { compactNumber, LineChart } from '@/components/operations/charts';
import { Placeholder } from '@/components/operations/placeholder';
import { PresetButton } from '@/components/operations/preset-button';
import { RangePicker } from '@/components/operations/range-picker';
import { Panel } from '@/components/operations/section';
import { Stats } from '@/components/operations/stats';
import { Status } from '@/components/operations/status';
import { Button } from '@/components/ui/button';
import { usePicked } from '@/hooks/use-picked';
import OperationsLayout from '@/layouts/operations/layout';
import { formatCount } from '@/lib/numbers';
import { formatAge } from '@/lib/operations';
import { cn } from '@/lib/utils';
import type {
    Traffic,
    TrafficFilter,
    TrafficFilters,
    TrafficPoint,
    TrafficRange,
    TrafficRow,
    TrafficTotals,
} from '@/types';

const TRAFFIC_PRESETS = [
    { label: 'today', days: 1 },
    { label: '7d', days: 7 },
    { label: '30d', days: 30 },
    { label: '90d', days: 90 },
];

const BREAKDOWNS = [
    {
        key: 'pages',
        filter: 'page',
        title: 'pages',
        empty: '(none)',
        expected: 10,
        className: 'xl:col-span-3',
    },
    {
        key: 'referrers',
        filter: 'referrer',
        title: 'referrers',
        empty: 'direct',
        expected: 10,
        className: 'xl:col-span-3',
    },
    {
        key: 'countries',
        filter: 'country',
        title: 'countries',
        empty: 'unknown',
        expected: 10,
        className: 'xl:col-span-2',
    },
    {
        key: 'devices',
        filter: 'device',
        title: 'devices',
        empty: 'unknown',
        expected: 3,
        className: 'xl:col-span-2',
    },
    {
        key: 'browsers',
        filter: 'browser',
        title: 'browsers',
        empty: 'unknown',
        expected: 10,
        className: 'sm:col-span-2 xl:col-span-2',
    },
] as const;

function percent(value: number): string {
    return `${Math.round(value)}%`;
}

/**
 * Each total, as its tile prints it and as the chart and the breakdowns draw it: the bounce rate in percent, so its
 * axis counts whole ones and its bars fill up to 100.
 */
type Metric = {
    key: keyof TrafficTotals;
    label: string;
    value: (totals: TrafficTotals) => number;
    format: (value: number) => string;
    formatTick: (value: number) => string;
    max?: number;
};

const METRICS: Metric[] = [
    {
        key: 'visitors',
        label: 'visitors',
        value: (totals) => totals.visitors,
        format: formatCount,
        formatTick: compactNumber,
    },
    {
        key: 'pageviews',
        label: 'pageviews',
        value: (totals) => totals.pageviews,
        format: formatCount,
        formatTick: compactNumber,
    },
    {
        key: 'avg_duration',
        label: 'avg time',
        value: (totals) => totals.avg_duration,
        format: formatAge,
        formatTick: formatAge,
    },
    {
        key: 'bounce_rate',
        label: 'bounce rate',
        value: (totals) => totals.bounce_rate * 100,
        format: percent,
        formatTick: percent,
        max: 100,
    },
];

const CHART_HEIGHT = 200;

const regions = new Intl.DisplayNames(['en'], { type: 'region' });

function countryName(code: string): string {
    try {
        return regions.of(code) ?? code;
    } catch {
        return code;
    }
}

function hour(date: string): string {
    return new Date(date).toLocaleTimeString('en', {
        hour: '2-digit',
        minute: '2-digit',
        hourCycle: 'h23',
        timeZone: 'UTC',
    });
}

/**
 * A filter's value as its breakdown prints it.
 */
function filterValue(filter: TrafficFilter, value: string): string {
    const breakdown = BREAKDOWNS.find((item) => item.filter === filter);

    if (value === '') {
        return breakdown?.empty ?? value;
    }

    return filter === 'country' ? countryName(value) : value;
}

function without(
    filters: TrafficFilters,
    filter: TrafficFilter,
): TrafficFilters {
    return Object.fromEntries(
        Object.entries(filters).filter(([other]) => other !== filter),
    );
}

type Selection = { range: TrafficRange; filters: TrafficFilters };

/**
 * The filters in use, each removable, or a hint that rows filter the report. The row keeps its height either way, and
 * takes the focus of a removed filter, so keyboard users don't lose their place.
 */
function FilterBar({
    filters,
    onChange,
}: {
    filters: TrafficFilters;
    onChange: (filters: TrafficFilters) => void;
}) {
    const entries = Object.entries(filters) as [TrafficFilter, string][];
    const bar = useRef<HTMLDivElement>(null);

    const change = (next: TrafficFilters) => {
        bar.current?.focus();
        onChange(next);
    };

    return (
        <div
            ref={bar}
            tabIndex={-1}
            role="group"
            aria-label="Filters"
            className="mb-3 flex min-h-9 flex-wrap items-center gap-2 font-mono text-[13px] outline-none sm:min-h-7"
        >
            {entries.length === 0 ? (
                <span className="text-muted-foreground">
                    all visits · pick a row below to filter
                </span>
            ) : (
                <>
                    {entries.map(([filter, value]) => (
                        <Button
                            key={filter}
                            variant="secondary"
                            size="sm"
                            className="h-9 max-w-full min-w-0 gap-1.5 px-2 font-mono text-[13px] sm:h-7"
                            aria-label={`Remove ${filter}: ${filterValue(filter, value)}`}
                            title={`${filter}: ${filterValue(filter, value)}`}
                            onClick={() => change(without(filters, filter))}
                        >
                            <span className="text-muted-foreground">
                                {filter}
                            </span>
                            <span className="truncate">
                                {filterValue(filter, value)}
                            </span>
                            <X aria-hidden className="size-3.5 shrink-0" />
                        </Button>
                    ))}
                    {entries.length > 1 && (
                        <PresetButton
                            isActive={false}
                            onClick={() => change({})}
                        >
                            clear
                        </PresetButton>
                    )}
                </>
            )}
        </div>
    );
}

/**
 * One total over the range, by hour for a single day and by day otherwise, picked from the four.
 */
function TrafficChart({
    timeline,
    isHourly,
    metric,
    onMetric,
}: {
    timeline?: TrafficPoint[];
    isHourly: boolean;
    metric: Metric;
    onMetric: (metric: Metric) => void;
}) {
    const period = isHourly ? 'hour' : 'day';

    return (
        <div className="rounded-lg border px-4 pt-2 pb-3">
            <div className="mb-2 flex flex-wrap items-center justify-between gap-x-3 gap-y-1">
                <h3 className="font-mono text-[13px] text-muted-foreground">
                    {metric.label} per {period}
                </h3>
                <div className="grid w-full grid-cols-2 gap-1 sm:flex sm:w-auto">
                    {METRICS.map((item) => (
                        <PresetButton
                            key={item.key}
                            isActive={item.key === metric.key}
                            onClick={() => onMetric(item)}
                        >
                            {item.label}
                        </PresetButton>
                    ))}
                </div>
            </div>
            {timeline ? (
                <LineChart
                    history={timeline.map((point) => ({
                        date: point.date,
                        value: metric.value(point),
                    }))}
                    height={CHART_HEIGHT}
                    format={metric.format}
                    formatTick={metric.formatTick}
                    formatDate={isHourly ? hour : undefined}
                    label={`${metric.label} per ${period}`}
                    unit={metric.label}
                />
            ) : (
                <div style={{ height: CHART_HEIGHT }} className="flex">
                    <Placeholder className="h-full w-full" />
                </div>
            )}
        </div>
    );
}

/**
 * Fathom's report, its placeholders while it loads, or a line saying it didn't answer. The breakdowns show the total
 * the chart draws, on the rows with the most visitors.
 */
function TrafficValue({
    traffic,
    filters,
    isHourly,
    onFilter,
}: {
    traffic?: Traffic | null;
    filters: TrafficFilters;
    isHourly: boolean;
    onFilter: (filter: TrafficFilter, value: string) => void;
}) {
    const [metric, setMetric] = useState(METRICS[0]);

    if (traffic === null) {
        return (
            <div className="rounded-lg border px-4 py-2.5 text-sm">
                <Status isUp={false}>
                    unavailable
                    <span className="font-normal text-muted-foreground">
                        Fathom didn't answer
                    </span>
                </Status>
            </div>
        );
    }

    return (
        <div className="space-y-3">
            <Stats
                stats={METRICS.map((metric) => ({
                    label: metric.label,
                    value:
                        traffic && metric.format(metric.value(traffic.totals)),
                }))}
            />
            <TrafficChart
                timeline={traffic?.timeline}
                isHourly={isHourly}
                metric={metric}
                onMetric={setMetric}
            />
            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
                {BREAKDOWNS.map((breakdown) => (
                    <Breakdown
                        key={breakdown.key}
                        className={breakdown.className}
                        title={breakdown.title}
                        unit={metric.label}
                        empty={breakdown.empty}
                        expectedRows={breakdown.expected}
                        rows={traffic?.breakdowns[breakdown.key].map(
                            (row: TrafficRow) => ({
                                name: row.name,
                                count: metric.value(row),
                            }),
                        )}
                        max={metric.max}
                        format={metric.format}
                        formatName={
                            breakdown.key === 'countries'
                                ? countryName
                                : undefined
                        }
                        selected={filters[breakdown.filter]}
                        onSelect={(name) => onFilter(breakdown.filter, name)}
                    />
                ))}
            </div>
        </div>
    );
}

export default function Show({
    traffic,
    trafficRange,
    trafficFilters,
}: {
    traffic?: Traffic | null;
    trafficRange: TrafficRange;
    trafficFilters: TrafficFilters;
}) {
    const { picked, pick } = usePicked<Selection>();
    const [isLoading, setIsLoading] = useState(false);
    const current = picked ?? { range: trafficRange, filters: trafficFilters };

    const isHourly = trafficRange.from === trafficRange.to;

    const change = (next: Selection) => {
        const release = pick(next);

        router.get(
            TrafficController.show().url,
            { ...next.range, ...next.filters },
            {
                preserveState: true,
                preserveScroll: true,
                only: ['traffic', 'trafficRange', 'trafficFilters'],
                onStart: () => setIsLoading(true),
                onFinish: () => {
                    setIsLoading(false);
                    release();
                },
            },
        );
    };

    return (
        <>
            <Head title="Traffic" />

            <Panel
                title="Traffic"
                meta={
                    traffic === null ? (
                        'fathom'
                    ) : (
                        <>
                            {traffic ? (
                                formatCount(traffic.current)
                            ) : (
                                <Placeholder className="w-[2ch]" />
                            )}{' '}
                            on the site now
                        </>
                    )
                }
            >
                <div className="mb-3">
                    <RangePicker
                        range={current.range}
                        presets={TRAFFIC_PRESETS}
                        onSelect={(range) => change({ ...current, range })}
                    />
                </div>
                <FilterBar
                    filters={current.filters}
                    onChange={(filters) => change({ ...current, filters })}
                />
                <div
                    aria-busy={isLoading}
                    className={cn(
                        'transition-opacity motion-reduce:transition-none',
                        isLoading && 'opacity-60 delay-300',
                    )}
                >
                    <Deferred
                        data="traffic"
                        fallback={
                            <TrafficValue
                                filters={current.filters}
                                isHourly={isHourly}
                                onFilter={() => {}}
                            />
                        }
                    >
                        <TrafficValue
                            traffic={traffic}
                            filters={current.filters}
                            isHourly={isHourly}
                            onFilter={(filter, value) =>
                                change({
                                    ...current,
                                    filters:
                                        current.filters[filter] === value
                                            ? without(current.filters, filter)
                                            : {
                                                  ...current.filters,
                                                  [filter]: value,
                                              },
                                })
                            }
                        />
                    </Deferred>
                </div>
            </Panel>
        </>
    );
}

Show.layout = OperationsLayout;
