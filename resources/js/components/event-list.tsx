import { InfiniteScroll, Link, usePage } from '@inertiajs/react';
import {
    CalendarCheck2,
    CalendarClock,
    CalendarX2,
    Radio,
    Users,
} from 'lucide-react';
import type { ReactNode } from 'react';
import EventController from '@/actions/App/Http/Controllers/EventController';
import { Spinner } from '@/components/ui/spinner';
import { useNow } from '@/hooks/use-now';
import {
    eventFormat,
    eventPlace,
    eventStateAt,
    eventStateLabels,
} from '@/lib/event';
import { formatEventWhen } from '@/lib/event-dates';
import { formatCount, formatExactCount } from '@/lib/numbers';
import { cn, shortHash } from '@/lib/utils';
import type { CursorPaginated, EventState, EventWithHost } from '@/types';

const stateIcons = {
    upcoming: CalendarClock,
    live: Radio,
    ended: CalendarCheck2,
    cancelled: CalendarX2,
} satisfies Record<EventState, unknown>;

const stateColors: Record<EventState, string> = {
    upcoming: 'text-emerald-600 dark:text-emerald-400',
    live: 'text-red-600 dark:text-red-400',
    ended: 'text-violet-600 dark:text-violet-400',
    cancelled: 'text-muted-foreground',
};

/**
 * Events listed the way GitHub lists issues: a state icon, the title, and one line of metadata.
 */
export function EventList({
    events,
    data,
    empty,
}: {
    events: CursorPaginated<EventWithHost>;
    data: string;
    empty: ReactNode;
}) {
    const { auth } = usePage().props;
    const now = useNow();
    const timeZone = auth.user?.timezone ?? undefined;

    if (events.data.length === 0) {
        return <div className="px-6 py-16 text-center sm:px-12">{empty}</div>;
    }

    return (
        <InfiniteScroll
            data={data}
            onlyNext
            preserveUrl
            loading={
                <div className="flex justify-center py-6">
                    <Spinner className="text-muted-foreground" />
                </div>
            }
        >
            <ul className="divide-y border-b" data-test="event-list">
                {events.data.map((event) => {
                    const state = eventStateAt(event, now);
                    const Icon = stateIcons[state];

                    return (
                        <li
                            key={event.id}
                            className="relative flex items-start gap-3 px-4 py-3 hover:bg-muted/50 sm:px-6"
                        >
                            <Icon
                                className={cn(
                                    'mt-1 size-4 shrink-0',
                                    stateColors[state],
                                )}
                                aria-label={eventStateLabels[state]}
                            />
                            <div className="min-w-0 flex-1">
                                <p className="flex min-w-0 items-center gap-2">
                                    <Link
                                        href={EventController.show(event.id)}
                                        className="min-w-0 truncate font-semibold after:absolute after:inset-0 hover:text-primary"
                                        title={event.title}
                                    >
                                        {event.title}
                                    </Link>
                                    <span className="shrink-0 rounded-md border px-1.5 py-0.5 text-xs font-medium text-muted-foreground">
                                        {eventFormat(event)}
                                    </span>
                                </p>
                                <p className="mt-0.5 truncate font-mono text-[13px] text-muted-foreground">
                                    #{shortHash(event.id)} ·{' '}
                                    {formatEventWhen(event, timeZone)}
                                </p>
                                <p className="mt-0.5 truncate text-[13px] text-muted-foreground">
                                    {eventPlace(event) !== null &&
                                        `${eventPlace(event)} · `}
                                    hosted by @{event.host.handle}
                                </p>
                            </div>
                            <span
                                className="mt-0.5 flex shrink-0 items-center gap-1 font-mono text-[13px] text-muted-foreground tabular-nums"
                                title={`${formatExactCount(event.going_count)} going`}
                            >
                                <Users className="size-3.5" />
                                {formatCount(event.going_count)}
                            </span>
                        </li>
                    );
                })}
            </ul>
        </InfiniteScroll>
    );
}
