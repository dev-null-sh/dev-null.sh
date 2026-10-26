import { Deferred, Head, router } from '@inertiajs/react';
import { Breakdown } from '@/components/operations/breakdown';
import { Placeholder } from '@/components/operations/placeholder';
import { RangePicker } from '@/components/operations/range-picker';
import { Panel } from '@/components/operations/section';
import { Stats } from '@/components/operations/stats';
import { Status } from '@/components/operations/status';
import { usePicked } from '@/hooks/use-picked';
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
    {
        key: 'pages',
        title: 'pages',
        empty: '(none)',
        expected: 10,
        className: 'xl:col-span-3',
    },
    {
        key: 'referrers',
        title: 'referrers',
        empty: 'direct',
        expected: 10,
        className: 'xl:col-span-3',
    },
    {
        key: 'countries',
        title: 'countries',
        empty: 'unknown',
        expected: 10,
        className: 'xl:col-span-2',
    },
    {
        key: 'devices',
        title: 'devices',
        empty: 'unknown',
        expected: 3,
        className: 'xl:col-span-2',
    },
    {
        key: 'browsers',
        title: 'browsers',
        empty: 'unknown',
        expected: 10,
        className: 'sm:col-span-2 xl:col-span-2',
    },
] as const;

const regions = new Intl.DisplayNames(['en'], { type: 'region' });

function countryName(code: string): string {
    try {
        return regions.of(code) ?? code;
    } catch {
        return code;
    }
}

function TrafficRangePicker({ range }: { range: TrafficRange }) {
    const { picked, pick } = usePicked<TrafficRange>();

    return (
        <div className="mb-3">
            <RangePicker
                range={picked ?? range}
                presets={TRAFFIC_PRESETS}
                onSelect={(next) =>
                    router.reload({
                        data: next,
                        only: ['traffic', 'trafficRange'],
                        onFinish: pick(next),
                    })
                }
            />
        </div>
    );
}

/**
 * Fathom's report, its placeholders while it loads, or a line saying it didn't answer.
 */
function TrafficValue({ traffic }: { traffic?: Traffic | null }) {
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
                stats={[
                    {
                        label: 'visitors',
                        value: traffic && formatCount(traffic.totals.visitors),
                    },
                    {
                        label: 'pageviews',
                        value: traffic && formatCount(traffic.totals.pageviews),
                    },
                    {
                        label: 'avg time',
                        value:
                            traffic && formatAge(traffic.totals.avg_duration),
                    },
                    {
                        label: 'bounce rate',
                        value:
                            traffic &&
                            `${Math.round(traffic.totals.bounce_rate * 100)}%`,
                    },
                ]}
            />
            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
                {BREAKDOWNS.map((breakdown) => (
                    <Breakdown
                        key={breakdown.key}
                        className={breakdown.className}
                        title={breakdown.title}
                        unit="visitors"
                        empty={breakdown.empty}
                        expectedRows={breakdown.expected}
                        rows={traffic?.breakdowns[breakdown.key].map(
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

export default function Show({
    traffic,
    trafficRange,
}: {
    traffic?: Traffic | null;
    trafficRange: TrafficRange;
}) {
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
                <TrafficRangePicker range={trafficRange} />
                <Deferred data="traffic" fallback={<TrafficValue />}>
                    <TrafficValue traffic={traffic} />
                </Deferred>
            </Panel>
        </>
    );
}

Show.layout = OperationsLayout;
