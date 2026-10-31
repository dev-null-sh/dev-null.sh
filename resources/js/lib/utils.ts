import type { InertiaLinkProps } from '@inertiajs/react';
import type { ClassValue } from 'clsx';
import { clsx } from 'clsx';
import type { CSSProperties } from 'react';
import { flushSync } from 'react-dom';
import { twMerge } from 'tailwind-merge';
import type { DeletedCommit, Lane, ThreadCommit } from '@/types';
import type { FlashToast, TimeoutFlash } from '@/types/ui';
import { createDateTime } from './date-time.ts';

/**
 * The interface is in English, so month and weekday names are too, whatever the browser's language.
 */
export const dateLocale = 'en-US';

export type HourCycle = 'h11' | 'h12' | 'h23' | 'h24';

/** The time zone times show in, and whether the hours run to 12 or to 24. */
export type Clock = {
    timeZone: string;
    hourCycle: HourCycle;
    dateOrder?: 'month_first' | 'day_first' | null;
};

/** The clock the server shares: the reader's, once their browser has told it. */
export type SharedClock = {
    timeZone: string | null;
    hourCycle: HourCycle | null;
    dateOrder?: 'month_first' | 'day_first' | null;
};

/** What the server renders with until the reader's browser has told it its clock. */
const serverClock: Clock = {
    timeZone: 'UTC',
    hourCycle: 'h23',
    dateOrder: 'month_first',
};

let currentClock: Clock | null = null;

/** How this browser shows times: its time zone, and the hour cycle of its language. */
export function browserClock(): Clock & {
    dateOrder: 'month_first' | 'day_first';
} {
    const { timeZone, hourCycle } = new Intl.DateTimeFormat(undefined, {
        hour: 'numeric',
    }).resolvedOptions();

    const parts = new Intl.DateTimeFormat(undefined, {
        day: 'numeric',
        month: 'short',
    }).formatToParts(new Date());

    return {
        timeZone,
        hourCycle: hourCycle ?? 'h23',
        dateOrder:
            parts.findIndex((part) => part.type === 'day') <
            parts.findIndex((part) => part.type === 'month')
                ? 'day_first'
                : 'month_first',
    };
}

export function clockOf(shared: SharedClock | undefined): Clock | null {
    return shared?.timeZone && shared.hourCycle
        ? { ...shared, timeZone: shared.timeZone, hourCycle: shared.hourCycle }
        : null;
}

/**
 * Sets the clock every time shows on, so the server renders a page with the clock the reader's browser hydrates it
 * with. Null goes back to the browser's own, or to UTC on the server.
 */
export function setReaderClock(clock: Clock | null): void {
    currentClock = clock;
}

export function readerClock(): Clock {
    return (
        currentClock ??
        (typeof window === 'undefined' ? serverClock : browserClock())
    );
}

const dayFormats = new Map<string, Intl.DateTimeFormat>();

/** The calendar day a moment falls on in a time zone. */
export function dayIn(
    date: Date,
    timeZone: string,
): { year: number; month: number; day: number } {
    let format = dayFormats.get(timeZone);

    if (format === undefined) {
        format = new Intl.DateTimeFormat(dateLocale, {
            timeZone,
            year: 'numeric',
            month: 'numeric',
            day: 'numeric',
        });
        dayFormats.set(timeZone, format);
    }

    const parts = Object.fromEntries(
        format.formatToParts(date).map((part) => [part.type, part.value]),
    );

    return {
        year: Number(parts.year),
        month: Number(parts.month),
        day: Number(parts.day),
    };
}

export function characterCount(value: string): number {
    return Array.from(value).length;
}

export function limitCharacters(value: string, max: number): string {
    return Array.from(value).slice(0, max).join('');
}

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function toUrl(url: NonNullable<InertiaLinkProps['href']>): string {
    return typeof url === 'string' ? url : url.url;
}

// Non-React callers use browser defaults. UI components bind account preferences with useDateTime().
export const {
    formatFullDate,
    formatRelativeTime,
    formatFullDateTime,
    formatDayLabel,
    formatZonedTime,
    formatZonedDate,
} = createDateTime();

export function formatSyncedAgo(date: string, now: Date): string {
    const seconds = Math.round(
        (now.getTime() - new Date(date).getTime()) / 1000,
    );

    if (seconds < 60) {
        return 'just now';
    }

    const [value, unit]: [number, Intl.RelativeTimeFormatUnit] =
        seconds < 3600
            ? [Math.floor(seconds / 60), 'minute']
            : seconds < 86400
              ? [Math.floor(seconds / 3600), 'hour']
              : [Math.floor(seconds / 86400), 'day'];

    return new Intl.RelativeTimeFormat(dateLocale, { numeric: 'auto' }).format(
        -value,
        unit,
    );
}

export function laneColor(lane: Lane): string {
    return typeof lane === 'string' ? lane : `var(--lane-${lane})`;
}

/**
 * Organizations are in /dev/null's blue until their admins choose a lane.
 */
export function organizationLaneColor(lane: Lane | null): string {
    return lane === null ? 'var(--primary)' : laneColor(lane);
}

/**
 * A palette lane is a CSS variable, so a hex alpha cannot be appended to it.
 */
export function laneTint(color: string, percent: number): string {
    return `color-mix(in oklab, ${color} ${percent}%, transparent)`;
}

type LaneStrokeOptions = {
    axis?: 'vertical' | 'horizontal';
    /** The length of each dash and of each gap, in pixels. */
    dash?: number;
    /** Ends the dashes on a whole gap, so a dashed path that continues below picks up the same rhythm. */
    anchor?: 'start' | 'end';
};

