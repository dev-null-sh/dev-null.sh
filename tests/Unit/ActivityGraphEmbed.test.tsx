/// <reference types="node" />

import assert from 'node:assert/strict';
import { createInertiaApp } from '@inertiajs/react';
import { renderToString } from 'react-dom/server';
import { test } from 'vite-plus/test';
import ActivityGraphEmbed from '../../resources/js/components/activity-graph-embed';
import type { User } from '../../resources/js/types';

async function renderEmbed(
    props: {
        activityGraphUrl: string;
        badgeUrl?: string;
        profileUrl: string;
    },
    user: Partial<User> = {},
): Promise<string> {
    const defaultUser: User = {
        id: 'user-1',
        name: 'Octo Cat',
        handle: 'octocat',
        avatar_url: 'https://example.com/avatar.png',
        lane: 0,
        ...user,
    } as User;

    const result = await createInertiaApp<{ auth: { user: User } }>({
        page: {
            component: 'embed',
            props: { auth: { user: defaultUser }, errors: {} },
            url: '/settings/profile',
            version: null,
            clearHistory: false,
            encryptHistory: false,
            rescuedProps: [],
            rememberedState: {},
            flash: {},
        },
        resolve: () => () => <ActivityGraphEmbed {...props} />,
        render: renderToString,
        setup: ({ App, props }) => <App {...props} />,
    });

    assert.ok(result);

    return result.body;
}

test('renders activity graph embed section and tabs by default', async () => {
    const html = await renderEmbed({
        activityGraphUrl: 'https://dev-null.sh/@octocat/graph.svg',
        badgeUrl: 'https://dev-null.sh/@octocat/badge.svg',
        profileUrl: 'https://dev-null.sh/@octocat',
    });

    assert.ok(html.includes('data-test="activity-graph-embed"'));
    assert.ok(html.includes('data-test="graph-embed-tab"'));
    assert.ok(html.includes('data-test="badge-embed-tab"'));
    assert.ok(html.includes('data-test="activity-graph-preview"'));
    assert.ok(html.includes('data-test="embed-snippet"'));
    assert.ok(html.includes('https://dev-null.sh/@octocat/graph.svg'));
    assert.ok(html.includes('https://dev-null.sh/@octocat'));
});

test('falls back to replacing graph.svg with badge.svg when badgeUrl is not provided', async () => {
    const html = await renderEmbed({
        activityGraphUrl: 'https://dev-null.sh/@octocat/graph.svg',
        profileUrl: 'https://dev-null.sh/@octocat',
    });

    assert.ok(html.includes('data-test="activity-graph-embed"'));
    assert.ok(html.includes('Follow badge'));
});
