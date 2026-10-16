/// <reference types="node" />

import assert from 'node:assert/strict';
import { test } from 'node:test';
import { createInertiaApp } from '@inertiajs/react';
import { renderToString } from 'react-dom/server';
import { CommitFeed } from '../../resources/js/components/commit-feed';
import type { Auth, Commit, CursorPaginated } from '../../resources/js/types';

function commit(id: string): Commit {
    return {
        id,
        user_id: 'author',
        parent_id: null,
        hot_score: 41485,
        body: id,
        body_segments: [{ type: 'text', text: id }],
        status: 'approved',
        series_id: null,
        series_position: null,
        series_size: null,
        created_at: '2026-10-16T12:00:00Z',
        updated_at: '2026-10-16T12:00:00Z',
        stars_count: 0,
        replies_count: 0,
        starred: false,
        quoted_commit_id: null,
        user: {
            id: 'author',
            username: 'author',
            name: 'Author',
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
}

for (const isRanked of [false, true]) {
    void test(`collapses moderated rows in a ${isRanked ? 'ranked' : 'chronological'} feed without duplicating the latest commit`, async () => {
        const latest = commit('latest');
        const removing = commit('removing');
        const kept = commit('kept');
        const commits = {
            data: [latest, removing, kept],
            next_cursor: null,
            prev_cursor: null,
        };
        const result = await createInertiaApp<{
            auth: Auth;
            commits: CursorPaginated<Commit>;
        }>({
            page: {
                component: 'feed',
                props: {
                    auth: {
                        user: null,
                        isAdmin: false,
                        log: null,
                        unreadNotificationsCount: 0,
                    },
                    commits,
                    errors: {},
                },
                url: '/log/everyone',
                version: null,
                clearHistory: false,
                encryptHistory: false,
                rescuedProps: [],
                rememberedState: {},
                flash: {},
            },
            resolve: () => () => (
                <CommitFeed
                    commits={commits}
                    latestCommits={isRanked ? [latest] : []}
                    isRanked={isRanked}
                    removingIds={new Set(['latest', 'removing'])}
                    empty="No commits"
                    end="All commits"
                />
            ),
            render: renderToString,
            setup: ({ App, props }) => <App {...props} />,
        });

        assert.ok(result);
        const articles = result.body.match(/<article\b[^>]*>/gu) ?? [];
        assert.equal(articles.length, 3);
        for (const id of ['latest', 'removing', 'kept']) {
            const matches: string[] = articles.filter((article) =>
                article.includes(`data-commit="${id}"`),
            );
            assert.equal(matches.length, 1);
            assert.match(
                matches[0],
                id === 'kept' ? /grid-rows-\[1fr\]/u : /grid-rows-\[0fr\]/u,
            );
        }
    });
}
