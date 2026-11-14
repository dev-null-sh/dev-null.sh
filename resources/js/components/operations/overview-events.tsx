import { Link } from '@inertiajs/react';
import { Circle, CircleAlert, CircleX, Info } from 'lucide-react';
import OperationsEventController from '@/actions/App/Http/Controllers/OperationsEventController';
import { EmptyFeed } from '@/components/commit-feed';
import { Panel } from '@/components/operations/section';
import { useDateTime } from '@/hooks/use-date-time';
import { useListSlide } from '@/hooks/use-list-slide';
import { useNow } from '@/hooks/use-now';
import { cn } from '@/lib/utils';
import type { OperationsEvent, OperationsEventLevel } from '@/types';

const LEVELS: Record<
    OperationsEventLevel,
    { icon: typeof Circle; className: string }
> = {
    error: { icon: CircleX, className: 'text-destructive' },
    warning: { icon: CircleAlert, className: 'text-warning' },
    notice: { icon: Info, className: 'text-muted-foreground' },
    info: { icon: Circle, className: 'text-rail' },
};

function EventRow({ event, now }: { event: OperationsEvent; now: Date }) {
    const { formatFullDateTime, formatRelativeTime } = useDateTime();
    const level = LEVELS[event.level];
    const meta = [
        event.source,
        event.actor === null ? null : `@${event.actor}`,
        event.detail,
    ]
        .filter((part) => part !== null)
        .join(' · ');
    const content = (
        <>
            <level.icon
                aria-label={event.level}
                className={cn('mt-0.5 size-4 shrink-0', level.className)}
            />
            <span className="min-w-0 flex-1">
                <span className="block truncate text-sm" title={event.message}>
                    {event.message}
                </span>
                <span
                    className="block truncate font-mono text-[13px] text-muted-foreground"
                    title={meta}
                >
                    {meta}
                </span>
            </span>
            <time
                dateTime={event.at}
                title={formatFullDateTime(event.at)}
                className="w-12 shrink-0 text-right font-mono text-[13px] text-muted-foreground tabular-nums"
            >
                {formatRelativeTime(event.at, now)}
            </time>
        </>
    );
    const className = 'flex items-start gap-3 px-4 py-2';

    return (
        <li>
            {event.href ? (
                <Link
                    href={event.href}
                    className={cn(
                        className,
                        'transition-colors hover:bg-accent focus-visible:bg-accent focus-visible:-outline-offset-2',
                    )}
                >
                    {content}
                </Link>
            ) : (
                <div className={className}>{content}</div>
            )}
        </li>
    );
}

/**
 * The newest events as a compact log, each with its level, what happened, who did it and how long ago.
 */
export function OverviewEvents({ events }: { events: OperationsEvent[] }) {
    const now = useNow();
    const list = useListSlide<HTMLOListElement>(
        events.map((event) => event.id),
    );

    return (
        <Panel
            title="Recent events"
            meta="last 24h"
            actions={
                <Link
                    href={OperationsEventController.index()}
                    className="-mr-2 inline-flex h-7 items-center rounded-md px-2 text-sm text-primary underline-offset-4 hover:underline"
                >
                    All events
                </Link>
            }
        >
            {events.length === 0 ? (
                <div className="py-6 text-center">
                    <EmptyFeed command="$ tail operations.log">
                        Nothing happened in the last 24 hours.
                    </EmptyFeed>
                </div>
            ) : (
                <ol ref={list} className="relative -mx-4 -my-4 divide-y">
                    {events.map((event) => (
                        <EventRow key={event.id} event={event} now={now} />
                    ))}
                </ol>
            )}
        </Panel>
    );
}
