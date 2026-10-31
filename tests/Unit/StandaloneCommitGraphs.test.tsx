/// <reference types="node" />

import assert from 'node:assert/strict';
import { createInertiaApp } from '@inertiajs/react';
import type { ComponentType } from 'react';
import { renderToString } from 'react-dom/server';
import { test } from 'vite-plus/test';
import { RelatedCommitList } from '../../resources/js/components/related-commits';
import { TooltipProvider } from '../../resources/js/components/ui/tooltip';
import Reports from '../../resources/js/pages/moderation/reports';
import { SearchResults } from '../../resources/js/pages/search/index';
import type { Commit } from '../../resources/js/types';

const commit = (id: string, lane: number): Commit =>
    ({
        id,
        user_id: `author-${id}`,
        parent_id: null,
        quoted_commit_id: null,
        created_at: new Date().toISOString(),
        status: 'approved',
        body: `Commit ${id}`,
        body_segments: [{ type: 'text', text: `Commit ${id}` }],
        preview_segments: [{ type: 'text', text: `Commit ${id}` }],
        replies_count: 0,
        user: {
            id: `author-${id}`,
            name: `Author ${id}`,
            handle: `author-${id}`,
            lane,
            timezone: null,
            show_local_time: false,
        },
    }) as Commit;

async function render(Page: ComponentType): Promise<string> {
    const result = await createInertiaApp({
        page: {
            component: 'test',
            props: { auth: { user: null }, errors: {} },
            url: '/',
            version: null,
            clearHistory: false,
            encryptHistory: false,
            rescuedProps: [],
            rememberedState: {},
            flash: {},
        },
        resolve: () => Page,
        render: renderToString,
        setup: ({ App, props }) => (
            <TooltipProvider>
                <App {...props} />
            </TooltipProvider>
        ),
    });

    assert.ok(result);
    return result.body;
}

const railPiece =
    /data-test="(rail-previous|rail-tail|thread-spine|thread-column|thread-elbow|commit-reply-spine)"/;

/** The rail pieces that would still show once the canvas draws: those outside the marks it hides. */
function railPiecesLeftShowing(html: string): number {
    const open: boolean[] = [];
    let left = 0;

    for (const [tag, closing, , , selfClosing] of html.matchAll(
        /<(\/?)([a-zA-Z]+)([^>]*?)(\/?)>/g,
    )) {
        if (closing) {
            open.pop();
            continue;
        }
        if (railPiece.test(tag) && !open.includes(true)) {
            left++;
        }
        if (!selfClosing) {
            open.push(tag.includes('data-graph-rail'));
        }
    }

    return left;
}

test('draws the graph of reported commits in a canvas, its rail standing in until it draws, each commit ending on its dot as in a feed', async () => {
    const html = await render(() => (
        <Reports
            reports={{
                data: [
                    {
                        id: 'report-1',
                        reason: 'spam',
                        details: null,
                        attachments: [],
                        created_at: new Date().toISOString(),
                        commit: commit('one', 1),
                    },
                    {
                        id: 'report-2',
                        reason: 'spam',
                        details: null,
                        attachments: [],
                        created_at: new Date().toISOString(),
                        commit: commit('two', 2),
                    },
                ],
                next_cursor: null,
                prev_cursor: null,
            }}
            reasons={{ spam: 'Spam' }}
        />
    ));

    assert.match(html, /data-test="graph-canvas"/);
    assert.match(html, railPiece);
    assert.equal(railPiecesLeftShowing(html), 0);
    assert.equal(html.match(/data-test="rail-end-dot"/g)?.length, 2);
});

test('draws the graph of search results in a canvas, its rail standing in until it draws', async () => {
    const html = await render(() => (
        <SearchResults
            results={{
                term: 'graph',
                commits: [commit('one', 1), commit('two', 2)],
            }}
            isWaiting={false}
        />
    ));

    assert.match(html, /data-test="graph-canvas"/);
    assert.match(html, railPiece);
    assert.equal(railPiecesLeftShowing(html), 0);
});

test('keeps the graph of each report apart, even when two people report the same commit', async () => {
    const report = (id: string) => ({
        id,
        reason: 'spam',
        details: null,
        attachments: [],
        created_at: new Date().toISOString(),
        commit: commit('one', 1),
    });
    const html = await render(() => (
        <Reports
            reports={{
                data: [report('report-1'), report('report-2')],
                next_cursor: null,
                prev_cursor: null,
            }}
            reasons={{ spam: 'Spam' }}
        />
    ));

    assert.equal(html.match(/data-test="graph-canvas"/g)?.length, 2);
});

test('ends the chain of search results on a dot below the last one', async () => {
    const html = await render(() => (
        <SearchResults
            results={{
                term: 'graph',
                commits: [
                    commit('one', 1),
                    commit('two', 2),
                    commit('three', 3),
                ],
            }}
            isWaiting={false}
        />
    ));

    assert.equal(html.match(/data-test="rail-end-dot"/g)?.length, 1);
    // In the last card: past everything the second one shows.
    assert.ok(
        html.indexOf('data-test="rail-end-dot"') >
            html.lastIndexOf('Commit two'),
    );
});

test('draws related commits in a canvas of their own, chained one after the other and ending on a dot below the last one', async () => {
    const html = await render(() => (
        <RelatedCommitList
            commits={[commit('one', 1), commit('two', 2), commit('three', 3)]}
        />
    ));

    assert.equal(html.match(/data-test="graph-canvas"/g)?.length, 1);
    assert.equal(railPiecesLeftShowing(html), 0);
    assert.equal(html.match(/data-test="rail-end-dot"/g)?.length, 1);
    // In the last card: past everything the second one shows.
    assert.ok(
        html.indexOf('data-test="rail-end-dot"') >
            html.lastIndexOf('Commit two'),
    );
});
