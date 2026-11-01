import assert from 'node:assert/strict';
import { test } from 'node:test';
import { changelogDayHeading } from '../../resources/js/lib/changelog.ts';
import { createDateTime } from '../../resources/js/lib/date-time.ts';
import { setReaderClock } from '../../resources/js/lib/utils.ts';

process.env.TZ = 'UTC';

const instant = '2026-10-19T14:35:00Z';

void test('browser defaults use shared display metadata and account preferences override it', () => {
    setReaderClock({
        timeZone: 'Pacific/Honolulu',
        hourCycle: 'h23',
        dateOrder: 'day_first',
    });
    try {
        const date = '2026-10-19T00:35:00Z';
        const defaults = createDateTime();
        assert.equal(defaults.formatFullDateTime(date), '14:35 · 18 Oct 2026');
        assert.equal(defaults.formatHour(0), '00');
        const preferences = createDateTime({
            dateFormat: 'month_first',
            timeFormat: '12h',
        });
        assert.equal(preferences.formatFullDateTime(date), '2:35 PM · Oct 18, 2026');
        assert.equal(preferences.formatFullDateTime(date, 'UTC'), '12:35 AM · Oct 19, 2026');
        assert.equal(defaults.formatDayLabel(date, new Date('2026-10-19T01:00:00Z')), 'Today');
    } finally {
        setReaderClock(null);
    }
});

void test('date order and clock format are independent overrides', () => {
    const dayFirst = createDateTime({
        dateFormat: 'day_first',
        timeFormat: '24h',
        locale: 'en-US',
    });
    assert.equal(dayFirst.formatFullDateTime(instant), '14:35 · 19 Oct 2026');
    assert.equal(dayFirst.formatFullDate(instant), '19 Oct 2026');
    assert.equal(dayFirst.formatDate(instant, { year: false }), '19 Oct');
    assert.equal(dayFirst.formatTime('2026-10-19T00:00:00Z'), '00:00');
    assert.equal(dayFirst.formatHour(0), '00');
    const monthFirst = createDateTime({
        dateFormat: 'month_first',
        timeFormat: '12h',
        locale: 'en-GB',
    });
    assert.match(
        monthFirst.formatFullDateTime(instant),
        /^2:35\s*pm · Oct 19, 2026$/i,
    );
    assert.equal(monthFirst.formatFullDate(instant), 'Oct 19, 2026');
    assert.match(monthFirst.formatHour(14), /^2\s*pm$/i);
    const dateOnly = createDateTime({ dateFormat: 'day_first', locale: 'en-US' });
    assert.match(dateOnly.formatFullDateTime(instant), /^2:35\s*PM · 19 Oct 2026$/);
    const timeOnly = createDateTime({ timeFormat: '24h', locale: 'en-US' });
    assert.equal(timeOnly.formatFullDateTime(instant), '14:35 · Oct 19, 2026');
});

void test('browser defaults detect order and clock while keeping months English', () => {
    assert.equal(
        createDateTime({ locale: 'en-US' }).formatDate(instant, { year: true }),
        'Oct 19, 2026',
    );
    const french = createDateTime({ locale: 'fr-FR' });
    assert.equal(french.formatDate(instant, { year: true }), '19 Oct 2026');
    assert.equal(french.formatTime(instant), '14:35');
    assert.equal(
        french.formatTime('2026-10-19T00:35:00Z', 'Pacific/Honolulu', {
            weekday: 'short',
        }),
        'Sun 14:35',
    );
    assert.match(
        createDateTime({ locale: 'fr-FR', timeFormat: '12h' }).formatTime(
            '2026-10-19T00:35:00Z',
            'Asia/Tokyo',
            { weekday: 'long', timeZone: 'Pacific/Honolulu' },
        ),
        /^Sunday 2:35\s*PM$/,
    );
    assert.equal(
        french.formatDate(instant, { monthOnly: true }),
        'October 2026',
    );
});

void test('relative labels stay short but their absolute fallback follows preferences', () => {
    const format = createDateTime({ dateFormat: 'day_first' });
    const now = new Date('2026-10-19T14:35:00Z');
    assert.equal(format.formatRelativeTime('2026-10-19T14:30:00Z', now), '5m');
    assert.equal(format.formatRelativeTime('2026-10-19T12:35:00Z', now), '2h');
    assert.equal(
        format.formatRelativeTime('2026-10-05T12:00:00Z', now),
        '5 Oct',
    );
    assert.equal(
        format.formatRelativeTime('2025-10-05T12:00:00Z', now),
        '5 Oct 2025',
    );
});

