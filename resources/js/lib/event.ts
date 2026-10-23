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
 * The event's place, unless it only says it is online.
 */
export function eventPlace(event: Pick<Event, 'location'>): string | null {
    return event.location === null ||
        event.location.trim().toLowerCase() === 'online'
        ? null
        : event.location;
}

/**
 * A place, a link, or both, as GitHub would label it.
 */
export function eventFormat(
    event: Pick<Event, 'location' | 'url'>,
): 'In person' | 'Online' | 'Hybrid' {
    if (eventPlace(event) !== null && event.url !== null) {
        return 'Hybrid';
    }

    return event.url !== null ? 'Online' : 'In person';
}

/**
 * The zone's short name at that moment, like "GMT+1" or "CET", so times say which clock they follow.
 */
export function timeZoneLabel(date: Date, timeZone?: string): string {
    return (
        new Intl.DateTimeFormat(undefined, {
            timeZone,
            timeZoneName: 'short',
        })
            .formatToParts(date)
            .find((part) => part.type === 'timeZoneName')?.value ?? ''
    );
}

/**
 * How far the zone's clock is ahead of UTC at that moment, in milliseconds.
 */
function zoneOffset(date: Date, timeZone?: string): number {
    const parts = Object.fromEntries(
        new Intl.DateTimeFormat('en-US', {
            timeZone,
            hourCycle: 'h23',
            year: 'numeric',
            month: 'numeric',
            day: 'numeric',
            hour: 'numeric',
            minute: 'numeric',
            second: 'numeric',
        })
            .formatToParts(date)
            .map((part) => [part.type, Number(part.value)]),
    );

    return (
        Date.UTC(
            parts.year,
            parts.month - 1,
            parts.day,
            parts.hour,
            parts.minute,
            parts.second,
        ) -
        Math.floor(date.getTime() / 1000) * 1000
    );
}

/**
 * Reads a datetime-local value on the zone's clock rather than the browser's, so an event is entered in the same zone
 * its pages show it in.
 */
export function wallTimeToInstant(value: string, timeZone?: string): string {
    if (value === '') {
        return '';
    }

    const [date, time] = value.split('T');
    const [year, month, day] = date.split('-').map(Number);
    const [hour, minute] = time.split(':').map(Number);
    const wall = Date.UTC(year, month - 1, day, hour, minute);
    const guess = wall - zoneOffset(new Date(wall), timeZone);

    // Around a clock change the offset at the guess can differ from the one at the result, so it is taken again.
    return new Date(wall - zoneOffset(new Date(guess), timeZone)).toISOString();
}
