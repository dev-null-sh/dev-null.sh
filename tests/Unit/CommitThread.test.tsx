/// <reference types="node" />

import assert from 'node:assert/strict';
import { test } from 'node:test';
import { createInertiaApp } from '@inertiajs/react';
import { renderToString } from 'react-dom/server';
import {
    ThreadActions,
    ThreadReplies,
    useThread,
} from '../../resources/js/components/commit-thread';
import { TooltipProvider } from '../../resources/js/components/ui/tooltip';
import type { Commit } from '../../resources/js/types';

const reply = (id: string): Commit =>
    ({
        id,
        user_id: 'author',
        parent_id: 'parent',
        quoted_commit_id: null,
        created_at: new Date().toISOString(),
        status: 'approved',
        body: `Visible reply ${id}`,
        body_segments: [{ type: 'text', text: `Visible reply ${id}` }],
        preview_segments: [{ type: 'text', text: `Visible reply ${id}` }],
        replies_count: 0,
        user: {
            id: 'author',
            name: 'Author',
            username: 'author',
            lane: 1,
            timezone: null,
            show_local_time: false,
        },
    }) as Commit;

async function renderThread(count: number, repliesPerPage: number) {
    const shown = Math.min(count, repliesPerPage);
    const commit = {
        ...reply('parent'),
        replies_count: count,
        reply_preview: {
            data: Array.from({ length: shown }, (_, i) => reply(String(i))),
            next_cursor: count > shown ? 'older-replies' : null,
            prev_cursor: null,
        },
    };

    function Preview() {
        const thread = useThread(commit);
        return (
            <TooltipProvider>
                <ThreadActions thread={thread} />
                <ThreadReplies thread={thread} depth={0} />
            </TooltipProvider>
        );
    }

    const result = await createInertiaApp({
        page: {
            component: 'test',
            props: { auth: { user: null }, errors: {}, repliesPerPage },
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

const text = (html: string) =>
    html
        .replace(/<!-- -->/g, '')
        .replace(/<[^>]+>/g, ' ')
        .replace(/\s+/g, ' ');

for (const [count, repliesPerPage] of [
    [0, 3],
    [1, 3],
    [3, 3],
    [5, 1],
    [23, 5],
]) {
    const shown = Math.min(count, repliesPerPage);

    void test(`shows ${shown} replies immediately out of ${count} when showing ${repliesPerPage} at a time`, async () => {
        const html = await renderThread(count, repliesPerPage);
        assert.equal((html.match(/Visible reply /g) ?? []).length, shown);
        assert.doesNotMatch(html, /data-test="toggle-replies-button"/);
        assert.equal(
            html.includes('data-test="more-replies-button"'),
            count > shown,
        );
    });
}

for (const [count, repliesPerPage, more, all] of [
    [23, 5, 'Show 5 more', 'Show all 18'],
    [5, 1, 'Show 1 more', 'Show all 4'],
    [6, 3, 'Show the last 3', null],
    [4, 3, 'Show the last reply', null],
] as const) {
    void test(`offers "${more}"${all ? ` and "${all}"` : ''} with ${count - repliesPerPage} of ${count} replies left`, async () => {
        const html = text(await renderThread(count, repliesPerPage));
        assert.match(html, new RegExp(more));
        assert.equal(
            all === null ? !html.includes('Show all') : html.includes(all),
            true,
        );
    });
}
