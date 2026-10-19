/// <reference types="node" />
import assert from 'node:assert/strict';
import type { ReactNode } from 'react';
import { renderToString } from 'react-dom/server';
import { test, vi } from 'vite-plus/test';
import { TooltipProvider } from '../../resources/js/components/ui/tooltip';
import type { NotificationGroup, User } from '../../resources/js/types';

let poll: () => { only?: string[]; data?: Record<string, unknown> };
let scrollParams: { data?: Record<string, unknown> } | undefined;

vi.doMock('@inertiajs/react', async () => ({
    ...(await vi.importActual('@inertiajs/react')),
    usePoll: (_interval: number, options: typeof poll) => {
        poll = options;

        return { start: () => {}, stop: () => {} };
    },
    InfiniteScroll: ({
        params,
        children,
    }: {
        params?: typeof scrollParams;
        children: ReactNode;
    }) => {
        scrollParams = params;

        return children;
    },
}));

const { createInertiaApp } = await import('@inertiajs/react');
const { default: Index } =
    await import('../../resources/js/pages/notification/index');

const timestamp = '2026-10-16T12:00:00Z';
const actor: User = {
    id: 'actor',
    username: 'follower',
    name: 'Follower',
    avatar_url: null,
    bio: null,
    location: null,
    website: null,
    company: null,
    has_sponsors_listing: false,
    timezone: null,
    has_github_account: true,
    lane: 0,
    created_at: timestamp,
    updated_at: timestamp,
};
const follow: NotificationGroup = {
    id: 'notice',
    recipient_id: 'recipient',
    actor_id: actor.id,
    commit_id: null,
    subject_id: null,
    reason: null,
    read_at: timestamp,
    group_id: 'notice',
    type: 'followed',
    actor: { ...actor, is_followed: false },
    actors: [actor],
    actors_count: 1,
    event_count: 1,
    unread_count: 0,
    created_at: timestamp,
    updated_at: timestamp,
    commit: null,
};

await createInertiaApp({
    page: {
        component: 'notification/index',
        props: {
            auth: { user: { id: 'recipient', username: 'recipient' }, log: {} },
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
                data: [follow],
                next_cursor: 'next',
                prev_cursor: null,
            }}
            filters={{ type: 'follows' }}
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

test('polls and loads more with the filter on screen', () => {
    assert.equal(poll().data?.type, 'follows');
    assert.equal(scrollParams?.data?.type, 'follows');
});

test('keeps the messages badge current while the layout poller is off', () => {
    assert.ok(poll().only?.includes('auth.unreadMessagesCount'));
});
