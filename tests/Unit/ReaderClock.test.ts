import assert from 'node:assert/strict';
import { afterEach, test } from 'node:test';
import {
    browserClock,
    clockOf,
    clockTimeOf,
    formatClockDateTime,
    formatClockMonth,
    formatClockTime,
    setReaderClock,
    formatDayLabel,
    formatFullDate,
    formatFullDateTime,
    formatRelativeTime,
    formatSyncedAgo,
    formatUtcOffset,
    formatZonedDate,
    formatZonedTime,
    localDayKey,
} from '../../resources/js/lib/utils.ts';

afterEach(() => setReaderClock(null));

void test("shows times on the reader's clock, whatever the clock of the machine rendering them", () => {
    setReaderClock({ timeZone: 'Pacific/Auckland', hourCycle: 'h23' });
    assert.equal(
        formatFullDateTime('2026-10-20T10:36:00Z'),
        '23:36 · Oct 20, 2026',
    );
    assert.equal(formatZonedTime('2026-10-20T10:36:00Z', 'UTC'), '10:36');

    setReaderClock({ timeZone: 'America/New_York', hourCycle: 'h12' });
    assert.equal(
        formatFullDateTime('2026-10-20T10:36:00Z'),
        '6:36 AM · Oct 20, 2026',
    );
    assert.equal(
        formatFullDateTime('2026-10-20T10:36:00Z', 'Asia/Tokyo'),
        '7:36 PM · Oct 20, 2026',
    );
});

void test("shows a time of day, a date and time, and a month on the reader's clock", () => {
    const date = '2026-10-31T23:30:00Z';

    setReaderClock({ timeZone: 'Europe/Budapest', hourCycle: 'h12' });
    assert.equal(formatClockTime(date), '12:30 AM');
    assert.equal(formatClockDateTime(date), 'Nov 1, 2026, 12:30 AM');
    assert.equal(formatClockMonth(date), 'November 2026');
    assert.deepEqual(clockTimeOf(date), { hour: 0, minute: 30 });

    setReaderClock({ timeZone: 'UTC', hourCycle: 'h23' });
    assert.equal(formatClockTime(date), '23:30');
    assert.equal(formatClockDateTime(date), 'Oct 31, 2026, 23:30');
    assert.equal(formatClockMonth(date), 'October 2026');
    assert.deepEqual(clockTimeOf(date), { hour: 23, minute: 30 });
});

void test("counts days on the reader's calendar", () => {
    // Noon in London is already the next day in Auckland.
    const date = '2026-10-20T12:00:00Z';
    const now = new Date('2026-10-21T00:30:00Z');

    setReaderClock({ timeZone: 'Pacific/Auckland', hourCycle: 'h23' });
    assert.equal(localDayKey(date), '2026-10-21');
    assert.equal(formatDayLabel(date, now), 'Today');
    assert.equal(formatFullDate(date), 'Oct 21, 2026');

    setReaderClock({ timeZone: 'UTC', hourCycle: 'h23' });
    assert.equal(localDayKey(date), '2026-10-20');
    assert.equal(formatDayLabel(date, now), 'Yesterday');
    assert.equal(formatFullDate(date), 'Oct 20, 2026');
    assert.equal(formatDayLabel('2026-10-13T12:00:00Z', now), 'Tue, Oct 13');
});

void test("tells the year apart on the reader's calendar", () => {
    const date = '2025-12-31T23:00:00Z';
    const now = new Date('2026-01-20T00:00:00Z');

    setReaderClock({ timeZone: 'Pacific/Auckland', hourCycle: 'h23' });
    assert.equal(formatRelativeTime(date, now), 'Jan 1');

    setReaderClock({ timeZone: 'UTC', hourCycle: 'h23' });
    assert.equal(formatRelativeTime(date, now), 'Dec 31, 2025');
    assert.match(formatZonedDate(date, 'Pacific/Auckland'), /^Jan 1\b/);
});

void test('reads a clock the server shared only once it knows both its time zone and its hour cycle', () => {
    assert.deepEqual(clockOf({ timeZone: 'Europe/Paris', hourCycle: 'h23' }), {
        timeZone: 'Europe/Paris',
        hourCycle: 'h23',
    });
    assert.equal(clockOf({ timeZone: null, hourCycle: 'h23' }), null);
    assert.equal(clockOf({ timeZone: 'Europe/Paris', hourCycle: null }), null);
    assert.equal(clockOf(undefined), null);
});

