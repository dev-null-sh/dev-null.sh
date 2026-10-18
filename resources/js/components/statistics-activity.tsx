import { useId } from 'react';
import type { ActivityBucket } from '@/types';

const dateFormat = new Intl.DateTimeFormat('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    timeZone: 'UTC',
});

function formatDate(date: string): string {
    return dateFormat.format(new Date(`${date}T00:00:00Z`));
}

export function StatisticsActivity({
    activity,
}: {
    activity: ActivityBucket[];
}) {
    const chartId = useId();
    const captionId = useId();
    const total = activity.reduce((sum, bucket) => sum + bucket.count, 0);
    const highest = Math.max(0, ...activity.map((bucket) => bucket.count));
    const max = Math.max(1, highest);
    const first = activity[0];
    const last = activity.at(-1);

    return (
        <>
            <figure className="space-y-2">
                <svg
                    viewBox="0 0 540 144"
                    preserveAspectRatio="none"
                    className="h-36 w-full text-muted-foreground"
                    role="img"
                    aria-labelledby={`${chartId}-title`}
                    aria-describedby={`${chartId}-description`}
                >
                    <title id={`${chartId}-title`}>Daily public commits</title>
                    <desc id={`${chartId}-description`}>
                        {total.toLocaleString('en-US')}{' '}
                        {total === 1 ? 'public commit' : 'public commits'} over{' '}
                        {activity.length}{' '}
                        {activity.length === 1 ? 'day' : 'days'}. The highest
                        daily count is {highest.toLocaleString('en-US')}. Exact
                        counts are available in the daily activity data table
                        below.
                    </desc>
                    <g aria-hidden="true">
                        <line
                            x1="0"
                            x2="540"
                            y1="143"
                            y2="143"
                            className="stroke-border"
                        />
                        {activity.map((bucket, index) => {
                            const step = 540 / activity.length;
                            const height = (bucket.count / max) * 128;

                            return (
                                <rect
                                    key={bucket.date}
                                    x={index * step + 2}
                                    y={143 - height}
                                    width={Math.max(1, step - 4)}
                                    height={height}
                                    rx="2"
                                    fill="currentColor"
                                />
                            );
                        })}
                    </g>
                </svg>
                {first && last && (
                    <figcaption className="flex flex-wrap justify-between gap-x-3 font-mono text-xs text-muted-foreground">
                        <time dateTime={first.date}>
                            {formatDate(first.date)}
                        </time>
                        <time dateTime={last.date}>
                            {formatDate(last.date)}
                        </time>
                    </figcaption>
                )}
            </figure>

            {total === 0 && (
                <p className="text-sm text-muted-foreground">
                    No public commits in this period.
                </p>
            )}

            <details className="text-sm">
                <summary className="w-fit cursor-pointer rounded-sm font-medium text-muted-foreground hover:text-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring">
                    View daily activity data
                </summary>
                <div
                    role="region"
                    aria-labelledby={captionId}
                    tabIndex={0}
                    className="mt-3 max-h-64 overflow-auto rounded-lg border px-3 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                >
                    <table className="w-full table-fixed text-sm">
                        <caption
                            id={captionId}
                            className="py-2.5 text-left font-medium"
                        >
                            Daily public commits (UTC)
                        </caption>
                        <thead className="sticky top-0 bg-card text-muted-foreground">
                            <tr className="border-y">
                                <th
                                    scope="col"
                                    className="py-2.5 text-left font-normal"
                                >
                                    Date (UTC)
                                </th>
                                <th
                                    scope="col"
                                    className="py-2.5 text-right font-normal"
                                >
                                    Commits
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {activity.map((bucket) => (
                                <tr key={bucket.date}>
                                    <th
                                        scope="row"
                                        className="py-2.5 text-left font-mono text-xs font-normal text-muted-foreground"
                                    >
                                        <time dateTime={bucket.date}>
                                            {formatDate(bucket.date)}
                                        </time>
                                    </th>
                                    <td className="py-2.5 text-right font-mono text-[13px] tabular-nums">
                                        {bucket.count.toLocaleString('en-US')}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </details>
        </>
    );
}
