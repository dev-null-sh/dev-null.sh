const locale = 'en-US';

export function eventDateParts(iso: string): {
    month: string;
    day: string;
    weekday: string;
} {
    const date = new Date(iso);

    return {
        month: date.toLocaleString(locale, { month: 'short' }).toLowerCase(),
        day: String(date.getDate()),
        weekday: date
            .toLocaleString(locale, { weekday: 'short' })
            .toLowerCase(),
    };
}

export function formatEventDay(iso: string): string {
    return new Date(iso).toLocaleDateString(locale, {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
    });
}

export function formatEventDate(iso: string): string {
    return new Date(iso).toLocaleDateString(locale, {
        weekday: 'long',
        month: 'long',
        day: 'numeric',
    });
}

export function formatEventTime(iso: string): string {
    return new Date(iso).toLocaleTimeString(locale, {
        hour: '2-digit',
        minute: '2-digit',
        hourCycle: 'h23',
    });
}

export function formatEventTimeRange(startsAt: string, endsAt: string): string {
    const end = new Date(endsAt).toLocaleTimeString(locale, {
        hour: '2-digit',
        minute: '2-digit',
        hourCycle: 'h23',
        timeZoneName: 'short',
    });

    return `${formatEventTime(startsAt)}–${end}`;
}

export function relativeEventLabel(
    startsAt: string,
    now: Date = new Date(),
): string {
    const minutes = Math.round(
        (new Date(startsAt).getTime() - now.getTime()) / 60000,
    );

    if (minutes <= 0) {
        return 'now';
    }

    if (minutes < 60) {
        return `in ${minutes}m`;
    }

    if (minutes < 24 * 60) {
        return `in ${Math.round(minutes / 60)}h`;
    }

    const days = Math.round(minutes / (24 * 60));

    return days === 1 ? 'tomorrow' : `in ${days} days`;
}

export function isToday(iso: string, now: Date = new Date()): boolean {
    return new Date(iso).toDateString() === now.toDateString();
}

export function mapUrl(venue: string | null, address: string | null): string {
    const query = [venue, address].filter(Boolean).join(', ');

    return `https://www.openstreetmap.org/search?query=${encodeURIComponent(query)}`;
}

/**
 * Hands the system share sheet the link alone: chat apps build the preview from its og tags.
 * The link becomes absolute only on click, so rendering never touches window, which does not exist during SSR.
 */
export async function shareLink(path: string): Promise<void> {
    await navigator
        .share({ url: new URL(path, window.location.origin).href })
        .catch(() => undefined);
}
