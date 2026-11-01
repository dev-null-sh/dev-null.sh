import { ColumnChart } from '@/components/operations/charts';
import { classBasename } from '@/components/operations/health-failed-jobs';
import { Delta } from '@/components/operations/overview-delta';
import { Panel } from '@/components/operations/section';
import { useDateTime } from '@/hooks/use-date-time';
import { useNow } from '@/hooks/use-now';
import { formatCount } from '@/lib/numbers';
import type { FailureTrend } from '@/types';

const SERIES = [
    {
        key: 'count' as const,
        label: 'failed',
        className: 'fill-destructive bg-destructive',
    },
];

/**
 * How many jobs failed in the last day against the day before, hour by hour, and which jobs failed most.
 */
export function FailuresPanel({
    failureTrend,
    className,
}: {
    failureTrend: FailureTrend;
    className?: string;
}) {
    const { formatTime, formatFullDateTime, formatRelativeTime } =
        useDateTime();
    const now = useNow();
    const top = Math.max(0, ...failureTrend.by_job.map((job) => job.count));

    return (
        <Panel title="Failures" meta="last 24h" className={className}>
            <div className="mb-3 flex flex-wrap items-baseline gap-x-3 gap-y-1">
                <p className="font-mono text-xl leading-7 font-medium tabular-nums">
                    {formatCount(failureTrend.last_24h)}
                    <span className="ml-2 text-[13px] font-normal text-muted-foreground">
                        {failureTrend.last_24h === 1 ? 'failure' : 'failures'}
                    </span>
                </p>
                <Delta
                    current={failureTrend.last_24h}
                    previous={failureTrend.previous_24h}
                    isUpGood={false}
                    label={`vs ${formatCount(failureTrend.previous_24h)} the 24h before`}
                />
            </div>

            <ColumnChart
                buckets={failureTrend.hours}
                series={SERIES}
                height={140}
                label="Failed jobs per hour, the last 24 hours"
                formatBucket={formatTime}
                unit="jobs"
            />

            <table className="-mx-4 mt-4 -mb-4 w-[calc(100%+2rem)] table-fixed border-t text-sm">
                <thead>
                    <tr className="font-mono text-[13px] text-muted-foreground">
                        <th
                            scope="col"
                            className="py-2.5 pl-4 text-left font-normal"
                        >
                            job
                        </th>
                        <th
                            scope="col"
                            className="w-20 py-2.5 text-right font-normal"
                        >
                            failed
                        </th>
                        <th
                            scope="col"
                            className="w-20 py-2.5 pr-4 text-right font-normal sm:w-24"
                        >
                            last
                        </th>
                    </tr>
                </thead>
                <tbody className="divide-y border-t">
                    {failureTrend.by_job.length === 0 && (
                        <tr>
                            <td
                                colSpan={3}
                                className="px-4 py-2.5 font-mono text-[13px] text-muted-foreground"
                            >
                                nothing failed in 24h
                            </td>
                        </tr>
                    )}
                    {failureTrend.by_job.map((job) => (
                        <tr key={job.name}>
                            <th
                                scope="row"
                                className="relative py-2.5 pr-3 pl-4 text-left font-mono text-[13px] font-normal"
                            >
                                <span
                                    aria-hidden
                                    className="absolute inset-y-1.5 left-2 rounded bg-muted"
                                    style={{
                                        width: `calc((100% - 1rem) * ${top > 0 ? job.count / top : 0} + 0.5rem)`,
                                    }}
                                />
                                <span
                                    className="relative block truncate"
                                    title={job.name}
                                >
                                    {classBasename(job.name)}
                                </span>
                            </th>
                            <td className="py-2.5 text-right font-mono text-[13px] font-medium tabular-nums">
                                {formatCount(job.count)}
                            </td>
                            <td className="py-2.5 pr-4 text-right font-mono text-[13px] text-muted-foreground tabular-nums">
                                <time
                                    dateTime={job.last_failed_at}
                                    title={formatFullDateTime(
                                        job.last_failed_at,
                                    )}
                                >
                                    {formatRelativeTime(
                                        job.last_failed_at,
                                        now,
                                    )}
                                </time>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </Panel>
    );
}
