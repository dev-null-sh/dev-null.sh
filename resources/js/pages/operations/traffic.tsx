import { Deferred, Head, router } from '@inertiajs/react';
import { Breakdown } from '@/components/operations/breakdown';
import { PresetButton } from '@/components/operations/preset-button';
import { Section } from '@/components/operations/section';
import { Stats } from '@/components/operations/stats';
import { Status } from '@/components/operations/status';
import { Skeleton } from '@/components/ui/skeleton';
import AppLayout from '@/layouts/app-layout';
import OperationsLayout from '@/layouts/operations/layout';
import { formatCount } from '@/lib/numbers';
import { formatAge } from '@/lib/operations';
import type { Traffic, TrafficRange, TrafficRow } from '@/types';

const TRAFFIC_PRESETS = [
    { label: 'today', days: 1 },
    { label: '7d', days: 7 },
    { label: '30d', days: 30 },
    { label: '90d', days: 90 },
];

const BREAKDOWNS = [
    { key: 'pages', title: 'pages', empty: '(none)' },
    { key: 'referrers', title: 'referrers', empty: 'direct' },
    { key: 'countries', title: 'countries', empty: 'unknown' },
    { key: 'devices', title: 'devices', empty: 'unknown' },
    { key: 'browsers', title: 'browsers', empty: 'unknown' },
] as const;

const regions = new Intl.DisplayNames(['en'], { type: 'region' });

function utcDate(daysAgo = 0): string {
    return new Date(Date.now() - daysAgo * 86_400_000)
        .toISOString()
        .slice(0, 10);
}

function countryName(code: string): string {
    try {
        return regions.of(code) ?? code;
    } catch {
        return code;
    }
}

function TrafficRangePicker({ range }: { range: TrafficRange }) {
    const today = utcDate();

    const select = (next: TrafficRange) =>
        router.reload({
            data: next,
            only: ['traffic', 'trafficRange'],
        });

    return (
        <div className="mb-3 flex flex-wrap items-center gap-2 font-mono text-[13px]">
            {TRAFFIC_PRESETS.map((preset) => {
                const from = utcDate(preset.days - 1);

                return (
                    <PresetButton
                        key={preset.label}
                        isActive={range.from === from && range.to === today}
                        onClick={() => select({ from, to: today })}
                    >
                        {preset.label}
                    </PresetButton>
                );
            })}
            <span className="flex items-center gap-1.5 sm:ml-auto">
                <input
                    type="date"
                    aria-label="From"
                    value={range.from}
                    max={range.to}
                    onChange={(event) =>
                        event.target.value &&
                        select({ ...range, from: event.target.value })
                    }
                    className="h-7 rounded-md border bg-transparent px-2 text-[13px]"
                />
                <span className="text-muted-foreground">→</span>
                <input
                    type="date"
                    aria-label="To"
                    value={range.to}
                    min={range.from}
                    max={today}
                    onChange={(event) =>
                        event.target.value &&
                        select({ ...range, to: event.target.value })
                    }
                    className="h-7 rounded-md border bg-transparent px-2 text-[13px]"
                />
            </span>
        </div>
    );
}

function TrafficValue({ traffic }: { traffic: Traffic | null | undefined }) {
    if (!traffic) {
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
                stats={[
                    {
                        label: 'visitors',
                        value: formatCount(traffic.totals.visitors),
                    },
                    {
                        label: 'pageviews',
                        value: formatCount(traffic.totals.pageviews),
                    },
                    {
                        label: 'avg time',
                        value: formatAge(traffic.totals.avg_duration),
                    },
                    {
                        label: 'bounce rate',
                        value: `${Math.round(traffic.totals.bounce_rate * 100)}%`,
                    },
                ]}
            />
            <div className="grid gap-3 sm:grid-cols-2">
                {BREAKDOWNS.map((breakdown) => (
                    <Breakdown
                        key={breakdown.key}
                        title={breakdown.title}
                        unit="visitors"
                        empty={breakdown.empty}
                        rows={traffic.breakdowns[breakdown.key].map(
                            (row: TrafficRow) => ({
                                name: row.name,
                                count: row.visitors,
                            }),
                        )}
                        formatName={
                            breakdown.key === 'countries'
                                ? countryName
                                : undefined
                        }
                    />
                ))}
            </div>
        </div>
    );
}

function TrafficSkeleton() {
    return (
        <div
            aria-hidden
            className="animate-in space-y-3 delay-300 duration-300 fill-mode-both fade-in"
        >
            <Skeleton className="h-16 w-full rounded-lg" />
            <div className="grid gap-3 sm:grid-cols-2">
                {BREAKDOWNS.map((breakdown) => (
                    <Skeleton
                        key={breakdown.key}
                        className="h-48 w-full rounded-lg"
                    />
                ))}
            </div>
        </div>
    );
}

export default function Show({
    traffic,
    trafficRange,
}: {
    traffic?: Traffic | null;
    trafficRange: TrafficRange;
}) {
    return (
        <>
            <Head title="Site traffic" />

            <Section
                title="Traffic"
                meta={
                    traffic
                        ? `${formatCount(traffic.current)} on the site now`
                        : 'fathom'
                }
            >
                <TrafficRangePicker range={trafficRange} />
                <Deferred data="traffic" fallback={<TrafficSkeleton />}>
                    <TrafficValue traffic={traffic} />
                </Deferred>
            </Section>
        </>
    );
}

Show.layout = [AppLayout, OperationsLayout];
