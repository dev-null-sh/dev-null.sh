/// <reference types="node" />

import assert from 'node:assert/strict';
import { test } from 'node:test';
import { createInertiaApp } from '@inertiajs/react';
import { renderToString } from 'react-dom/server';
import { TooltipProvider } from '../../resources/js/components/ui/tooltip';
import Index from '../../resources/js/pages/notification/index';
import type { Commit, NotificationGroup, User } from '../../resources/js/types';

const actor = {
    id: 'actor',
    name: 'Matthieu Napoli',
    username: 'mnapoli',
    lane: 2,
} as User;

const notification = {
    id: 'notification',
    group_id: 'notification',
    event_count: 1,
    type: 'replied',
    actor,
    actors: [actor],
    actors_count: 1,
    unread_count: 1,
    commit_id: 'reply',
    subject_id: 'parent',
    reason: null,
    created_at: '2026-10-16T11:51:00.000000Z',
    commit: {
        id: 'reply',
        body: 'Try this instead\n```php\nreturn round($score, 6);\n```',
        body_segments: [
            { type: 'text', text: 'Try this instead\n' },
            {
                type: 'block',
                language: 'php',
                html: '<span class="hl-keyword">return</span> round($score, 6);',
            },
        ],
    } as Omit<Commit, 'user'>,
} as NotificationGroup;

async function render(notifications: NotificationGroup[]): Promise<string> {
    const result = await createInertiaApp({
        page: {
            component: 'notification/index',
            props: {
                auth: {
                    user: { id: 'recipient', username: 'nunomaduro' },
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
                filters={{ type: 'all' }}
                readAt="2026-10-16T12:00:00.000000Z"
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

    return result.body.slice(result.body.indexOf('<article'));
}

void test('renders a code block highlighted in a notification', async () => {
    const body = await render([notification]);

    assert.match(body, /<pre[^>]*><span class="hl-keyword">return<\/span>/);
    assert.doesNotMatch(body, /```/);
    assert.equal(body.match(/<a\b/g)?.length, 3);
    assert.doesNotMatch(body, /<button\b/);
    assert.match(body, /<pre[^>]*tabindex="-1"/i);
});

for (const isFollowing of [false, true]) {
    void test(`keeps ${isFollowing ? 'unfollow' : 'follow-back'} separate from highlighted notification links`, async () => {
        const followed = {
            ...notification,
            id: 'follow-notification',
            group_id: 'follow-notification',
            type: 'followed',
            actor: { ...actor, is_followed: isFollowing },
            commit: null,
            commit_id: null,
            subject_id: null,
        } as NotificationGroup;
        const body = await render([notification, followed]);

        assert.match(body, /<pre[^>]*><span class="hl-keyword">return<\/span>/);
        assert.equal(body.match(/<a\b/g)?.length, 6);
        assert.equal(body.match(/<button\b/g)?.length, 1);
        assert.doesNotMatch(body, /<a\b[^>]*>(?:(?!<\/a>).)*<button\b/s);
        assert.match(
            body,
            new RegExp(
                `aria-label="${isFollowing ? 'Unfollow' : 'Follow'} @mnapoli"`,
            ),
        );
        assert.match(body, /followed you/);
        assert.doesNotMatch(body, /```/);
    });
}

void test('shows the image of a starred image-only commit beside who starred it', async () => {
    const starred = {
        ...notification,
        type: 'starred',
        actors_count: 3,
        commit: {
            id: 'image-only',
            body: '',
            body_segments: [],
            images: [
                {
                    id: 'image',
                    url: '/commit-images/image',
                    width: 1200,
                    height: 800,
                    description: null,
                },
            ],
        } as unknown as Omit<Commit, 'user'>,
    } as NotificationGroup;
    const body = (await render([starred])).replaceAll('<!-- -->', '');

    assert.match(
        body,
        /and 2 others <a\b[^>]*data-notification-link[^>]*>starred your image<\/a>/,
    );
    assert.match(body, /<img[^>]*src="\/commit-images\/image"/);
    assert.doesNotMatch(body, /<div[^>]*>(?:<div[^>]*>)*(?:<\/div>)+<\/a>/);
});

void test('links the actor to their profile apart from the commit link', async () => {
    const starred = { ...notification, type: 'starred' } as NotificationGroup;
    const body = await render([starred]);
    const links = [...body.matchAll(/<a\b[^>]*>/g)].map((match) => match[0]);

    assert.deepEqual(
        links.map((link) => link.match(/href="([^"]*)"/)?.[1]),
        ['/@mnapoli', '/@mnapoli', '/@nunomaduro/commits/reply'],
    );
    assert.match(links[0], /hover:underline/);
    assert.doesNotMatch(links[1], /hover:underline/);
    assert.doesNotMatch(body, /<a\b(?:(?!<\/a>).)*<a\b/s);
    assert.match(body, /starred your commit<\/a>/);
});
