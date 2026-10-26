/// <reference types="node" />

import assert from 'node:assert/strict';
import { createInertiaApp } from '@inertiajs/react';
import { renderToString } from 'react-dom/server';
import { test } from 'vite-plus/test';
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
            handle: 'author',
            lane: 1,
            timezone: null,
            show_local_time: false,
        },
    }) as Commit;

async function renderThread(
    count: number,
    repliesPerPage: number,
    { isOnTrunk = false }: { isOnTrunk?: boolean } = {},
) {
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
                <ThreadReplies
                    thread={thread}
                    depth={0}
                    isOnTrunk={isOnTrunk}
                />
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

    test(`shows ${shown} replies immediately out of ${count} when showing ${repliesPerPage} at a time`, async () => {
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
    test(`offers "${more}"${all ? ` and "${all}"` : ''} with ${count - repliesPerPage} of ${count} replies left`, async () => {
        const html = text(await renderThread(count, repliesPerPage));
        assert.match(html, new RegExp(more));
        assert.equal(
            all === null ? !html.includes('Show all') : html.includes(all),
            true,
        );
    });
}

const pieces = (html: string, name: string) =>
    html.match(new RegExp(`<[^>]*data-test="${name}"[^>]*>`, 'g')) ?? [];

test('hangs every reply off the parent with an elbow of its own, as the graph canvas does', async () => {
    const html = await renderThread(3, 3);

    assert.equal(pieces(html, 'thread-elbow').length, 3);
    assert.equal(pieces(html, 'reply-link').length, 0);
});

test("runs the parent's column on from each elbow to the next one, down to the more replies dot", async () => {
    assert.equal(pieces(await renderThread(3, 3), 'thread-column').length, 2);
    assert.equal(pieces(await renderThread(5, 3), 'thread-column').length, 4);
});

test("puts the more replies dot on the parent's column", async () => {
    const [row] = pieces(await renderThread(5, 3), 'more-replies');

    assert.ok(row);
    assert.match(row, /ml-\[calc\(var\(--spine\)-12px\)\]/);
});

test('branches the replies off a trunk that goes on below them, without a column of their own', async () => {
    const html = await renderThread(5, 3, { isOnTrunk: true });

    assert.equal(pieces(html, 'thread-column').length, 0);
    assert.equal(pieces(html, 'thread-elbow').length, 4);
    assert.doesNotMatch(pieces(html, 'more-replies')[0] ?? '', /--spine/);
    assert.doesNotMatch(html, /d="M 0 0 /);
});
