/// <reference types="node" />

import assert from 'node:assert/strict';
import { createInertiaApp } from '@inertiajs/react';
import { renderToString } from 'react-dom/server';
import { test } from 'vite-plus/test';
import { TooltipProvider } from '../../resources/js/components/ui/tooltip';
import Index from '../../resources/js/pages/notification/index';
import type { Commit, NotificationGroup, User } from '../../resources/js/types';

const timestamp = '2026-10-16T12:00:00Z';
const actor: User = {
    id: 'actor',
    handle: 'quoter',
    name: 'Quoter',
    avatar_url: null,
    bio: null,
    location: null,
    website: null,
    company: null,
    has_sponsors_listing: false,
    timezone: null,
    show_local_time: false,
    has_github_account: true,
    lane: 0,
    created_at: timestamp,
    updated_at: timestamp,
};
const quoteCommit: Omit<Commit, 'user'> = {
    id: 'quote-commit',
    user_id: actor.id,
    parent_id: null,
    hot_score: 0,
    body: 'My quote commentary',
    mentions: {},
    body_segments: [{ type: 'text', text: 'My quote commentary' }],
    preview_segments: null,
    status: 'approved',
    created_at: timestamp,
    updated_at: timestamp,
    stars_count: 0,
    pasteis_count: 0,
    starred: false,
    quoted_commit_id: 'quoted-commit',
};
const quote: NotificationGroup = {
    id: 'notice',
    recipient_id: 'recipient',
    actor_id: actor.id,
    commit_id: 'quote-commit',
    subject_id: null,
    reason: null,
    read_at: timestamp,
    group_id: 'notice',
    type: 'quoted',
    actor: { ...actor, is_followed: false },
    actors: [actor],
    actors_count: 1,
    event_count: 1,
    unread_count: 0,
    created_at: timestamp,
    updated_at: timestamp,
    commit: quoteCommit,
};

async function renderInbox(notifications: NotificationGroup[], type = 'all') {
    const result = await createInertiaApp({
        page: {
            component: 'notification/index',
            props: {
                auth: {
                    user: { id: 'recipient', handle: 'recipient' },
                    log: {},
                },
                errors: {},
            },
            url: '/notifications',
            version: null,
            clearHistory: false,
            encryptHistory: false,
            rescuedProps: [],
            rememberedState: {},
            flash: {},
        },
        resolve: () => () => (
            <Index
                notifications={{
                    data: notifications,
                    next_cursor: null,
                    prev_cursor: null,
                }}
                filters={{ type }}
                readAt={timestamp}
            />
        ),
        render: renderToString,
        setup: ({ App, props }) => (
            <TooltipProvider>
                <App {...props} />
            </TooltipProvider>
        ),
    });

    assert.ok(result);
    return result.body.replaceAll('<!-- -->', '');
}

