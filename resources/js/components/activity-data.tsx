import { useId, useState } from 'react';
import type { Activity } from '@/types';

const dateFormat = new Intl.DateTimeFormat('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    timeZone: 'UTC',
});

const timeFormat = new Intl.DateTimeFormat('en-GB', {
    hour: '2-digit',
    minute: '2-digit',
    hourCycle: 'h23',
    timeZone: 'UTC',
});

export function ActivityData({ activity }: { activity: Activity }) {
    const [isOpen, setIsOpen] = useState(false);

    return (
        <details
            className="mt-3"
            onToggle={(event) => setIsOpen(event.currentTarget.open)}
        >
            <summary className="w-fit cursor-pointer rounded-sm text-sm font-medium text-muted-foreground hover:text-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring">
                View activity data
            </summary>
            {isOpen && <ActivityDataTable activity={activity} />}
        </details>
    );
}

export function ActivityDataTable({ activity }: { activity: Activity }) {
    const captionId = useId();
    const hourly = activity.scale === 'day';

    return (
        <div
            role="region"
            aria-labelledby={captionId}
            tabIndex={0}
            className="mt-3 max-h-64 overflow-auto rounded-md border focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
        >
            <table className="w-full text-sm">
                <caption
                    id={captionId}
                    className="px-3 py-2 text-left font-medium text-foreground"
                >
                    Commit activity by {hourly ? 'hour' : 'date'} (UTC)
                </caption>
                <thead className="sticky top-0 bg-card text-foreground">
                    <tr className="border-y">
                        <th
                            scope="col"
                            className="px-3 py-2 text-left font-medium"
                        >
                            {hourly ? 'Hour starting (UTC)' : 'Date (UTC)'}
                        </th>
                        <th
                            scope="col"
                            className="px-3 py-2 text-right font-medium"
                        >
                            Commits
                        </th>
                    </tr>
                </thead>
                <tbody className="divide-y">
                    {activity.buckets.map((bucket) => {
                        const date = new Date(
                            hourly ? bucket.date : `${bucket.date}T00:00:00Z`,
                        );
                        const label = dateFormat.format(date);

                        return (
                            <tr key={bucket.date}>
                                <th
                                    scope="row"
                                    className="px-3 py-2 text-left font-normal text-muted-foreground"
                                >
                                    <time dateTime={bucket.date}>
                                        {hourly
                                            ? `${label}, ${timeFormat.format(date)}`
                                            : label}
                                    </time>
                                </th>
                                <td className="px-3 py-2 text-right font-mono tabular-nums">
                                    {bucket.count}
                                </td>
                            </tr>
                        );
                    })}
                    {activity.buckets.length === 0 && (
                        <tr>
                            <td
                                colSpan={2}
                                className="px-3 py-2 text-muted-foreground"
                            >
                                No activity data available.
                            </td>
                        </tr>
                    )}
                </tbody>
            </table>
        </div>
    );
}
