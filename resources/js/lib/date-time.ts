export type DateTimePreferences = {
    dateFormat?: 'month_first' | 'day_first' | null;
    timeFormat?: '12h' | '24h' | null;
    locale?: string;
};

/** Display preferences never change stored instants or date-only calendar values. */
export function createDateTime(preferences: DateTimePreferences = {}) {
    const formats = new Map<string, Intl.DateTimeFormat>();
    const formatter = (
        locale: string | undefined,
        options: Intl.DateTimeFormatOptions,
    ) => {
        const key = JSON.stringify([locale, options]);
        let value = formats.get(key);
        if (value === undefined) {
            value = new Intl.DateTimeFormat(locale, options);
            formats.set(key, value);
        }
        return value;
    };
    const dayFirst = () => {
        if (preferences.dateFormat != null)
            return preferences.dateFormat === 'day_first';
        const order = formatter(preferences.locale, {
            month: 'short',
            day: 'numeric',
        }).formatToParts(new Date());
        return (
            order.findIndex((part) => part.type === 'day') <
            order.findIndex((part) => part.type === 'month')
        );
    };
    const formatDate = (
        date: string | Date,
        options: {
            timeZone?: string;
            year?: boolean;
            weekday?: boolean;
            monthOnly?: boolean;
        } = {},
    ) => {
        const value = new Date(date);
        const fields = formatter('en-US', {
            timeZone: options.timeZone,
            month: options.monthOnly ? 'long' : 'short',
            day: options.monthOnly ? undefined : 'numeric',
            year: 'numeric',
            weekday: options.weekday ? 'short' : undefined,
        }).formatToParts(value);
        const part = (type: Intl.DateTimeFormatPartTypes) =>
            fields.find((field) => field.type === type)?.value;
        if (options.monthOnly) return `${part('month')} ${part('year')}`;
        const datePart = dayFirst()
            ? `${part('day')} ${part('month')}`
            : `${part('month')} ${part('day')}`;
        const withYear =
            options.year ?? value.getFullYear() !== new Date().getFullYear();
        return `${options.weekday ? `${part('weekday')}, ` : ''}${datePart}${withYear ? `${dayFirst() ? ' ' : ', '}${part('year')}` : ''}`;
    };
    const timeOptions = (): Intl.DateTimeFormatOptions =>
        preferences.timeFormat === '24h'
            ? { hourCycle: 'h23' }
            : preferences.timeFormat === '12h'
              ? { hourCycle: 'h12' }
              : {};
    const formatTime = (date: string | Date, timeZone?: string) =>
        formatter(preferences.locale, {
            hour: preferences.timeFormat === '24h' ? '2-digit' : 'numeric',
            minute: '2-digit',
            timeZone,
            ...timeOptions(),
        }).format(new Date(date));
    const formatHour = (hour: number) =>
        formatter(preferences.locale, {
            hour: preferences.timeFormat === '24h' ? '2-digit' : 'numeric',
            timeZone: 'UTC',
            ...timeOptions(),
        }).format(new Date(Date.UTC(2000, 0, 1, hour)));
    const formatFullDateTime = (date: string, timeZone?: string) =>
        `${formatTime(date, timeZone)} · ${formatDate(date, { timeZone, year: true })}`;
    const formatDayLabel = (date: string, now: Date = new Date()) => {
        const day = new Date(date);
        const startOfToday = new Date(
            now.getFullYear(),
            now.getMonth(),
            now.getDate(),
        );
        const startOfDay = new Date(
            day.getFullYear(),
            day.getMonth(),
            day.getDate(),
        );
        const daysAgo = Math.round(
            (startOfToday.getTime() - startOfDay.getTime()) / 86_400_000,
        );
        if (daysAgo === 0) return 'Today';
        if (daysAgo === 1) return 'Yesterday';
        return formatDate(date, {
            weekday: true,
            year: day.getFullYear() !== now.getFullYear(),
        });
    };
    const formatRelativeTime = (date: string, now: Date = new Date()) => {
        const seconds = Math.round(
            (now.getTime() - new Date(date).getTime()) / 1000,
        );
        if (seconds < 60) return 'now';
        if (seconds < 3600) return `${Math.floor(seconds / 60)}m`;
        if (seconds < 86400) return `${Math.floor(seconds / 3600)}h`;
        if (seconds < 604800) return `${Math.floor(seconds / 86400)}d`;
        return formatDate(date, {
            year: new Date(date).getFullYear() !== now.getFullYear(),
        });
    };
    return {
        formatDate,
        formatFullDate: (date: string) => formatDate(date, { year: true }),
        formatTime,
        formatHour,
        formatFullDateTime,
        formatDayLabel,
        formatRelativeTime,
        formatZonedTime: formatTime,
        formatZonedDate: (date: string, timeZone: string) =>
            formatDate(date, { timeZone }),
    };
}
