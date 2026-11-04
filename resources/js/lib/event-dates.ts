import { dateLocale } from '@/lib/utils';
import type { Event } from '@/types';

function sameDay(a: Date, b: Date, timeZone?: string): boolean {
    const day = (date: Date) =>
        date.toLocaleDateString(dateLocale, {
            year: 'numeric',
            month: 'numeric',
            day: 'numeric',
            timeZone,
        });

    return day(a) === day(b);
}

export function formatEventTime(date: string, timeZone?: string): string {
    return new Date(date).toLocaleTimeString(undefined, {
        hour: 'numeric',
        minute: '2-digit',
        timeZone,
    });
}

export function formatEventDay(date: string, timeZone?: string): string {
    return new Date(date).toLocaleDateString(dateLocale, {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        timeZone,
    });
}

/**
 * "Tue, Nov 4, 2026 · 6:00 PM – 8:00 PM", or both days when it spans several.
 */
export function formatEventWhen(
    event: Pick<Event, 'starts_at' | 'ends_at'>,
    timeZone?: string,
): string {
    const start = `${formatEventDay(event.starts_at, timeZone)} · ${formatEventTime(event.starts_at, timeZone)}`;

    if (sameDay(new Date(event.starts_at), new Date(event.ends_at), timeZone)) {
        return `${start} – ${formatEventTime(event.ends_at, timeZone)}`;
    }

    return `${start} – ${formatEventDay(event.ends_at, timeZone)} · ${formatEventTime(event.ends_at, timeZone)}`;
}

/**
 * A short calendar tile: "NOV" over "4".
 */
export function eventTile(
    date: string,
    timeZone?: string,
): { month: string; day: string } {
    const value = new Date(date);

    return {
        month: value
            .toLocaleDateString(dateLocale, { month: 'short', timeZone })
            .toUpperCase(),
        day: value.toLocaleDateString(dateLocale, { day: 'numeric', timeZone }),
    };
}