void test("tells this machine's clock", () => {
    const clock = browserClock();

    assert.equal(
        clock.timeZone,
        Intl.DateTimeFormat().resolvedOptions().timeZone,
    );
    assert.match(clock.hourCycle, /^h(11|12|23|24)$/);
});

void test('cached formats match native output across readers, DST changes and cache eviction', () => {
    const dates = [
        '2025-12-31T23:30:00Z',
        '2026-03-08T06:59:00Z',
        '2026-03-08T07:01:00Z',
        '2026-10-25T00:59:00Z',
        '2026-10-25T01:01:00Z',
        '2026-11-01T05:59:00Z',
        '2026-11-01T06:01:00Z',
    ];
    // More distinct keys than the cache holds, followed by the same zones again.
    const zones = [
        'UTC',
        ...Intl.supportedValuesOf('timeZone').slice(0, 140),
        'America/New_York',
        'Europe/Lisbon',
        'Asia/Kathmandu',
        'UTC',
    ];

    for (const timeZone of zones) {
        for (const hourCycle of ['h11', 'h12', 'h23', 'h24'] as const) {
            setReaderClock({ timeZone, hourCycle });
            for (const date of dates) {
                const value = new Date(date);
                const time = value.toLocaleTimeString('en-US', {
                    hour: 'numeric',
                    minute: '2-digit',
                    timeZone,
                    hourCycle,
                });
                const day = value.toLocaleDateString('en-US', {
                    month: 'short',
                    day: 'numeric',
                    year: 'numeric',
                    timeZone,
                });
                assert.equal(formatClockTime(date), time);
                assert.equal(formatFullDate(date), day);
                assert.equal(formatFullDateTime(date), `${time} · ${day}`);
                assert.equal(
                    formatClockDateTime(date),
                    value.toLocaleString('en-US', {
                        dateStyle: 'medium',
                        timeStyle: 'short',
                        timeZone,
                        hourCycle,
                    }),
                );
                assert.equal(
                    formatClockMonth(date),
                    value.toLocaleDateString('en-US', {
                        month: 'long',
                        year: 'numeric',
                        timeZone,
                    }),
                );
            }
        }
    }
});

void test('preserves invalid-date labels for locale string helpers', () => {
    setReaderClock({ timeZone: 'UTC', hourCycle: 'h23' });
    assert.equal(formatClockTime('invalid'), 'Invalid Date');
    assert.equal(formatFullDate('invalid'), 'Invalid Date');
    assert.equal(formatClockDateTime('invalid'), 'Invalid Date');
    assert.equal(formatClockMonth('invalid'), 'Invalid Date');
    assert.equal(formatFullDateTime('invalid'), 'Invalid Date · Invalid Date');
});

void test('reuses offset formats without freezing daylight-saving offsets', () => {
    assert.equal(
        formatUtcOffset(new Date('2026-03-08T06:59:00Z'), 'America/New_York'),
        'UTC -05:00',
    );
    assert.equal(
        formatUtcOffset(new Date('2026-03-08T07:01:00Z'), 'America/New_York'),
        'UTC -04:00',
    );
    assert.equal(
        formatUtcOffset(new Date('2026-03-08T07:01:00Z'), 'UTC'),
        'UTC',
    );
    assert.equal(
        formatUtcOffset(new Date('2026-03-08T07:01:00Z'), 'Asia/Kathmandu'),
        'UTC +05:45',
    );
});

void test('keeps synced-ago labels at minute, hour and day boundaries', () => {
    const now = new Date('2026-10-27T12:00:00Z');
    for (const [seconds, expected] of [
        [59, 'just now'],
        [60, '1 minute ago'],
        [3600, '1 hour ago'],
        [86400, 'yesterday'],
        [172800, '2 days ago'],
    ] as const) {
        assert.equal(
            formatSyncedAgo(
                new Date(now.getTime() - seconds * 1000).toISOString(),
                now,
            ),
            expected,
        );
    }
});