/**
 * Dashed lane connectors.
 */
export function laneStrokeStyle(
    lane: string,
    options: LaneStrokeOptions = {},
): CSSProperties {
    const isVertical = (options.axis ?? 'vertical') === 'vertical';
    const length = options.dash ?? 2;
    const dash =
        options.anchor === 'end'
            ? `repeating-linear-gradient(${isVertical ? 'to top' : 'to left'}, transparent 0 ${length}px, #000 ${length}px ${length * 2}px)`
            : `repeating-linear-gradient(${isVertical ? 'to bottom' : 'to right'}, #000 0 ${length}px, transparent ${length}px ${length * 2}px)`;

    return {
        backgroundColor: lane,
        WebkitMaskImage: dash,
        maskImage: dash,
        WebkitMaskRepeat: 'repeat',
        maskRepeat: 'repeat',
    };
}

export function isDeleted(commit: {
    deleted_at?: string | null;
}): commit is DeletedCommit {
    return typeof commit.deleted_at === 'string';
}

/**
 * Whether a commit submission came back without a commit. The `committed` and `scheduled` flashes can't tell:
 * they live in the session, so a poll or another tab landing before the redirect takes them. The rejections
 * that still redirect back flash an error toast (rate limited, page expired), or the timeout the user is in.
 */
export function isRejectedSubmission(flash: {
    toast?: FlashToast;
    timeout?: TimeoutFlash;
}): boolean {
    return flash.toast?.type === 'error' || flash.timeout !== undefined;
}

export function laneOf(commit: ThreadCommit): string {
    return isDeleted(commit) ? 'var(--rail)' : laneColor(commit.user.lane);
}

export function shortHash(id: string): string {
    return id.replaceAll('-', '').slice(-7);
}

export function isWithinSeconds(
    date: string,
    seconds: number,
    now: Date = new Date(),
) {
    return now.getTime() - new Date(date).getTime() < seconds * 1000;
}

/** The reader's calendar day a moment falls on, like `2026-10-21`. */
export function localDayKey(date: string): string {
    const { year, month, day } = dayIn(new Date(date), readerClock().timeZone);

    return `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
}

export function browserTimeZone(): string {
    return Intl.DateTimeFormat().resolvedOptions().timeZone;
}

/** A time of day on the reader's clock, like `9:00 AM`. */
export function formatClockTime(date: string): string {
    return formatZonedTime(date, readerClock().timeZone);
}

/** A date and time of day on the reader's clock, like `Oct 26, 2026, 9:00 AM`. */
export function formatClockDateTime(date: string): string {
    const { timeZone, hourCycle } = readerClock();

    return new Date(date).toLocaleString(dateLocale, {
        dateStyle: 'medium',
        timeStyle: 'short',
        hourCycle,
        timeZone,
    });
}

/** The month a moment falls in on the reader's calendar, like `October 2026`. */
export function formatClockMonth(date: string): string {
    return new Date(date).toLocaleDateString(dateLocale, {
        month: 'long',
        year: 'numeric',
        timeZone: readerClock().timeZone,
    });
}

/** The hour, from 0 to 23, and the minute a moment falls on on the reader's clock. */
export function clockTimeOf(date: string): { hour: number; minute: number } {
    const parts = Object.fromEntries(
        new Intl.DateTimeFormat(dateLocale, {
            hour: 'numeric',
            minute: 'numeric',
            hourCycle: 'h23',
            timeZone: readerClock().timeZone,
        })
            .formatToParts(new Date(date))
            .map((part) => [part.type, part.value]),
    );

    return { hour: Number(parts.hour), minute: Number(parts.minute) };
}

/**
 * The offset a timezone has at a moment, like `UTC +02:00`, or `UTC` when it has none.
 */
export function formatUtcOffset(date: Date, timeZone: string): string {
    const offset = new Intl.DateTimeFormat('en-US', {
        timeZone,
        timeZoneName: 'longOffset',
    })
        .formatToParts(date)
        .find((part) => part.type === 'timeZoneName')?.value;

    if (offset === undefined || offset === 'GMT' || offset === 'GMT+00:00') {
        return 'UTC';
    }

    return offset.replace('GMT', 'UTC ');
}

export function timeZoneCity(timeZone: string): string {
    return timeZone.slice(timeZone.lastIndexOf('/') + 1).replaceAll('_', ' ');
}

/**
 * Stops the content under the tabs from sliding in the next view transition.
 */
export function clearTabSlide(): void {
    delete document.documentElement.dataset.tabSlide;
}

let currentTransition: ViewTransition | undefined;

/**
 * Applies a state change inside a view transition, so the page animates to it like it does between pages. The `kind`
 * is set on the root element while the transition runs: the `in-place:` variant names the parts that move on their
 * own only then, so they still slide with their tab between pages.
 */
export function withViewTransition(
    update: () => void,
    kind: 'in-place' | 'theme' = 'in-place',
): Promise<void> {
    if (typeof document.startViewTransition !== 'function') {
        update();

        return Promise.resolve();
    }

    clearTabSlide();
    document.documentElement.dataset.viewTransition = kind;

    const transition = document.startViewTransition(() => flushSync(update));
    currentTransition = transition;

    return transition.finished
        .catch(() => undefined)
        .finally(() => {
            // A newer transition skips this one: leave its kind in place...
            if (currentTransition === transition) {
                currentTransition = undefined;
                delete document.documentElement.dataset.viewTransition;
            }
        });
}
