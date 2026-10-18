/// <reference types="node" />
import assert from 'node:assert/strict';
import { createElement } from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { afterEach, test } from 'vite-plus/test';
import { ActivityDataTable } from '../../resources/js/components/activity-data';
import { ActivityGraph } from '../../resources/js/components/activity-graph';
import type { Activity, ActivityScale } from '../../resources/js/types';

const timezone = process.env.TZ;
const render = (activity: Activity) =>
    renderToStaticMarkup(createElement(ActivityDataTable, { activity }));
const dates = (html: string) =>
    [...html.matchAll(/<time datetime="([^"]+)">([^<]+)<\/time>/gi)].map(
        (match) => ({ date: match[1], label: match[2] }),
    );
const counts = (html: string) =>
    [...html.matchAll(/<td[^>]*>(\d+)<\/td>/g)].map((match) =>
        Number(match[1]),
    );

afterEach(() => {
    if (timezone === undefined) delete process.env.TZ;
    else process.env.TZ = timezone;
});

test('keeps the data table out of the collapsed graph markup', () => {
    const html = renderToStaticMarkup(
        createElement(ActivityGraph, {
            activity: {
                scale: 'week',
                buckets: [{ date: '2026-10-18', count: 2 }],
            },
            lane: '#d9480f',
        }),
    );
    assert.match(html, /<details[^>]*>/);
    assert.match(html, /<summary[^>]*>View activity data<\/summary>/);
    assert.doesNotMatch(html, /<table|role="region"/);
    assert.match(html, /aria-label="2 commits in the last 7 days"/);
});

for (const scale of ['week', 'month', 'year'] as const) {
    test(`shows every ${scale} bucket with its UTC date and original count`, () => {
        const activity: Activity = {
            scale,
            buckets: [
                { date: '2026-12-31', count: 2 },
                { date: '2027-01-01', count: 0 },
                { date: '2027-01-02', count: 5 },
            ],
        };
        const html = render(activity);
        assert.deepEqual(dates(html), [
            { date: '2026-12-31', label: 'Dec 31, 2026' },
            { date: '2027-01-01', label: 'Jan 1, 2027' },
            { date: '2027-01-02', label: 'Jan 2, 2027' },
        ]);
        assert.deepEqual(counts(html), [2, 0, 5]);
        assert.match(html, /Commit activity by date \(UTC\)/);
        assert.equal([...html.matchAll(/scope="col"/g)].length, 2);
        assert.equal([...html.matchAll(/scope="row"/g)].length, 3);
    });
}

test('labels hourly buckets by their start time, including midnight and a year boundary', () => {
    const html = render({
        scale: 'day',
        buckets: [
            { date: '2026-12-31T23:00:00Z', count: 3 },
            { date: '2027-01-01T00:00:00Z', count: 0 },
        ],
    });
    assert.deepEqual(dates(html), [
        { date: '2026-12-31T23:00:00Z', label: 'Dec 31, 2026, 23:00' },
        { date: '2027-01-01T00:00:00Z', label: 'Jan 1, 2027, 00:00' },
    ]);
    assert.deepEqual(counts(html), [3, 0]);
    assert.match(html, /Hour starting \(UTC\)/);
    assert.match(html, /Commit activity by hour \(UTC\)/);
});

test('keeps dates and hours stable across viewer timezones and a daylight-saving transition', () => {
    const activity: Activity = {
        scale: 'day',
        buckets: [
            { date: '2026-11-01T05:00:00Z', count: 1 },
            { date: '2026-11-01T06:00:00Z', count: 2 },
        ],
    };
    for (const zone of [
        'America/New_York',
        'Asia/Dhaka',
        'Pacific/Kiritimati',
    ]) {
        process.env.TZ = zone;
        assert.deepEqual(
            dates(render(activity)).map((row) => row.label),
            ['Nov 1, 2026, 05:00', 'Nov 1, 2026, 06:00'],
        );
        assert.equal(
            dates(
                render({
                    scale: 'week',
                    buckets: [{ date: '2024-02-29', count: 1 }],
                }),
            )[0].label,
            'Feb 29, 2024',
        );
    }
});

test('distinguishes an empty response from periods with zero commits', () => {
    for (const scale of [
        'day',
        'week',
        'month',
        'year',
    ] satisfies ActivityScale[]) {
        const empty = render({ scale, buckets: [] });
        assert.match(empty, /No activity data available\./);
        assert.deepEqual(counts(empty), []);
        const date = scale === 'day' ? '2026-10-18T00:00:00Z' : '2026-10-18';
        const zero = render({ scale, buckets: [{ date, count: 0 }] });
        assert.doesNotMatch(zero, /No activity data available/);
        assert.deepEqual(counts(zero), [0]);
    }
});

test('exposes a whole year with one scroll-region focus stop and leaves the buckets intact', () => {
    const activity: Activity = {
        scale: 'year',
        buckets: Array.from({ length: 371 }, (_, index) => ({
            date: new Date(Date.UTC(2025, 9, 12 + index))
                .toISOString()
                .slice(0, 10),
            count: index % 3,
        })),
    };
    const before = structuredClone(activity);
    activity.buckets.forEach(Object.freeze);
    Object.freeze(activity.buckets);
    const html = render(activity);
    assert.equal(dates(html).length, 371);
    assert.deepEqual(
        counts(html),
        before.buckets.map((bucket) => bucket.count),
    );
    assert.equal([...html.matchAll(/tabindex="0"/gi)].length, 1);
    const captionId = html.match(/<caption id="([^"]+)"/)?.[1];
    assert(captionId);
    assert(html.includes(`role="region" aria-labelledby="${captionId}"`));
    assert.deepEqual(activity, before);
});
