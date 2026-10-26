/**
 * The pieces of the schedule picker that are only date arithmetic, kept apart from the picker itself so that
 * anything validating a scheduled time does not have to load the calendar to do it.
 */

export function pad(value: number): string {
    return String(value).padStart(2, '0');
}

/**
 * Writes a date the way a `datetime-local` input does, in the browser's timezone.
 */
export function localDateTime(date: Date): string {
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

/**
 * A local time skipped by a daylight saving change, like 02:30 on the night clocks go forward, comes back shifted.
 */
export function isExistingLocalTime(value: string): boolean {
    return localDateTime(new Date(value)) === value;
}
