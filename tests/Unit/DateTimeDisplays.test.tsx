/// <reference types="node" />
import assert from 'node:assert/strict';
import * as React from 'react';
import type { ReactNode } from 'react';
import { renderToString } from 'react-dom/server';
import { afterEach, beforeEach, test, vi } from 'vite-plus/test';
import type { DateTimePreferences } from '../../resources/js/lib/date-time';
import type { User } from '../../resources/js/types';

const auth = {
    user: { timezone: 'Pacific/Honolulu' },
    dateTime: {} as DateTimePreferences,
};
let server = false;
let browserLocale = 'en-US';
const timezone = process.env.TZ;
const DateTimeFormat = Intl.DateTimeFormat;

beforeEach(() => {
    browserLocale = 'en-US';
    // Fix the test locale while keeping the real hook and native date formatting.
    vi.spyOn(Intl, 'DateTimeFormat').mockImplementation(
        function (locales, options) {
            return new DateTimeFormat(locales ?? browserLocale, options);
        },
    );
});

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
// Render the pinned panel's contents without a browser portal.
vi.doMock('../../resources/js/components/ui/popover', () => ({
    Popover: ({ children }: { children: ReactNode }) => children,
    PopoverTrigger: ({ children }: { children: ReactNode }) => children,
    PopoverContent: ({ children }: { children: ReactNode }) => children,
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
const { LineChart } =
    await import('../../resources/js/components/operations/charts');
const { default: Forecasts } =
    await import('../../resources/js/pages/operations/forecasts');
const { default: Events } =
    await import('../../resources/js/pages/operations/events');
const { FailuresPanel } =
    await import('../../resources/js/components/operations/health-failures');
const { FailedJobsPanel } =
    await import('../../resources/js/components/operations/health-failed-jobs');
const { OverviewEvents } =
    await import('../../resources/js/components/operations/overview-events');
const { default: Links } = await import('../../resources/js/pages/links/index');
const { PinnedBanner } =
    await import('../../resources/js/components/chat/pinned-messages');
const { default: ScheduledCommits } =
    await import('../../resources/js/pages/scheduled-commits/index');
const { default: SiteActivity } =
    await import('../../resources/js/pages/operations/activity');
const { default: OrganizationMembers } =
    await import('../../resources/js/pages/organization-member/index');
const { setRenderedAt } = await import('../../resources/js/hooks/use-now');
const { browserClock, setReaderClock } =
    await import('../../resources/js/lib/utils');

afterEach(() => {
    setReaderClock(null);
    setRenderedAt(null);
    vi.useRealTimers();
    vi.restoreAllMocks();
    if (timezone === undefined) delete process.env.TZ;
    else process.env.TZ = timezone;
    server = false;
});

test('organization join dates follow preferences while relative labels and instants stay unchanged', () => {
    process.env.TZ = 'Pacific/Honolulu';
    setReaderClock(browserClock());
    vi.useFakeTimers();
    vi.setSystemTime(new Date('2026-10-30T12:00:00Z'));
    setRenderedAt(Date.now());
    server = true;
    const props: Parameters<typeof OrganizationMembers>[0] = {
        organization: { id: 'organization', handle: 'example' } as Parameters<
            typeof OrganizationMembers
        >[0]['organization'],
        role: 'member',
        members: ['2026-10-19T00:35:00Z', '2026-10-30T09:00:00Z'].map(
            (joined_at, index) => ({
                ...({
                    id: `member-${index}`,
                    handle: `member-${index}`,
                    name: 'Example Member',
                    avatar_url: null,
                } as User),
                role: 'member' as const,
                joined_at,
            }),
        ),
    };
    const page = () =>
        renderToString(<OrganizationMembers {...props} />).replaceAll(
            '<!-- -->',
            '',
        );
    auth.dateTime = { dateFormat: 'day_first', timeFormat: '24h' };
    const dayFirst = page();
    assert.match(dayFirst, /title="14:35 · 18 Oct 2026"/);
    assert.match(dayFirst, />18 Oct<\/time>/);
    assert.match(dayFirst, />3h ago<\/time>/);
    assert.match(dayFirst, /dateTime="2026-10-19T00:35:00Z"/);
    auth.dateTime = { dateFormat: 'month_first', timeFormat: '12h' };
    const monthFirst = page();
    assert.match(monthFirst, /title="2:35\s*PM · Oct 18, 2026"/i);
    assert.match(monthFirst, />Oct 18<\/time>/);
    assert.match(monthFirst, />3h ago<\/time>/);
    assert.match(monthFirst, /dateTime="2026-10-19T00:35:00Z"/);
});

test('overall activity dates follow viewer date order without shifting the UTC calendar day', () => {
    process.env.TZ = 'Pacific/Honolulu';
    const props: Parameters<typeof SiteActivity>[0] = {
        today: {
            signups: { today: 2, yesterday: 1 },
            commits: { today: 5, yesterday: 3 },
            replies: { today: 4, yesterday: 2 },
            authors: { today: 3, yesterday: 2 },
        },
        overall: {
            since: '2026-10-19',
            signups: 1240,
            commits: 18325,
            replies: 6427,
            authors: 893,
        },
        activity: { metric: 'commits', scale: 'week', buckets: [] },
        breakdowns: { statuses: [], authors: [] },
    };
    const page = () => renderToString(<SiteActivity {...props} />);
    auth.dateTime = { dateFormat: 'day_first' };
    assert.match(page(), /since 19 Oct 2026/);
    auth.dateTime = { dateFormat: 'month_first' };
    assert.match(page(), /since Oct 19, 2026/);
    props.overall.since = null;
    assert.match(page(), />all time<\/p>/);
});

test('pinned message dates and times follow viewer preferences without changing the instant', () => {
    process.env.TZ = 'Pacific/Honolulu';
    setReaderClock(browserClock());
    vi.useFakeTimers();
    vi.setSystemTime(new Date('2026-10-26T12:00:00Z'));
    const panel = () =>
        renderToString(
            <PinnedBanner
                messages={[
                    {
                        id: 17,
                        user_id: 'author',
                        client_id: 'pinned-message',
                        body: 'A pinned message',
                        created_at: '2026-10-20T00:35:00Z',
                    },
                ]}
                user={{ id: 'viewer' } as User}
                authorOf={() => ({
                    name: 'Author',
                    lane: 'violet',
                    isMe: false,
                })}
                boundary={{ current: null }}
                isCompact={false}
                usage={[]}
                onReply={null}
                onReact={null}
                onUnpin={null}
                onEmptied={() => {}}
            />,
        ).replaceAll('<!-- -->', '');
    auth.dateTime = { dateFormat: 'day_first', timeFormat: '24h' };
    assert.match(panel(), /Mon, 19 Oct · 14:35<\/time>/);
    assert.match(panel(), /dateTime="2026-10-20T00:35:00Z"/);
    auth.dateTime = { dateFormat: 'month_first', timeFormat: '12h' };
    assert.match(panel(), /Mon, Oct 19 · 2:35\s*PM<\/time>/i);
});

test('profile clocks use the viewer clock preference in the profile owner timezone', () => {
    vi.useFakeTimers();
    vi.setSystemTime(new Date('2026-10-19T14:35:45Z'));
    auth.dateTime = { timeFormat: '24h' };
    const clock = (as: 'li' | 'p' = 'li') =>
        renderToString(<LocalTime timeZone="Asia/Kathmandu" as={as} />);
    const html = clock();
    assert.match(html, />20:20<\/time>/);
    assert.match(html, /UTC \+05:45/);
    assert.match(html, /dateTime="2026-10-19T14:35:00.000Z"/);
    assert.match(clock('p'), /^<p[^>]*>.*>20:20<\/time>/);

    auth.dateTime = { timeFormat: '12h' };
    assert.match(clock(), />8:20\s*PM<\/time>/i);
    assert.match(clock('p'), /^<p[^>]*>.*>8:20\s*PM<\/time>/i);
    server = true;
    assert.equal(clock(), '');
    assert.equal(clock('p'), '');
});

test('scheduled dates and both action labels follow viewer preferences without changing the instant', () => {
    process.env.TZ = 'Pacific/Honolulu';
    setReaderClock(browserClock());
    vi.useFakeTimers();
    vi.setSystemTime(new Date('2026-10-26T12:00:00Z'));
    const page = () =>
        renderToString(
            <TooltipProvider>
                <ScheduledCommits
                    scheduledCommits={{
                        data: [
                            {
                                id: 'scheduled-review',
                                body: null,
                                body_segments: [],
                                preview_segments: null,
                                poll_options: null,
                                image_count: 0,
                                scheduled_at: '2026-12-19T00:35:00Z',
                                submitted_at: null,
                                status: 'scheduled',
                            },
                        ],
                        prev_page_url: null,
                        next_page_url: null,
                    }}
                />
            </TooltipProvider>,
        );
    for (const [preferences, expected] of [
        [{ dateFormat: 'day_first', timeFormat: '24h' }, '14:35 · 18 Dec 2026'],
        [
            { dateFormat: 'month_first', timeFormat: '12h' },
            '2:35 PM · Dec 18, 2026',
        ],
    ] as const) {
        auth.dateTime = preferences;
        const html = page().replace(/\s+/g, ' ');
        assert.ok(html.includes(`${expected}</time>`));
        assert.ok(
            html.includes(`aria-label="Commit now instead of at ${expected}"`),
        );
        assert.ok(
            html.includes(
                `aria-label="Cancel the commit scheduled for ${expected}"`,
            ),
        );
        assert.match(html, /dateTime="2026-12-19T00:35:00Z"/);
    }
});

test('forecast dates keep their UTC calendar day and follow viewer date order', () => {
    process.env.TZ = 'Pacific/Honolulu';
    vi.useFakeTimers();
    vi.setSystemTime(new Date('2026-10-22T14:35:00Z'));
    const chart = () =>
        renderToString(
            <LineChart
                history={[{ date: '2026-10-19', value: 7 }]}
                forecast={[
                    { date: '2026-10-20', value: 13, lower: 9, upper: 17 },
                ]}
                label="Signups by UTC day"
                unit="signups"
            />,
        ).replaceAll('<!-- -->', '');
    const milestones = () =>
        renderToString(
            <Forecasts
                forecasts={[]}
                milestones={[
                    {
                        metric: 'users',
                        current: 37,
                        target: 100,
                        eta: '2026-11-19',
                    },
                ]}
                month={[]}
            />,
        ).replaceAll('<!-- -->', '');
    auth.dateTime = { dateFormat: 'day_first' };
    assert.match(chart(), /19 Oct 2026/);
    assert.match(chart(), /20 Oct 2026/);
    assert.match(chart(), /<td>13<\/td><td>9 – 17<\/td>/);
    assert.match(milestones(), /≈ 19 Nov/);
    auth.dateTime = { dateFormat: 'month_first' };
    assert.match(chart(), /Oct 19, 2026/);
    assert.match(milestones(), /≈ Nov 19/);
});

test('event logs keep seconds and local grouping while chart days stay UTC', () => {
    process.env.TZ = 'Pacific/Honolulu';
    setReaderClock(browserClock());
    const props: Parameters<typeof Events>[0] = {
        events: [
            {
                id: 'example',
                source: 'jobs',
                level: 'error',
                at: '2026-10-19T00:35:47Z',
                message: 'Example job failed',
                detail: 'Example exception',
                actor: null,
                href: null,
            },
        ],
        filters: { window: '30d', source: null, level: null, search: '' },
        counts: {
            signups: 0,
            jobs: 3,
            reports: 0,
            moderation: 0,
            bans: 0,
            timeouts: 0,
            features: 0,
        },
        histogram: [
            {
                at: '2026-10-19T00:00:00Z',
                info: 0,
                notice: 0,
                warning: 0,
                error: 3,
            },
        ],
    };
    const page = () =>
        renderToString(<Events {...props} />).replaceAll('<!-- -->', '');
    auth.dateTime = { dateFormat: 'day_first', timeFormat: '12h' };
    assert.match(page(), /2:35:47\s*PM/);
    assert.match(page(), /18 Oct 2026/);
    assert.match(page(), /HST/);
    assert.match(page(), /<th scope="row">19 Oct<\/th>/);
    assert.match(page(), /dateTime="2026-10-19T00:35:47Z"/);
    props.filters.window = '7d';
    assert.match(page(), /Sun 2:00\s*PM/);
    browserLocale = 'fr-FR';
    assert.match(page(), /Sun 2:00\s*PM/);
    browserLocale = 'en-US';
    auth.dateTime = { dateFormat: 'month_first', timeFormat: '24h' };
    props.filters.window = '30d';
    assert.match(page(), />14:35:47<\/time>/);
    assert.match(page(), /Oct 18, 2026/);
    assert.match(page(), /<th scope="row">Oct 19<\/th>/);
});

test('health, overview and shared links use preferences for absolute date fallbacks', () => {
    process.env.TZ = 'UTC';
    const at = '2025-10-19T14:35:00Z';
    const event: Parameters<typeof OverviewEvents>[0]['events'][number] = {
        id: 'example',
        source: 'jobs',
        level: 'error',
        at,
        message: 'Example job failed',
        detail: null,
        actor: null,
        href: null,
    };
    const panels = () =>
        renderToString(
            <>
                <FailuresPanel
                    failureTrend={{
                        last_24h: 3,
                        previous_24h: 1,
                        hours: [{ at, count: 3 }],
                        by_job: [
                            {
                                name: 'App\\Jobs\\Example',
                                count: 3,
                                last_failed_at: at,
                            },
                        ],
                    }}
                />
                <FailedJobsPanel
                    failedJobs={{
                        total: 1,
                        recent: [
                            {
                                uuid: 'example',
                                name: 'App\\Jobs\\Example',
                                queue: 'default',
                                exception: 'Example exception',
                                failed_at: at,
                            },
                        ],
                    }}
                />
                <OverviewEvents events={[event]} />
                <Links
                    url={null}
                    topLinks={[
                        {
                            url: 'https://example.com',
                            title: 'Example link',
                            site_name: 'Example',
                            people: 2,
                            commits: 3,
                            last_mentioned_at: at,
                        },
                    ]}
                />
            </>,
        ).replaceAll('<!-- -->', '');
    auth.dateTime = { dateFormat: 'day_first', timeFormat: '24h' };
    assert.equal(
        (panels().match(/title="14:35 · 19 Oct 2025"/g) ?? []).length,
        3,
    );
    assert.match(panels(), /last 19 Oct 2025/);
    assert.match(panels(), /<th scope="row">14:35<\/th>/);
    auth.dateTime = { dateFormat: 'month_first', timeFormat: '12h' };
    assert.equal(
        (panels().match(/title="2:35\s*PM · Oct 19, 2025"/g) ?? []).length,
        3,
    );
    assert.match(panels(), /last Oct 19, 2025/);
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
                        handle: 'bulk-user',
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
        retentionByDay: { day_1: [], day_7: [] },
        retentionRecordedSince: '2026-10-19',
        retentionCohorts: {
            cohorts: [
                {
                    week: '2026-10-12',
                    signups: 3,
                    days: [{ day: 1, eligible: 3, retained: 2, backfilled: 1 }],
                },
            ],
            curve: [{ day: 1, eligible: 3, retained: 2, backfilled: 1 }],
        },
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
            conversion_by_device: null,
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
    const dayFirst = page().replaceAll('<!-- -->', '');
    assert.match(dayFirst, /approximate before 19 Oct/);
    assert.match(dayFirst, />12 Oct<\/span>/);
    assert.match(dayFirst, /week of 12 Oct, day 1/);
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
    const monthFirst = page().replaceAll('<!-- -->', '');
    assert.match(monthFirst, /approximate before Oct 19/);
    assert.match(monthFirst, />Oct 12<\/span>/);
    assert.match(monthFirst, /week of Oct 12, day 1/);
    assert.match(monthFirst, />Oct 18<\/span>/);
    assert.match(monthFirst, /5 fewer than the 7 of Oct 18, net/);
    assert.equal(
        (monthFirst.match(/<th scope="row">Oct 19<\/th>/g) ?? []).length,
        3,
    );
});
