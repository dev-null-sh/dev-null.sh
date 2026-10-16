/// <reference types="node" />

import assert from 'node:assert/strict';
import { test } from 'node:test';
import { createInertiaApp } from '@inertiajs/react';
import { renderToString } from 'react-dom/server';
import { CommitStarButton } from '../../resources/js/components/commit-star-button';
import type { Commit, CommitStatus } from '../../resources/js/types';

const cases: {
    name: string;
    viewer: string | null;
    status: CommitStatus;
    readOnly: boolean;
    interactive: boolean;
}[] = [
    {
        name: 'own approved commit',
        viewer: 'author',
        status: 'approved',
        readOnly: true,
        interactive: false,
    },
    {
        name: 'own pending commit',
        viewer: 'author',
        status: 'pending',
        readOnly: true,
        interactive: false,
    },
    {
        name: 'approved commit of another author',
        viewer: 'viewer',
        status: 'approved',
        readOnly: false,
        interactive: true,
    },
    {
        name: 'approved commit for a guest',
        viewer: null,
        status: 'approved',
        readOnly: false,
        interactive: true,
    },
    {
        name: 'pending commit of another author',
        viewer: 'viewer',
        status: 'pending',
        readOnly: false,
        interactive: false,
    },
];

for (const scenario of cases) {
    void test(`renders the star control for ${scenario.name}`, async () => {
        const commit: Commit = {
            id: scenario.name,
            user_id: 'author',
            parent_id: null,
            hot_score: 41487,
            quoted_commit_id: null,
            body: 'Shipped a repair',
            body_segments: [],
            status: scenario.status,
            series_id: null,
            series_position: null,
            series_size: null,
            created_at: '2026-10-16T12:00:00Z',
            updated_at: '2026-10-16T12:00:00Z',
            stars_count: 3,
            starred: false,
            user: {
                id: 'author',
                username: 'octocat',
                name: 'Octocat',
                avatar_url: null,
                bio: null,
                location: null,
                website: null,
                company: null,
                has_sponsors_listing: false,
                lane: 0,
                created_at: '2026-10-16T12:00:00Z',
                updated_at: '2026-10-16T12:00:00Z',
            },
        };
        const user = scenario.viewer === null ? null : { id: scenario.viewer };
        const result = await createInertiaApp({
            page: {
                component: 'star',
                props: { auth: { user }, errors: {} },
                url: '/@octocat/commit',
                version: null,
                clearHistory: false,
                encryptHistory: false,
                rescuedProps: [],
                rememberedState: {},
                flash: {},
            },
            resolve: () => () => <CommitStarButton commit={commit} />,
            render: renderToString,
            setup: ({ App, props }) => <App {...props} />,
        });

        assert.ok(result);
        assert.equal(
            result.body.includes('data-test="star-count-readonly"'),
            scenario.readOnly,
        );
        assert.equal(
            result.body.includes('data-test="star-commit-button"'),
            scenario.interactive,
        );

        if (scenario.readOnly) {
            assert.match(result.body, /aria-label="3 stars"/);
            assert.doesNotMatch(result.body, /<button/);
        }
    });
}
