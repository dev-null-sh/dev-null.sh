/// <reference types="node" />
import assert from 'node:assert/strict';
import { renderToString } from 'react-dom/server';
import { test } from 'vite-plus/test';
import {
    ActivityCells,
    activitySummary,
} from '../../resources/js/components/activity-graph';
import { setReaderClock } from '../../resources/js/lib/utils';
import type { Activity } from '../../resources/js/types';

const week: Activity = {
    scale: 'week',
    buckets: [
        { date: '2026-10-13', count: 0 },
        { date: '2026-10-14', count: 2 },
        { date: '2026-10-15', count: 1 },
    ],
};

test('summarizes the activity with its own noun', () => {
    assert.equal(
        activitySummary(week, ['reply', 'replies']),
        '3 replies in the last 7 days',
    );
    assert.equal(
        activitySummary(
            {
                scale: 'day',
                buckets: [{ date: '2026-10-15T12:00:00Z', count: 1 }],
            },
            ['signup', 'signups'],
        ),
        '1 signup in the last 24 hours',
    );
    assert.equal(
        activitySummary({ scale: 'month', buckets: [] }, ['reply', 'replies']),
        'No replies yet',
    );
    assert.equal(
        activitySummary({ scale: 'year', buckets: [] }, ['commit', 'commits']),
        '0 commits in the last year',
    );
    assert.equal(
        activitySummary(
            { scale: 'year', buckets: [{ date: '2026-10-15', count: 1500 }] },
            ['commit', 'commits'],
        ),
        '1,500 commits in the last year',
    );
});

test("labels the hours of the last day on the reader's clock, whatever the clock of the machine rendering them", () => {
    const day: Activity = {
        scale: 'day',
        buckets: Array.from({ length: 24 }, (_, hour) => ({
            date: `2026-10-15T${String(hour).padStart(2, '0')}:00:00Z`,
            count: 0,
        })),
    };
    const labels = () =>
        renderToString(
            <ActivityCells
                activity={day}
                color="var(--muted-foreground)"
                noun={['commit', 'commits']}
            />,
        ).match(/\d\d:\d\d/g);

    try {
        setReaderClock({ timeZone: 'UTC', hourCycle: 'h23' });
        assert.deepEqual(labels(), ['00:00', '06:00', '12:00', '18:00']);

        setReaderClock({ timeZone: 'Pacific/Auckland', hourCycle: 'h23' });
        assert.deepEqual(labels(), ['18:00', '00:00', '06:00']);
    } finally {
        setReaderClock(null);
    }
});

test('labels the cells with the summary and paints them in the given color', () => {
    const html = renderToString(
        <ActivityCells
            activity={week}
            color="var(--muted-foreground)"
            noun={['reply', 'replies']}
        />,
    );

    assert.match(html, /aria-label="3 replies in the last 7 days"/);
    assert.match(html, /--lane:var\(--muted-foreground\)/);
});

test('summarizes the half-year window, counting zero instead of saying none yet', () => {
    assert.equal(
        activitySummary(
            { scale: 'half', buckets: [{ date: '2026-10-15', count: 3 }] },
            ['commit', 'commits'],
        ),
        '3 commits in the last 6 months',
    );
    assert.equal(
        activitySummary({ scale: 'half', buckets: [] }, ['commit', 'commits']),
        '0 commits in the last 6 months',
    );
});

test('draws half and year as week grids with one column per week', () => {
    for (const [scale, weeks] of [
        ['half', 26],
        ['year', 53],
    ] as const) {
        const buckets = Array.from({ length: weeks * 7 }, (_, day) => ({
            date: new Date(Date.UTC(2026, 0, 4 + day))
                .toISOString()
                .slice(0, 10),
            count: day % 3,
        }));
        const html = renderToString(
            <ActivityCells
                activity={{ scale, buckets }}
                color="red"
                noun={['commit', 'commits']}
            />,
        );

        assert.match(
            html,
            new RegExp(`repeat\\(${weeks}, minmax\\(0, 1fr\\)\\)`),
        );
        assert.match(html, /grid-rows-7/);
    }
});

test('leaves the last week unlabelled when it starts a month, so the label cannot spill past the grid', () => {
    const buckets = Array.from({ length: 26 * 7 }, (_, day) => ({
        date: new Date(Date.UTC(2026, 4, 10 + day)).toISOString().slice(0, 10),
        count: 1,
    }));
    const html = renderToString(
        <ActivityCells
            activity={{ scale: 'half', buckets }}
            color="red"
            noun={['commit', 'commits']}
        />,
    );

    assert.match(html, /whitespace-nowrap">Oct</);
    assert.doesNotMatch(html, /whitespace-nowrap">Nov</);
});
