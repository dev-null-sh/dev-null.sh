/// <reference types="node" />
import assert from 'node:assert/strict';
import { renderToString } from 'react-dom/server';
import { test, vi } from 'vite-plus/test';
import {
    ActivityCells,
    activitySummary,
} from '../../resources/js/components/activity-graph';
import type { Activity } from '../../resources/js/types';

const auth = {
    user: { timezone: 'Pacific/Kiritimati' },
    dateTime: { dateFormat: 'day_first', timeFormat: '24h' },
};

const week: Activity = {
    scale: 'week',
    buckets: [
        { date: '2026-10-13', count: 0 },
        { date: '2026-10-14', count: 2 },
        { date: '2026-10-15', count: 1 },
    ],
};

vi.mock('@inertiajs/react', async () => ({
    ...(await vi.importActual('@inertiajs/react')),
    usePage: () => ({ props: { auth } }),
}));

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

test('activity hour labels keep browser time when the account timezone differs', () => {
    const activity: Activity = {
        scale: 'day',
        buckets: Array.from({ length: 4 }, (_, hour) => ({
            date: new Date(2026, 9, 19, hour).toISOString(),
            count: 1,
        })),
    };
    for (const timezone of ['Pacific/Kiritimati', 'Pacific/Pago_Pago']) {
        auth.user.timezone = timezone;
        const html = renderToString(
            <ActivityCells
                activity={activity}
                color="var(--muted-foreground)"
                noun={['commit', 'commits']}
            />,
        );
        assert.match(html, />00:00<\/span>/);
    }
});
