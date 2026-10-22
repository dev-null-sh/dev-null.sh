import type { Event, EventAgendaItem, EventState } from '@/types';

export const eventStateLabels: Record<EventState, string> = {
    upcoming: 'Upcoming',
    live: 'Happening now',
    ended: 'Ended',
    cancelled: 'Cancelled',
};

/**
 * The state as of now, since a page left open keeps the state it was served with.
 */
export function eventStateAt(event: Event, now: Date): EventState {
    if (event.cancelled_at !== null) {
        return 'cancelled';
    }

    if (now >= new Date(event.ends_at)) {
        return 'ended';
    }

    return now >= new Date(event.starts_at) ? 'live' : 'upcoming';
}

/**
 * How far along the agenda is, like a milestone: an item is done once the next one has started, and the last one once
 * the event has ended.
 */
export function agendaProgress(
    items: EventAgendaItem[],
    event: Pick<Event, 'ends_at'>,
    now: Date,
): { done: number; current: number | null } {
    let done = 0;
    let current: number | null = null;

    items.forEach((item, index) => {
        const next = items[index + 1]?.starts_at ?? event.ends_at;

        if (now >= new Date(next)) {
            done++;
        } else if (now >= new Date(item.starts_at)) {
            current = item.id;
        }
    });

    return { done, current };
}

/**
 * A place, a link, or both, as GitHub would label it.
 */
export function eventFormat(
    event: Pick<Event, 'location' | 'url'>,
): 'In person' | 'Online' | 'Hybrid' {
    if (event.location !== null && event.url !== null) {
        return 'Hybrid';
    }

    return event.url !== null ? 'Online' : 'In person';
}
