/// <reference types="node" />
import assert from 'node:assert/strict';
import { createElement } from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { afterEach, test } from 'vite-plus/test';
import { StatisticsActivity } from '../../resources/js/components/statistics-activity';
import type { ActivityBucket } from '../../resources/js/types';

const timezone = process.env.TZ;
const render = (activity: ActivityBucket[]) =>
    renderToStaticMarkup(createElement(StatisticsActivity, { activity }));

afterEach(() => {
    if (timezone === undefined) delete process.env.TZ;
    else process.env.TZ = timezone;
});

test('offers a chart summary and exact daily counts including zeroes', () => {
    const activity = Array.from({ length: 30 }, (_, day) => ({
        date: new Date(Date.UTC(2026, 8, 19 + day)).toISOString().slice(0, 10),
        count: day % 3,
    }));
    const html = render(activity);

    assert.match(html, /30 public commits over 30 days/);
    assert.match(html, /highest daily count is 2/);
    assert.equal([...html.matchAll(/<rect /g)].length, 30);
    assert.equal([...html.matchAll(/scope="row"/g)].length, 30);
    assert.deepEqual(
        [...html.matchAll(/<td[^>]*>([^<]+)<\/td>/g)].map((match) => Number(match[1])),
        activity.map((bucket) => bucket.count),
    );
    assert.match(html, /<summary[^>]*>View daily activity data<\/summary>/);
    assert.match(html, /Date \(UTC\)/);
    assert.match(html, /Daily public commits \(UTC\)/);
});

test('keeps UTC dates stable across viewer timezones, leap days and year boundaries', () => {
    for (const zone of ['America/New_York', 'Asia/Dhaka', 'Pacific/Kiritimati']) {
        process.env.TZ = zone;
        const html = render([
            { date: '2024-02-29', count: 1 },
            { date: '2026-12-31', count: 0 },
            { date: '2027-01-01', count: 2 },
        ]);
        assert.match(html, /Feb 29, 2024/);
        assert.match(html, /Dec 31, 2026/);
        assert.match(html, /Jan 1, 2027/);
    }
});

test('represents an empty community without inventing bar heights', () => {
    const html = render([{ date: '2026-10-18', count: 0 }]);
    assert.match(html, /No public commits in this period\./);
    assert.match(html, /height="0"/);
    assert.doesNotMatch(html, /NaN|Infinity/);
});

test('formats large counts and scales the tallest bar within the chart', () => {
    const html = render([
        { date: '2026-10-17', count: 1234567 },
        { date: '2026-10-18', count: 0 },
    ]);
    assert.match(html, /1,234,567 public commits/);
    assert.match(html, /height="128"/);
    assert.match(html, /<td[^>]*>1,234,567<\/td>/);
    assert.doesNotMatch(html, /No public commits in this period/);
});

test('names the chart and table region and provides a single table focus stop', () => {
    const html = render([{ date: '2026-10-18', count: 1 }]);
    assert.match(html, /1 public commit over 1 day/);
    const captionId = html.match(/<caption id="([^"]+)"/)?.[1];
    assert(captionId);
    assert(html.includes(`role="region" aria-labelledby="${captionId}" tabindex="0"`));
    assert.equal([...html.matchAll(/tabindex="0"/gi)].length, 1);
    assert.equal([...html.matchAll(/scope="col"/g)].length, 2);
    assert.match(html, /role="img" aria-labelledby="[^"]+"/);
    assert.match(html, /<title id="[^"]+">Daily public commits<\/title>/);
});
