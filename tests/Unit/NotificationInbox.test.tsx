/// <reference types="node" />

import assert from 'node:assert/strict';
import { test } from 'node:test';
import { createInertiaApp } from '@inertiajs/react';
import { renderToString } from 'react-dom/server';
import Index from '../../resources/js/pages/notification/index';
import type { NotificationGroup, User } from '../../resources/js/types';

const timestamp = '2026-10-16T12:00:00Z';
const actor: User = {
    id: 'actor',
    username: 'quoter',
    name: 'Quoter',
    avatar_url: null,
    bio: null,
    location: null,
    website: null,
    company: null,
    has_sponsors_listing: false,
    lane: 0,
    created_at: timestamp,
    updated_at: timestamp,
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
    actor,
    actors: [actor],
    actors_count: 1,
    event_count: 1,
    unread_count: 0,
    created_at: timestamp,
    updated_at: timestamp,
    commit: {
        id: 'quote-commit',
        user_id: actor.id,
        parent_id: null,
        body: 'My quote commentary',
        body_segments: [{ type: 'text', text: 'My quote commentary' }],
        status: 'approved',
        series_id: null,
        series_position: null,
        series_size: null,
        created_at: timestamp,
        updated_at: timestamp,
        stars_count: 0,
        starred: false,
        quoted_commit_id: 'quoted-commit',
    },
};

async function renderInbox(notifications: NotificationGroup[]) {
    const result = await createInertiaApp({
        page: {
            component: 'notification/index',
            props: {
                auth: {
                    user: { id: 'recipient', username: 'recipient' },
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
            />
        ),
        render: renderToString,
        setup: ({ App, props }) => <App {...props} />,
    });

    assert.ok(result);
    return result.body;
}

void test('renders quote notifications with their label and quoting commit destination', async () => {
    const html = await renderInbox([quote]);

    assert.match(html, /quoted your commit/);
    assert.match(html, /href="\/@quoter\/commits\/quote-commit"/);
    assert.match(html, /My quote commentary/);
    assert.match(html, /<option value="quotes">Quotes<\/option>/);
});

void test('includes quotes in the unfiltered empty inbox message', async () => {
    assert.match(await renderInbox([]), /replies, quotes, mentions/);
});
