import assert from 'node:assert/strict';
import { test } from 'node:test';
import {
    convertTimeMentions,
    findTimeMentions,
} from '../../resources/js/lib/commit-times.ts';
import type { BodySegment } from '../../resources/js/types/commit.ts';

const prose = (text: string): BodySegment[] => [{ type: 'text', text }];
const postedAt = '2026-10-19T14:19:00Z';

void test('detects the time and full timezone name from the screenshot', async () => {
    const mentions = findTimeMentions(prose('15:00 British Summer Time'), null);
    assert.deepEqual(mentions, [
        {
            text: '15:00 British Summer Time',
            hour: 15,
            minute: 0,
            timeZone: '+01:00',
            date: null,
            dayOffset: 0,
            inferred: false,
        },
    ]);
    const [time] = await convertTimeMentions(mentions, postedAt);
    assert.equal(time.instant, '2026-10-19T14:00:00Z');
    assert.equal(
        new Date(time.instant!).toLocaleTimeString('en-GB', {
            timeZone: 'Europe/Oslo',
            hour: '2-digit',
            minute: '2-digit',
        }),
        '16:00',
    );
});

void test('reads 12-hour and 24-hour times, parentheses and numeric offsets', async () => {
    for (const [text, instant] of [
        ['3pm BST', '2026-10-19T14:00:00Z'],
        ['3:30 p.m. UTC', '2026-10-19T15:30:00Z'],
        ['12am GMT', '2026-10-19T00:00:00Z'],
        ['12 PM UTC', '2026-10-19T12:00:00Z'],
        ['15:00 (CEST)', '2026-10-19T13:00:00Z'],
        ['15:00 UTC+05:30', '2026-10-19T09:30:00Z'],
        ['15:00 GMT-4', '2026-10-19T19:00:00Z'],
        ['15:00 +0545', '2026-10-19T09:15:00Z'],
        ['15:00Z', '2026-10-19T15:00:00Z'],
        ['2026-12-25T15:00Z', '2026-12-25T15:00:00Z'],
        ['2026-12-25 at 15:00 UTC', '2026-12-25T15:00:00Z'],
        ['15:00 America/New_York', '2026-10-19T19:00:00Z'],
        ['15:00 British  Summer Time', '2026-10-19T14:00:00Z'],
    ]) {
        const [time] = await convertTimeMentions(
            findTimeMentions(prose(text), null),
            postedAt,
        );
        assert.equal(time?.instant, instant, text);
    }
});

void test('uses the author timezone for unqualified times but never invents one', async () => {
    const body = prose('Shipping at 15:00 and celebrating at 5pm.');
    assert.deepEqual(findTimeMentions(body, null), []);
    const mentions = findTimeMentions(body, 'Europe/London');
    assert.equal(mentions.length, 2);
    assert.ok(mentions.every((mention) => mention.inferred));
    const times = await convertTimeMentions(mentions, postedAt);
    assert.deepEqual(
        times.map((time) => time.instant),
        ['2026-10-19T14:00:00Z', '2026-10-19T16:00:00Z'],
    );
});

void test('an explicit timezone overrides the author timezone', async () => {
    const [time] = await convertTimeMentions(
        findTimeMentions(prose('15:00 UTC'), 'Asia/Tokyo'),
        postedAt,
    );
    assert.equal(time.instant, '2026-10-19T15:00:00Z');
    assert.equal(time.inferred, false);
});

void test('relative days use the posting date rather than the day the post is read', async () => {
    for (const [text, instant] of [
        ['Tomorrow at 15:00 BST', '2026-10-20T14:00:00Z'],
        ['15:00 BST tomorrow', '2026-10-20T14:00:00Z'],
        ['Yesterday 3pm UTC', '2026-10-18T15:00:00Z'],
        ['today at 15:00', '2026-10-19T14:00:00Z'],
    ]) {
        const [time] = await convertTimeMentions(
            findTimeMentions(prose(text), 'Europe/London'),
            postedAt,
        );
        assert.equal(time?.instant, instant, text);
    }
});

void test('IANA zones follow daylight saving, while abbreviations keep their stated offsets', async () => {
    for (const [text, instant] of [
        ['2026-07-01 15:00 Europe/London', '2026-07-01T14:00:00Z'],
        ['2026-12-01 15:00 Europe/London', '2026-12-01T15:00:00Z'],
        ['2026-12-01 15:00 BST', '2026-12-01T14:00:00Z'],
        ['2026-07-01 3pm America/New_York', '2026-07-01T19:00:00Z'],
    ]) {
        const [time] = await convertTimeMentions(
            findTimeMentions(prose(text), null),
            postedAt,
        );
        assert.equal(time?.instant, instant, text);
    }
});

void test('uses the posting date in the source timezone near midnight', async () => {
    const [time] = await convertTimeMentions(
        findTimeMentions(prose('00:30 Asia/Tokyo'), null),
        '2026-10-19T23:30:00Z',
    );
    assert.equal(time.instant, '2026-10-19T15:30:00Z');
});

void test('preserves the date when a converted time crosses midnight', async () => {
    const [time] = await convertTimeMentions(
        findTimeMentions(prose('23:30 PDT'), null),
        postedAt,
    );
    assert.equal(time.instant, '2026-10-20T06:30:00Z');
    assert.equal(
        new Date(time.instant!).toLocaleDateString('en-CA', {
            timeZone: 'Asia/Tokyo',
        }),
        '2026-10-20',
    );
});

void test('rejects skipped and repeated hours rather than silently shifting the time', async () => {
    for (const text of [
        '2026-03-29 01:30 Europe/London',
        '2026-10-25 01:30 Europe/London',
        '2026-03-08 02:30 America/New_York',
        '2026-11-01 01:30 America/New_York',
        '2026-02-30 15:00 UTC',
        '15:00 UTC+25:00',
        '15:00 America/Invalid',
    ]) {
        const [time] = await convertTimeMentions(
            findTimeMentions(prose(text), null),
            postedAt,
        );
        assert.ok(time, text);
        assert.equal(time.instant, null, text);
    }
});

void test('excludes code, URLs, versions, durations with seconds and invalid clock times', () => {
    assert.deepEqual(
        findTimeMentions(
            [
                { type: 'code', code: '15:00 BST' },
                { type: 'block', html: '3pm UTC', language: null },
                ...prose(
                    'https://example.com/15:00 www.example.com/3pm example.com/12:00 ' +
                        'v15:00 1:15:00 25:00 UTC 15:60 BST 15pm UTC 0am UTC 123pm ' +
                        'Numbers 15 and 42. user15:00 @3pm',
                ),
            ],
            'Europe/London',
        ),
        [],
    );
});

void test('does not reinterpret an ambiguous or unknown timezone using the author timezone', () => {
    for (const zone of [
        'CST',
        'IST',
        'AST',
        'cst',
        'ist',
        'Cst',
        '(IST)',
        'XYZ',
    ]) {
        assert.deepEqual(
            findTimeMentions(prose(`15:00 ${zone}`), 'Europe/London'),
            [],
            zone,
        );
    }
});

void test('deduplicates equivalent times and preserves the order of distinct times', () => {
    const mentions = findTimeMentions(
        prose('15:00 UTC, 16:30 BST, then 15:00 UTC again.'),
        null,
    );
    assert.deepEqual(
        mentions.map((mention) => mention.text),
        ['15:00 UTC', '16:30 BST'],
    );
});
