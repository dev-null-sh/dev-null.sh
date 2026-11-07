import assert from 'node:assert/strict';
import { test } from 'node:test';
import {
    browserClock,
    formatDayLabel,
    formatFullDateTime,
    formatRelativeTime,
    formatSyncedAgo,
    formatZonedDate,
    setReaderClock,
} from '../../resources/js/lib/utils.ts';

void test('month and weekday names stay in English whatever the browser language', () => {
    const original = Object.getOwnPropertyDescriptor(
        Intl,
        'DateTimeFormat',
    )!;
    const DateTimeFormat = original.value as typeof Intl.DateTimeFormat;

    // A French browser: the default locale is what `undefined` resolves to.
    Object.defineProperty(Intl, 'DateTimeFormat', {
        ...original,
        value: class extends DateTimeFormat {
            constructor(
                locales?: Intl.LocalesArgument,
                options?: Intl.DateTimeFormatOptions,
            ) {
                super(locales ?? 'fr-FR', options);
            }
        },
    });

    try {
        setReaderClock(browserClock());
        // These helpers show the local calendar day, so the fixtures are local noons, not UTC ones.
        const noon = (year: number, month: number, day: number) =>
            new Date(year, month - 1, day, 12).toISOString();
        const now = new Date(noon(2026, 10, 18));

        assert.equal(formatRelativeTime(noon(2026, 10, 5), now), '5 Oct');
        assert.equal(formatRelativeTime(noon(2025, 3, 5), now), '5 Mar 2025');
        assert.equal(formatDayLabel(noon(2026, 10, 13), now), 'Tue, 13 Oct');
        assert.match(
            formatFullDateTime('2026-10-05T12:00:00Z', 'UTC'),
            / · 5 Oct 2026$/,
        );
        assert.equal(
            formatZonedDate('2025-10-05T12:00:00Z', 'UTC'),
            '5 Oct 2025',
        );
    } finally {
        setReaderClock(null);
        Object.defineProperty(Intl, 'DateTimeFormat', original);
    }
});

void test('the last GitHub sync reads in English whatever the browser language', () => {
    const original = Object.getOwnPropertyDescriptor(
        Intl,
        'RelativeTimeFormat',
    )!;
    const RelativeTimeFormat = original.value as typeof Intl.RelativeTimeFormat;

    // A French browser: the default locale is what `undefined` resolves to.
    Object.defineProperty(Intl, 'RelativeTimeFormat', {
        ...original,
        value: class extends RelativeTimeFormat {
            constructor(
                locales?: Intl.LocalesArgument,
                options?: Intl.RelativeTimeFormatOptions,
            ) {
                super(locales ?? 'fr-FR', options);
            }
        },
    });

    try {
        const now = new Date('2026-10-18T12:00:00Z');

        assert.equal(formatSyncedAgo('2026-10-18T11:59:30Z', now), 'just now');
        assert.equal(
            formatSyncedAgo('2026-10-18T09:00:00Z', now),
            '3 hours ago',
        );
        assert.equal(formatSyncedAgo('2026-10-17T12:00:00Z', now), 'yesterday');
    } finally {
        Object.defineProperty(Intl, 'RelativeTimeFormat', original);
    }
});
