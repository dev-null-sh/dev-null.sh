import { Head, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import OperationsEventController from '@/actions/App/Http/Controllers/OperationsEventController';
import { EmptyFeed } from '@/components/commit-feed';
import { ColumnChart } from '@/components/operations/charts';
import type { ColumnSeries } from '@/components/operations/charts';
import { EventsLog, LEVEL_DOTS } from '@/components/operations/events-log';
import { PresetButton } from '@/components/operations/preset-button';
import { Panel } from '@/components/operations/section';
import { useDateTime } from '@/hooks/use-date-time';
import OperationsLayout from '@/layouts/operations/layout';
import { exactCountTitle, formatCount } from '@/lib/numbers';
import { cn } from '@/lib/utils';
import type {
    OperationsEvent,
    OperationsEventBucket,
    OperationsEventFilters,
    OperationsEventLevel,
    OperationsEventSource,
    OperationsEventWindow,
} from '@/types';

const DEFAULT_WINDOW: OperationsEventWindow = '24h';

const EVENT_LIMIT = 300;

const SEARCH_DELAY = 300;

const WINDOWS: { window: OperationsEventWindow; phrase: string }[] = [
    { window: '1h', phrase: 'the last hour' },
    { window: '24h', phrase: 'the last 24 hours' },
    { window: '7d', phrase: 'the last 7 days' },
    { window: '30d', phrase: 'the last 30 days' },
];

const LEVELS: OperationsEventLevel[] = ['info', 'notice', 'warning', 'error'];

const SOURCES: OperationsEventSource[] = [
    'signups',
    'jobs',
    'reports',
    'moderation',
    'bans',
    'timeouts',
    'features',
];

const SERIES: ColumnSeries<OperationsEventLevel>[] = [
    {
        key: 'error',
        label: 'error',
        className: 'fill-destructive bg-destructive',
    },
    { key: 'warning', label: 'warning', className: 'fill-warning bg-warning' },
    {
        key: 'notice',
        label: 'notice',
        className: 'fill-muted-foreground bg-muted-foreground',
    },
    {
        key: 'info',
        label: 'info',
        className: 'fill-muted-foreground/40 bg-muted-foreground/40',
    },
];

/**
 * The chart's key, with how many events of each level it holds.
 */
function LevelTotals({
    totals,
}: {
    totals: Record<OperationsEventLevel, number>;
}) {
    return (
        <ul className="flex flex-wrap gap-x-4 gap-y-1 font-mono text-xs text-muted-foreground">
            {SERIES.map((item) => (
                <li key={item.key} className="flex items-center gap-1.5">
                    <span
                        aria-hidden
                        className={cn('size-2.5 rounded-xs', item.className)}
                    />
                    {item.label}
                    <span
                        className={cn(
                            'min-w-[5ch] tabular-nums',
                            totals[item.key] > 0 &&
                                (item.key === 'error' ||
                                    item.key === 'warning') &&
                                'text-foreground',
                        )}
                        title={exactCountTitle(totals[item.key])}
                    >
                        {formatCount(totals[item.key])}
                    </span>
                </li>
            ))}
        </ul>
    );
}

/**
 * The filters as the query string carries them: defaults and empty values are left out, so an unfiltered log keeps
 * its plain URL.
 */
function toQuery(filters: OperationsEventFilters): Record<string, string> {
    const search = filters.search.trim();

    return {
        ...(filters.window !== DEFAULT_WINDOW && { window: filters.window }),
        ...(filters.level !== null && { level: filters.level }),
        ...(filters.source !== null && { source: filters.source }),
        ...(search !== '' && { search }),
    };
}

/**
 * The command a shell would have run for these filters, for the empty log.
 */
function grepCommand(filters: OperationsEventFilters): string {
    const search = filters.search.trim();

    return [
        `$ cat /var/log/devnull/${filters.source ?? '*'}.log`,
        filters.level === null ? null : `grep -w ${filters.level}`,
        search === '' ? null : `grep -i '${search}'`,
    ]
        .filter((part) => part !== null)
        .join(' | ');
}

function FilterBar({
    filters,
    counts,
    onChange,
}: {
    filters: OperationsEventFilters;
    counts: Record<OperationsEventSource, number>;
    onChange: (filters: OperationsEventFilters, delay?: number) => void;
}) {
    return (
        <div className="flex flex-col gap-2 rounded-lg border bg-card px-3 py-2">
            <div className="flex flex-wrap items-center gap-x-3 gap-y-2">
                <div
                    role="group"
                    aria-label="Window"
                    className="flex items-center gap-1"
                >
                    {WINDOWS.map((item) => (
                        <PresetButton
                            key={item.window}
                            isActive={filters.window === item.window}
                            onClick={() =>
                                onChange({ ...filters, window: item.window })
                            }
                        >
                            {item.window}
                        </PresetButton>
                    ))}
                </div>
                <span
                    aria-hidden
                    className="hidden h-4 w-px bg-border sm:block"
                />
                <div
                    role="group"
                    aria-label="Level"
                    className="flex flex-wrap items-center gap-1"
                >
                    {LEVELS.map((level) => (
                        <PresetButton
                            key={level}
                            isActive={filters.level === level}
                            onClick={() =>
                                onChange({
                                    ...filters,
                                    level:
                                        filters.level === level ? null : level,
                                })
                            }
                        >
                            <span
                                aria-hidden
                                className={cn(
                                    'size-1.5 rounded-full',
                                    LEVEL_DOTS[level],
                                )}
                            />
                            {level}
                        </PresetButton>
                    ))}
                </div>
                <label className="relative flex w-full items-center sm:ml-auto sm:w-64">
                    <span className="sr-only">Search messages and details</span>
                    <Search
                        aria-hidden
                        className="pointer-events-none absolute left-2 size-3.5 text-muted-foreground"
                    />
                    <input
                        type="search"
                        value={filters.search}
                        onChange={(event) =>
                            onChange(
                                { ...filters, search: event.target.value },
                                SEARCH_DELAY,
                            )
                        }
                        placeholder="grep"
                        autoComplete="off"
                        spellCheck={false}
                        className="h-9 w-full min-w-0 rounded-md border border-input bg-transparent pr-2 pl-7 font-mono text-base outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:h-7 md:text-[13px]"
                    />
                </label>
            </div>
            <div
                role="group"
                aria-label="Source"
                className="flex flex-wrap items-center gap-1"
            >
                {SOURCES.map((source) => (
                    <PresetButton
                        key={source}
                        isActive={filters.source === source}
                        onClick={() =>
                            onChange({
                                ...filters,
                                source:
                                    filters.source === source ? null : source,
                            })
                        }
                    >
                        {source}
                        <span
                            className={cn(
                                'min-w-[3ch] text-left tabular-nums',
                                filters.source !== source &&
                                    'text-muted-foreground',
                            )}
                            title={exactCountTitle(counts[source])}
                        >
                            {formatCount(counts[source])}
                        </span>
                    </PresetButton>
                ))}
            </div>
        </div>
    );
}

export default function Index({
    events,
    filters,
    counts,
    histogram,
}: {
    events: OperationsEvent[];
    filters: OperationsEventFilters;
    counts: Record<OperationsEventSource, number>;
    histogram: OperationsEventBucket[];
}) {
    const { formatDate, formatTime } = useDateTime();
    const [query, setQuery] = useState(filters);
    const [isLoading, setIsLoading] = useState(false);
    const timer = useRef<number | undefined>(undefined);
    const releasePolls = useRef(() => {});
    const phrase =
        WINDOWS.find((item) => item.window === filters.window)?.phrase ??
        filters.window;
    const totals = Object.fromEntries(
        LEVELS.map((level) => [
            level,
            histogram.reduce((sum, bucket) => sum + bucket[level], 0),
        ]),
    ) as Record<OperationsEventLevel, number>;
    const isFiltered =
        filters.level !== null ||
        filters.source !== null ||
        filters.search.trim() !== '';

    useEffect(
        () => () => {
            window.clearTimeout(timer.current);
            releasePolls.current();
        },
        [],
    );

    /**
     * Polls keep the URL they started with, so one landing after the new filters would bring the old results back:
     * they are cancelled, and held off until the filtered results arrive.
     */
    const change = (next: OperationsEventFilters, delay = 0) => {
        setQuery(next);
        window.clearTimeout(timer.current);
        timer.current = window.setTimeout(() => {
            releasePolls.current();
            router.cancelAll({ sync: false, prefetch: false });

            const release = router.on(
                'before',
                (event) => !event.detail.visit.async,
            );

            releasePolls.current = release;

            router.get(OperationsEventController.index().url, toQuery(next), {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['events', 'counts', 'histogram', 'filters'],
                onStart: () => setIsLoading(true),
                onFinish: () => {
                    release();
                    setIsLoading(false);
                },
            });
        }, delay);
    };

    return (
        <>
            <Head title="Events" />

            <div className="flex flex-col gap-4 [overflow-anchor:none]">
                <FilterBar filters={query} counts={counts} onChange={change} />

                <div
                    aria-busy={isLoading}
                    className={cn(
                        'flex flex-col gap-4 transition-opacity motion-reduce:transition-none',
                        isLoading && 'opacity-60 delay-300',
                    )}
                >
                    <Panel title="Volume" meta={`in ${phrase}`}>
                        <ColumnChart
                            buckets={histogram}
                            series={SERIES}
                            height={140}
                            label={`Events in ${phrase} by level`}
                            formatBucket={(at) =>
                                filters.window === '30d'
                                    ? formatDate(at, {
                                          timeZone: 'UTC',
                                          year: false,
                                      })
                                    : formatTime(
                                          at,
                                          undefined,
                                          filters.window === '7d'
                                              ? { weekday: 'short' }
                                              : {},
                                      )
                            }
                            unit="events"
                        />
                        <div className="mt-3">
                            <LevelTotals totals={totals} />
                        </div>
                    </Panel>

                    <Panel
                        title="Log"
                        meta={
                            <span className="tabular-nums">
                                {events.length >= EVENT_LIMIT
                                    ? `${EVENT_LIMIT} newest shown`
                                    : `${formatCount(events.length)} ${events.length === 1 ? 'event' : 'events'}`}
                            </span>
                        }
                    >
                        {events.length === 0 ? (
                            <div key="empty" className="py-8 text-center">
                                <EmptyFeed command={grepCommand(filters)}>
                                    <p>
                                        {isFiltered
                                            ? `Nothing matches in ${phrase}.`
                                            : `Nothing was logged in ${phrase}.`}
                                    </p>
                                    {isFiltered && (
                                        <button
                                            type="button"
                                            className="mt-1 rounded-sm text-primary underline-offset-4 outline-none hover:underline focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                            onClick={() =>
                                                change({
                                                    ...query,
                                                    level: null,
                                                    source: null,
                                                    search: '',
                                                })
                                            }
                                        >
                                            Clear the filters
                                        </button>
                                    )}
                                </EmptyFeed>
                            </div>
                        ) : (
                            <div key={JSON.stringify(filters)} className="-m-4">
                                <EventsLog events={events} />
                                {events.length >= EVENT_LIMIT && (
                                    <p className="border-t px-4 py-2.5 text-sm text-muted-foreground">
                                        Showing the{' '}
                                        <span className="font-mono text-[13px]">
                                            {EVENT_LIMIT}
                                        </span>{' '}
                                        newest. Narrow the window or the filters
                                        to see older events.
                                    </p>
                                )}
                            </div>
                        )}
                    </Panel>
                </div>
            </div>
        </>
    );
}

Index.layout = OperationsLayout;
