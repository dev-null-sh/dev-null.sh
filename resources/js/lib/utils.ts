import type { InertiaLinkProps } from '@inertiajs/react';
import type { ClassValue } from 'clsx';
import { clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function toUrl(url: NonNullable<InertiaLinkProps['href']>): string {
    return typeof url === 'string' ? url : url.url;
}

export function formatRelativeTime(date: string, now: Date = new Date()) {
    const seconds = Math.round(
        (now.getTime() - new Date(date).getTime()) / 1000,
    );

    if (seconds < 60) {
        return 'now';
    }

    if (seconds < 3600) {
        return `${Math.floor(seconds / 60)}m`;
    }

    if (seconds < 86400) {
        return `${Math.floor(seconds / 3600)}h`;
    }

    if (seconds < 604800) {
        return `${Math.floor(seconds / 86400)}d`;
    }

    return new Date(date).toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
        year:
            new Date(date).getFullYear() === now.getFullYear()
                ? undefined
                : 'numeric',
    });
}

export function formatFullDateTime(date: string): string {
    const value = new Date(date);

    return `${value.toLocaleTimeString(undefined, {
        hour: 'numeric',
        minute: '2-digit',
    })} · ${value.toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    })}`;
}

export function laneColor(lane: number): string {
    return `var(--lane-${lane})`;
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

export function localDayKey(date: string): string {
    const day = new Date(date);

    return `${day.getFullYear()}-${day.getMonth() + 1}-${day.getDate()}`;
}

export function formatDayLabel(date: string, now: Date = new Date()): string {
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

    if (daysAgo === 0) {
        return 'Today';
    }

    if (daysAgo === 1) {
        return 'Yesterday';
    }

    return day.toLocaleDateString(undefined, {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        year: day.getFullYear() === now.getFullYear() ? undefined : 'numeric',
    });
}
