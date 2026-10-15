import { CalendarDays, MapPin, Video } from 'lucide-react';
import {
    eventDateParts,
    formatEventDay,
    formatEventTime,
    formatEventTimeRange,
    isToday,
    relativeEventLabel,
} from '@/lib/event';
import { cn } from '@/lib/utils';
import type { CommitEvent } from '@/types';

export function EventTicket({ event }: { event: CommitEvent }) {
    const date = eventDateParts(event.starts_at);
    const today = isToday(event.starts_at);
    const isCancelled = event.state === 'cancelled';

    if (event.state === 'ended') {
        return (
            <div
                className="mt-3 flex items-center gap-2.5 rounded-lg border px-3 py-2 text-sm text-muted-foreground"
                data-test="event-ticket"
            >
                <CalendarDays className="size-4 shrink-0" />
                <span className="truncate">
                    {event.title} · {formatEventDay(event.starts_at)}
                </span>
                <span className="ml-auto shrink-0 font-mono text-[13px]">
                    exit 0 · {event.attendees_count} went
                </span>
            </div>
        );
    }

    return (
        <div
            className="mt-3 flex overflow-hidden rounded-lg border"
            data-test="event-ticket"
        >
            <div className="flex w-[76px] shrink-0 flex-col items-center justify-center border-r py-2.5">
                <span className="font-mono text-xs text-muted-foreground">
                    {today ? 'today' : date.month}
                </span>
                <span
                    className={cn(
                        'leading-tight font-semibold tracking-tight',
                        today ? 'text-xl' : 'text-[26px]',
                    )}
                >
                    {today ? formatEventTime(event.starts_at) : date.day}
                </span>
                <span className="font-mono text-xs text-muted-foreground">
                    {today ? relativeEventLabel(event.starts_at) : date.weekday}
                </span>
            </div>

            <div className="min-w-0 flex-1 px-3.5 py-2.5">
                <p
                    className={cn(
                        'text-[15px] leading-snug font-semibold',
                        isCancelled && 'text-muted-foreground line-through',
                    )}
                >
                    {event.title}
                </p>
                <p className="mt-1 font-mono text-[13px] text-muted-foreground">
                    {isCancelled
                        ? 'cancelled'
                        : `${formatEventTimeRange(event.starts_at, event.ends_at)}${event.updated ? ' · updated' : ''}`}
                </p>
                <p className="flex items-center gap-1.5 text-sm text-muted-foreground">
                    {event.format === 'online' ? (
                        <Video className="size-3.5 shrink-0" />
                    ) : (
                        <MapPin className="size-3.5 shrink-0" />
                    )}
                    <span className="truncate">
                        {event.format === 'online'
                            ? `Online · ${event.platform_label}`
                            : event.venue}
                        {event.format === 'hybrid' && ' · and online'}
                    </span>
                </p>
            </div>

            {event.cover && (
                <img
                    src={event.cover.url}
                    alt=""
                    loading="lazy"
                    className="m-2.5 ml-0 hidden w-[92px] shrink-0 rounded-md bg-muted object-cover sm:block"
                />
            )}
        </div>
    );
}
