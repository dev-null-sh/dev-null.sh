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

export function browserTimeZone(): string {
    return Intl.DateTimeFormat().resolvedOptions().timeZone;
}

export function localDayKey(date: string): string {
    const day = new Date(date);

    return `${day.getFullYear()}-${day.getMonth() + 1}-${day.getDate()}`;
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
