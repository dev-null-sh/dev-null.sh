/// <reference types="node" />
import assert from 'node:assert/strict';
import * as React from 'react';
import type { ReactNode } from 'react';
import { renderToString } from 'react-dom/server';
import { afterEach, test, vi } from 'vite-plus/test';
import type { DateTimePreferences } from '../../resources/js/lib/date-time';
import type { User } from '../../resources/js/types';

const auth = {
    user: { timezone: 'Pacific/Honolulu' },
    dateTime: {} as DateTimePreferences,
};
let server = false;
const timezone = process.env.TZ;

vi.doMock('react', () => ({
    ...React,
    useSyncExternalStore: (
        _subscribe: unknown,
        getSnapshot: () => unknown,
        getServerSnapshot: () => unknown,
    ) => (server ? getServerSnapshot() : getSnapshot()),
}));
vi.doMock('@inertiajs/react', async () => ({
    ...(await vi.importActual('@inertiajs/react')),
    usePage: () => ({ props: { auth } }),
    Head: () => null,
    Deferred: ({ children }: { children: ReactNode }) => children,
}));

const { LocalTime } = await import('../../resources/js/components/local-time');
const { TooltipProvider } =
    await import('../../resources/js/components/ui/tooltip');
const { default: Metrics } =
    await import('../../resources/js/pages/operations/metrics');
const { default: Bulk } =
    await import('../../resources/js/pages/moderation/bulk');
const { UserModerationStatus } =
    await import('../../resources/js/components/user-moderation-menu');

afterEach(() => {
    vi.useRealTimers();
    process.env.TZ = timezone;
    server = false;
});

test('profile clocks use the viewer clock preference in the profile owner timezone', () => {
    vi.useFakeTimers();
    vi.setSystemTime(new Date('2026-10-19T14:35:45Z'));
    auth.dateTime = { timeFormat: '24h' };
    const clock = () => renderToString(<LocalTime timeZone="Asia/Kathmandu" />);
    const html = clock();
    assert.match(html, />20:20<\/time>/);
    assert.match(html, /UTC \+05:45/);
    assert.match(html, /dateTime="2026-10-19T14:35:00.000Z"/);

    auth.dateTime = { timeFormat: '12h' };
    assert.match(clock(), />8:20\s*PM<\/time>/i);
    server = true;
    assert.equal(clock(), '');
});

test('timeout deadlines follow the viewer date and clock preferences', () => {
    process.env.TZ = 'UTC';
    const deadline = () =>
        renderToString(
            <UserModerationStatus
                moderation={{
                    timeoutsCount: 1,
                    timeout: {
                        reason: 'Spam',
                        endsAt: '2026-10-19T14:35:00Z',
                    },
                    ban: null,
                    reasons: {},
                    durations: {},
                }}
            />,
        );
    auth.dateTime = { dateFormat: 'day_first', timeFormat: '24h' };
    assert.match(deadline(), /14:35 · 19 Oct 2026/);
    auth.dateTime = { dateFormat: 'month_first', timeFormat: '12h' };
    assert.match(deadline(), /2:35\s*PM · Oct 19, 2026/i);
});

test('bulk activity timeout deadlines follow viewer date and clock preferences', () => {
    process.env.TZ = 'UTC';
    const props: Parameters<typeof Bulk>[0] = {
        scale: 'day',
        activity: {
            follows: [
                {
                    user: {
                        id: 'bulk-user',
                        username: 'bulk-user',
                        name: 'Bulk User',
                    } as User,
                    count: 32,
                    reach: null,
                    newcomers: 7,
                },
            ],
            stars: [],
            replies: [],
            posts: [],
        },
        thresholds: {
            follows: { perDay: 30, total: 30 },
            stars: { perDay: 30, total: 30 },
            replies: { perDay: 30, total: 30 },
            posts: { perDay: 30, total: 30 },
        },
        moderation: {
            'bulk-user': {
                timeoutsCount: 1,
                timeout: { reason: 'Spam', endsAt: '2026-10-19T14:35:00Z' },
                ban: null,
                reasons: {},
                durations: {},
            },
        },
    };
    const page = () =>
        renderToString(
            <TooltipProvider>
                <Bulk {...props} />
            </TooltipProvider>,
        ).replaceAll('<!-- -->', '');
    auth.dateTime = { dateFormat: 'day_first', timeFormat: '24h' };
    assert.match(page(), /timeout until 14:35 · 19 Oct 2026/);
    auth.dateTime = { dateFormat: 'month_first', timeFormat: '12h' };
    assert.match(page(), /timeout until 2:35\s*PM · Oct 19, 2026/i);
});

test('metrics dates follow viewer date order without changing UTC buckets or date inputs', () => {
    vi.useFakeTimers();
    vi.setSystemTime(new Date('2026-10-22T14:35:00Z'));
    process.env.TZ = 'Pacific/Honolulu';
    const props: Parameters<typeof Metrics>[0] = {
        range: { from: '2026-10-18', to: '2026-10-19', all_time: false },
        activeUserCounts: [
            {
                date: '2026-10-18',
                count: 7,
                returning: 4,
                activated: 2,
                not_activated: 1,
                pending: 0,
            },
            {
                date: '2026-10-19',
                count: 3,
                returning: 2,
                activated: 0,
                not_activated: 0,
                pending: 1,
            },
        ],
        activeUsers: { daily: 3, weekly: 5, monthly: 8, weekly_authors: 2 },
        responses: {
            commits: 3,
            answered: 1,
            median_minutes: 12,
            first_commits: 2,
            first_answered: 1,
        },
        newcomerFollows: {
            newcomers: 3,
            median_follows: 1,
            follows: 2,
            followed_back: 1,
        },
        funnel: {
            visitors: 10,
            signups: 3,
            acquisition_by_day: [
                {
                    date: '2026-10-19',
                    signups: 3,
                    visitors: 10,
                    converted: 2,
                    complete: true,
                },
            ],
            activated: 1,
            activation_eligible: 3,
            activation_by_day: [
                {
                    date: '2026-10-19',
                    activated: 1,
                    eligible: 3,
                    complete: true,
                },
            ],
            retained: 0,
            retention_eligible: 1,
            rates: { conversion: 0.2, activation: 1 / 3, retention: 0 },
            changes: { conversion: null, activation: null, retention: null },
        },
    };
    auth.dateTime = { dateFormat: 'day_first' };
    const page = () =>
        renderToString(
            <TooltipProvider>
                <Metrics {...props} />
            </TooltipProvider>,
        );
    const dayFirst = page();
    assert.match(dayFirst, />18 Oct<\/span>/);
    assert.match(dayFirst, /5 fewer than the 7 of 18 Oct, net/);
    assert.match(
        dayFirst,
        /3 active, 2 returning users, back after any gap, 5 fewer than the 7 of 18 Oct, net, 1 new: 1 under 12h/,
    );
    assert.equal(
        (dayFirst.match(/<th scope="row">19 Oct<\/th>/g) ?? []).length,
        3,
    );
    assert.match(dayFirst, /value="2026-10-18"/);
    assert.match(dayFirst, /value="2026-10-19"/);

    auth.dateTime = { dateFormat: 'month_first' };
    const monthFirst = page();
    assert.match(monthFirst, />Oct 18<\/span>/);
    assert.match(monthFirst, /5 fewer than the 7 of Oct 18, net/);
    assert.equal(
        (monthFirst.match(/<th scope="row">Oct 19<\/th>/g) ?? []).length,
        3,
    );
});