void test('format preferences preserve browser-local days and year omission at midnight', () => {
    process.env.TZ = 'America/Los_Angeles';
    setReaderClock({ timeZone: 'America/Los_Angeles', hourCycle: 'h12' });
    try {
        const now = new Date('2027-01-01T01:00:00Z');
        for (const dateFormat of [null, 'month_first', 'day_first'] as const) {
            const format = createDateTime({ dateFormat, locale: 'en-US' });
            assert.equal(
                format.formatDayLabel('2026-12-31T23:00:00Z', now),
                'Today',
            );
            assert.equal(
                format.formatDayLabel('2026-12-30T23:00:00Z', now),
                'Yesterday',
            );
            assert.equal(
                format.formatRelativeTime('2026-12-10T12:00:00Z', now),
                dateFormat === 'day_first' ? '10 Dec' : 'Dec 10',
            );
            assert.equal(
                format.formatDate(now, { year: true }),
                dateFormat === 'day_first' ? '31 Dec 2026' : 'Dec 31, 2026',
            );
            assert.equal(
                format.formatFullDate(now.toISOString()),
                dateFormat === 'day_first' ? '31 Dec 2026' : 'Dec 31, 2026',
            );
            assert.equal(
                format.formatDate(now, { timeZone: 'Asia/Tokyo', year: true }),
                dateFormat === 'day_first' ? '1 Jan 2027' : 'Jan 1, 2027',
            );
        }
    } finally {
        setReaderClock(null);
        process.env.TZ = 'UTC';
    }
});

void test('UTC date-only activity and changelog dates do not shift with viewer timezone', () => {
    const format = createDateTime({
        dateFormat: 'day_first',
    });
    assert.equal(
        format.formatDate('2026-10-19T00:00:00Z', {
            timeZone: 'UTC',
            year: false,
        }),
        '19 Oct',
    );
    assert.deepEqual(
        changelogDayHeading('2026-10-19', 'UTC', new Date(instant), {
            dateFormat: 'day_first',
        }),
        { title: 'Today', date: '19 Oct' },
    );
});

void test('zoned dates preserve reader-clock year omission and honor date order', (t) => {
    t.mock.timers.enable({ apis: ['Date'], now: Date.parse('2027-01-01T12:00:00Z') });
    const cases = [
        ['2027-01-01T12:00:00Z', '2027-01-01T00:30:00Z', 'Pacific/Honolulu', '31 Dec', 'Dec 31', '2026', true],
        ['2026-12-31T09:00:00Z', '2026-12-31T11:00:00Z', 'Pacific/Kiritimati', '1 Jan', 'Jan 1', '2027', true],
        ['2027-01-01T00:30:00Z', '2026-12-31T23:30:00Z', 'Pacific/Honolulu', '31 Dec', 'Dec 31', '2026', false],
        ['2026-12-31T11:00:00Z', '2027-01-01T00:30:00Z', 'Pacific/Kiritimati', '1 Jan', 'Jan 1', '2027', false],
    ] as const;

    for (const dateFormat of [null, 'day_first', 'month_first'] as const) {
        const format = createDateTime({ dateFormat, locale: 'en-US' });
        for (const [now, date, timeZone, dayFirst, monthFirst, year, includeYear] of cases) {
            t.mock.timers.setTime(Date.parse(now));
            const label = dateFormat === 'day_first' ? dayFirst : monthFirst;
            const full = `${label}${dateFormat === 'day_first' ? ' ' : ', '}${year}`;
            assert.equal(format.formatZonedDate(date, timeZone), includeYear ? full : label);
            assert.equal(format.formatDate(date, { timeZone, year: true }), full);
            assert.equal(format.formatDate(date, { timeZone, year: false }), label);
        }
    }
});

void test('relative thresholds do not depend on display preferences', () => {
    const now = new Date('2026-10-19T14:35:00Z');
    const cases = [
        [59, 'now'],
        [60, '1m'],
        [3599, '59m'],
        [3600, '1h'],
        [86399, '23h'],
        [86400, '1d'],
        [604799, '6d'],
    ] as const;
    for (const dateFormat of ['month_first', 'day_first'] as const) {
        for (const timeFormat of ['12h', '24h'] as const) {
            const format = createDateTime({ dateFormat, timeFormat });
            for (const [seconds, expected] of cases) {
                assert.equal(
                    format.formatRelativeTime(
                        new Date(now.getTime() - seconds * 1000).toISOString(),
                        now,
                    ),
                    expected,
                );
            }
            assert.equal(
                format.formatRelativeTime('2026-10-12T14:35:00Z', now),
                dateFormat === 'day_first' ? '12 Oct' : 'Oct 12',
            );
        }
    }
});
