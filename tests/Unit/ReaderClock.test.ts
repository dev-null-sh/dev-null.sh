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
    assert.deepEqual(clockOf({ timeZone: 'Europe/Paris', hourCycle: 'h23', dateOrder: 'day_first' }), {
        timeZone: 'Europe/Paris',
        hourCycle: 'h23',
        dateOrder: 'day_first',
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
    assert.match(clock.dateOrder, /^(month_first|day_first)$/);
});