// The filters render once for mobile and once for desktop, so count each category once.
function categories(html: string, pressed: boolean): Set<string> {
    return new Set(
        Array.from(
            html.matchAll(
                new RegExp(
                    `aria-pressed="${pressed}"[^>]*>([^<]+)</button>`,
                    'g',
                ),
            ),
            (match) => match[1],
        ),
    );
}

 test('renders quote notifications with their label and quoting commit destination', async () => {
    const html = await renderInbox([quote]);

    assert.match(html, /quoted your commit/);
    assert.match(html, /href="\/@quoter\/commits\/quote-commit"/);
    assert.match(html, /My quote commentary/);
    assert.match(html, /<button[^>]*aria-pressed="true"[^>]*>Quotes<\/button>/);
});

 test('shows the quoted commit under a quote, or a tombstone once it is deleted', async () => {
    const html = await renderInbox([
        {
            ...quote,
            commit: {
                ...quoteCommit,
                quoted_commit: {
                    id: 'quoted-commit',
                    body_segments: [{ type: 'text', text: 'My quoted commit' }],
                    deleted_at: null,
                },
            },
        },
    ]);

    assert.match(html, /My quote commentary.*My quoted commit/s);
    assert.doesNotMatch(html, /<a\b[^>]*>(?:(?!<\/a>).)*<a\b/s);
    assert.match(
        await renderInbox([
            {
                ...quote,
                commit: {
                    ...quoteCommit,
                    quoted_commit: {
                        id: 'quoted-commit',
                        parent_id: null,
                        deleted_at: timestamp,
                    },
                },
            },
        ]),
        /This commit was deleted/,
    );
});

 test('includes quotes in the unfiltered empty inbox message', async () => {
    assert.match(await renderInbox([]), /replies, quotes, mentions/);
});

 test('renders a follow-back control outside the follower profile link', async () => {
    const follow: NotificationGroup = {
        ...quote,
        type: 'followed',
        commit_id: null,
        subject_id: null,
        commit: null,
    };
    const html = await renderInbox([follow]);

    assert.match(
        html,
        /Quoter<\/a>.*@quoter<\/a>(?:<\/span>)? <a\b[^>]*data-notification-link[^>]*>followed you<\/a>/s,
    );
    assert.match(html, /<button[^>]*aria-label="Follow @quoter back"/);
    assert.doesNotMatch(html, /<a\b[^>]*>(?:(?!<\/a>).)*<button\b/s);
    assert.match(
        await renderInbox([
            { ...follow, actor: { ...follow.actor, is_followed: true } },
        ]),
        /aria-label="Unfollow @quoter"/,
    );
});

 test('leaves follow-back out of a group of followers until it is expanded', async () => {
    const html = await renderInbox([
        {
            ...quote,
            type: 'followed',
            commit_id: null,
            subject_id: null,
            commit: null,
            event_count: 2,
            actors_count: 2,
        },
    ]);

    assert.match(
        html,
        /Quoter<\/a> <span[^>]*>and 1 other <\/span><a\b[^>]*data-notification-link[^>]*>followed you<\/a>/,
    );
    assert.match(html, /aria-expanded="false"[^>]*>.*2 followers/s);
    assert.doesNotMatch(html, /aria-label="(Follow|Unfollow) @/);
});

 test('renders notification code without altering emoji shortcodes or nesting interactive controls', async () => {
    const html = await renderInbox([
        {
            ...quote,
            commit: {
                ...quoteCommit,
                body: '`echo ":fire:"`',
                body_segments: [{ type: 'code', code: 'echo ":fire:"' }],
            },
        },
    ]);

    assert.match(html, /<code[^>]*>echo &quot;:fire:&quot;<\/code>/);
    assert.doesNotMatch(html, /Copy code/);
});

 test('renders highlighted notification blocks without a nested copy button', async () => {
    const html = await renderInbox([
        {
            ...quote,
            commit: {
                ...quoteCommit,
                body: '```php\nreturn ":fire:";\n```',
                body_segments: [
                    {
                        type: 'block',
                        language: 'php',
                        filename: null,
                        html: '<span class="hl-keyword">return</span> &quot;:fire:&quot;;',
                    },
                ],
            },
        },
    ]);

    assert.match(
        html,
        /<pre[^>]*><span class="hl-keyword">return<\/span> &quot;:fire:&quot;;<\/pre>/,
    );
    assert.doesNotMatch(html, /Copy code/);
});

 test('renders all notification categories as enabled toggle buttons by default', async () => {
    const body = await renderInbox([]);
    assert.equal(categories(body, true).size, 6);
    assert.doesNotMatch(body, /aria-current="page"|role="tab"/);
});

 test('renders independent category selections and the all-off empty state', async () => {
    const selected = await renderInbox([], 'replies,mentions');
    assert.deepEqual([...categories(selected, true)], ['Replies', 'Mentions']);
    assert.equal(categories(selected, false).size, 4);
    const none = await renderInbox([], 'none');
    assert.equal(categories(none, false).size, 6);
    assert.match(none, /All categories are turned off/);
    assert.match(none, /Show all notifications/);
});
