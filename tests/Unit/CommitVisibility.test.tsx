/// <reference types="node" />

import assert from 'node:assert/strict';
import type { ReactNode } from 'react';
import { createInertiaApp } from '@inertiajs/react';
import { renderToString } from 'react-dom/server';
import { test } from 'vite-plus/test';
import { CommitCard } from '../../resources/js/components/commit-card';
import { CommitChannelBadges } from '../../resources/js/components/commit-channel-badges';
import {
    ThreadReplies,
    useThread,
} from '../../resources/js/components/commit-thread';
import { QuotedCommit } from '../../resources/js/components/quoted-commit';
import { TooltipProvider } from '../../resources/js/components/ui/tooltip';
import type { Commit } from '../../resources/js/types';

const commit = (id: string, overrides: Partial<Commit> = {}): Commit =>
    ({
        id,
        user_id: 'author',
        parent_id: null,
        quoted_commit_id: null,
        status: 'approved',
        created_at: '2026-10-26T12:00:00.000000Z',
        body: `Content of ${id}`,
        body_segments: [{ type: 'text', text: `Content of ${id}` }],
        preview_segments: null,
        mentions: null,
        channels: [],
        is_filtered: false,
        stars_count: 0,
        pasteis_count: 0,
        starred: false,
        user: {
            id: 'author',
            name: 'Author',
            username: 'author',
            lane: 1,
            timezone: null,
            show_local_time: false,
        },
        ...overrides,
    }) as Commit;

async function render(content: ReactNode): Promise<string> {
    const result = await createInertiaApp({
        page: {
            component: 'test',
            props: {
                auth: { user: null, automaticTranslation: false },
                errors: {},
                repliesPerPage: 3,
            },
            url: '/',
            version: null,
            clearHistory: false,
            encryptHistory: false,
            rescuedProps: [],
            rememberedState: {},
            flash: {},
        },
        resolve: () => () => <TooltipProvider>{content}</TooltipProvider>,
        render: renderToString,
        setup: ({ App, props }) => <App {...props} />,
    });

    assert.ok(result);
    return result.body.replace(/<!-- -->/g, '');
}

function Replies({ parent }: { parent: Commit }) {
    const thread = useThread(parent);
    return <ThreadReplies thread={thread} depth={0} />;
}

test('identifies each channel with its short badge and accessible explanation', () => {
    const html = renderToString(
        <CommitChannelBadges channels={['cli', 'ci', 'ai', 'bot']} />,
    );

    assert.equal(html.match(/data-test="commit-channel"/g)?.length, 4);
    assert.match(html, /aria-label="cli: Posted from a terminal"/);
    assert.match(html, /aria-label="ci: Posted from a workflow"/);
    assert.match(html, /aria-label="ai: AI-generated content/);
    assert.match(html, /aria-label="bot: An automated reply/);
    assert.equal(renderToString(<CommitChannelBadges />), '');
});

test('replaces a filtered commit body and engagement controls with an explicit reveal action', async () => {
    const html = await render(
        <CommitCard
            commit={commit('hidden', {
                is_filtered: true,
                channels: ['cli', 'ai'],
                images: [
                    {
                        id: 'hidden-image',
                        url: '/hidden-media.webp',
                        description: 'Hidden image description',
                        width: 400,
                        height: 300,
                    },
                ],
            })}
        />,
    );

    assert.match(html, /Commit hidden by your visibility settings/);
    assert.match(html, /data-test="show-filtered-commit"/);
    assert.doesNotMatch(html, /Content of hidden|hidden-media|Hidden image/);
    assert.doesNotMatch(html, /data-test="star-commit-button"/);
    assert.equal(html.match(/data-test="commit-channel"/g)?.length, 2);
});

test('keeps visible descendants beneath a filtered reply', async () => {
    const child = commit('visible-child', {
        parent_id: 'hidden-reply',
        replies_count: 0,
    });
    const hidden = commit('hidden-reply', {
        parent_id: 'parent',
        is_filtered: true,
        channels: ['ci'],
        replies_count: 1,
        reply_preview: {
            data: [child],
            next_cursor: null,
            prev_cursor: null,
        },
    });
    const html = await render(
        <Replies
            parent={commit('parent', {
                replies_count: 1,
                reply_preview: {
                    data: [hidden],
                    next_cursor: null,
                    prev_cursor: null,
                },
            })}
        />,
    );

    assert.match(html, /Reply hidden by your visibility settings/);
    assert.match(html, /Content of visible-child/);
    assert.doesNotMatch(html, /Content of hidden-reply/);
    assert.match(html, /aria-label="ci: Posted from a workflow"/);
});

test('renders a filtered quote as a reveal placeholder without leaking its content or nesting controls in a link', async () => {
    const html = await render(
        <QuotedCommit commit={commit('hidden-quote', { is_filtered: true })} />,
    );

    assert.match(html, /data-test="quoted-commit-filtered"/);
    assert.match(html, /Show commit/);
    assert.doesNotMatch(html, /Content of hidden-quote/);
    assert.doesNotMatch(html, /<a\b/);
});
