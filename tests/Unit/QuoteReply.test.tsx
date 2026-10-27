/// <reference types="node" />
import assert from 'node:assert/strict';
import { createInertiaApp } from '@inertiajs/react';
import { renderToString } from 'react-dom/server';
import { test } from 'vite-plus/test';
import {
    ThreadReplies,
    useThread,
} from '../../resources/js/components/commit-thread';
import { TooltipProvider } from '../../resources/js/components/ui/tooltip';
import type { Commit } from '../../resources/js/types';

const original = {
    id: 'original',
    user_id: 'author',
    parent_id: null,
    quoted_commit_id: null,
    created_at: new Date().toISOString(),
    status: 'approved',
    body: 'The original commit',
    body_segments: [{ type: 'text', text: 'The original commit' }],
    user: { id: 'author', name: 'Author', handle: 'author', lane: 1 },
} as Commit;

const reply = (id: string, overrides: Partial<Commit>): Commit =>
    ({
        ...original,
        id,
        user_id: id,
        body: `Body of ${id}`,
        body_segments: [{ type: 'text', text: `Body of ${id}` }],
        preview_segments: null,
        replies_count: 0,
        user: { id, name: id, handle: id, lane: 2 },
        ...overrides,
    }) as Commit;

async function renderReplies(replies: Commit[], automaticTranslation = true) {
    const commit = {
        ...original,
        replies_count: replies.length,
        reply_preview: {
            data: replies,
            next_cursor: null,
            prev_cursor: null,
        },
    };

    function Preview() {
        const thread = useThread(commit);

        return (
            <TooltipProvider>
                <ThreadReplies thread={thread} depth={0} />
            </TooltipProvider>
        );
    }

    const result = await createInertiaApp({
        page: {
            component: 'test',
            props: {
                auth: {
                    user: automaticTranslation ? null : original.user,
                    automaticTranslation,
                },
                errors: {},
            },
            url: '/',
            version: null,
            clearHistory: false,
            encryptHistory: false,
            rescuedProps: [],
            rememberedState: {},
            flash: {},
        },
        resolve: () => Preview,
        render: renderToString,
        setup: ({ App, props }) => <App {...props} />,
    });

    assert.ok(result);

    return result.body;
}

test('labels a quote listed under the commit it quotes and links to it', async () => {
    const html = await renderReplies([
        reply('replier', { parent_id: 'original' }),
        reply('quoter', { quoted_commit_id: 'original' }),
    ]);

    const labels = [
        ...html.matchAll(/<a[^>]*data-test="quote-reply-label"[^>]*>/g),
    ];

    assert.equal(labels.length, 1);
    assert.match(labels[0][0], /href="\/@quoter\/commits\/quoter"/);
    assert.match(html, /data-test="quote-reply-label"[^>]*>quoted<\/a>/);
});

test('shows cached translations to guests while leaving other replies in their original language', async () => {
    const html = await renderReplies([
        reply('translated', {
            parent_id: 'original',
            translation: {
                language: 'en',
                source_language: 'de',
                source_name: 'German',
                available: true,
                translated: {
                    body_segments: [{ type: 'text', text: 'Good morning' }],
                    preview_segments: null,
                },
            },
        }),
        reply('untranslated', { parent_id: 'original' }),
    ]);

    assert.match(html, /Good morning/);
    assert.match(html, /Translated from German/);
    assert.match(html, /Show original/);
    assert.doesNotMatch(html, /Body of translated/);
    assert.match(html, /Body of untranslated/);
});

test('keeps cached translations hidden until requested when automatic translation is off', async () => {
    const html = await renderReplies(
        [
            reply('chinese', {
                parent_id: 'original',
                body: '今天发布了新版本。',
                body_segments: [{ type: 'text', text: '今天发布了新版本。' }],
                translation: {
                    language: 'en',
                    source_language: 'zh',
                    source_name: 'Chinese',
                    available: true,
                    translated: {
                        body_segments: [
                            {
                                type: 'text',
                                text: 'Released a new version today.',
                            },
                        ],
                        preview_segments: null,
                    },
                },
            }),
        ],
        false,
    );

    assert.match(html, /今天发布了新版本。/);
    assert.doesNotMatch(html, /Released a new version today/);
    assert.doesNotMatch(html, /Translated from Chinese/);
    assert.doesNotMatch(html, /Show original/);
    assert.match(html, /aria-label="Translate commit"/);
    assert.doesNotMatch(html, /disabled=""[^>]*aria-label="Translate commit"/);
});
