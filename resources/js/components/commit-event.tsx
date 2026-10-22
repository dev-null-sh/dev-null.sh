import { Link, usePage } from '@inertiajs/react';
import { Globe, MapPin } from 'lucide-react';
import EventController from '@/actions/App/Http/Controllers/EventController';
import { EventStateBadge } from '@/components/event-state-badge';
import { EventTile } from '@/components/event-tile';
import { useNow } from '@/hooks/use-now';
import { eventFormat, eventStateAt } from '@/lib/event';
import { formatEventTime, formatEventWhen } from '@/lib/event-dates';
import type { Commit, Event } from '@/types';

/**
 * The event a commit announces, opening its page. Until the commit is approved, the agenda is spelled out too, so
 * whoever reviews it reads every word the event will show.
 */
export function CommitEvent({
    commit,
    event,
}: {
    commit: Pick<Commit, 'status'>;
    event: Event;
}) {
    const { auth } = usePage().props;
    const now = useNow();
    const timeZone = auth.user?.timezone ?? undefined;
    const state = eventStateAt(event, now);
    const isOnlyOnline =
        event.location === null ||
        event.location.trim().toLowerCase() === 'online';
    const where =
        event.url === null
            ? (event.location ?? '')
            : isOnlyOnline
              ? 'Online'
              : `${event.location} · Online`;

    return (
        <div
            className="mt-3 overflow-hidden rounded-lg border"
            data-test="commit-event"
        >
            <Link
                href={EventController.show(event.id)}
                className="flex items-center gap-3 px-3 py-2.5 transition-colors hover:bg-accent"
            >
                <EventTile date={event.starts_at} timeZone={timeZone} />
                <span className="min-w-0 flex-1">
                    <span className="flex items-center gap-2">
                        <span
                            className="min-w-0 truncate text-[15px] font-semibold"
                            title={event.title}
                        >
                            {event.title}
                        </span>
                        <EventStateBadge state={state} size="small" />
                    </span>
                    <span className="mt-0.5 block truncate font-mono text-[13px] text-muted-foreground">
                        {formatEventWhen(event, timeZone)}
                    </span>
                    <span className="mt-0.5 flex min-w-0 items-center gap-1 text-[13px] text-muted-foreground">
                        {eventFormat(event) === 'Online' ? (
                            <Globe className="size-3.5 shrink-0" />
                        ) : (
                            <MapPin className="size-3.5 shrink-0" />
                        )}
                        <span className="truncate" title={where}>
                            {where}
                        </span>
                    </span>
                </span>
            </Link>
            {commit.status !== 'approved' &&
                event.agenda_items !== undefined &&
                event.agenda_items.length > 0 && (
                    <ol
                        className="space-y-1 border-t px-3 py-2 text-[13px]"
                        aria-label="Agenda"
                    >
                        {event.agenda_items.map((item) => (
                            <li key={item.id} className="flex gap-2">
                                <span className="shrink-0 font-mono text-muted-foreground tabular-nums">
                                    {formatEventTime(item.starts_at, timeZone)}
                                </span>
                                <span className="min-w-0 wrap-break-word">
                                    {item.label}
                                </span>
                            </li>
                        ))}
                    </ol>
                )}
        </div>
    );
}
