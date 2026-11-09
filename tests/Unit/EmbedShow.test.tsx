/// <reference types="node" />
import assert from 'node:assert/strict';
import { createInertiaApp } from '@inertiajs/react';
import type { ComponentType } from 'react';
import { renderToString } from 'react-dom/server';
import { test } from 'vite-plus/test';
import { TimeoutDialog } from '../../resources/js/components/timeout-dialog';
import { Toaster } from '../../resources/js/components/ui/sonner';
import { TooltipProvider } from '../../resources/js/components/ui/tooltip';
import type { EmbedNode } from '../../resources/js/pages/embed/show';
import type { Commit } from '../../resources/js/types/commit';

function commit(
    id: string,
    handle: string,
    body: string,
    parentId: string | null = null,
): Commit {
    return {
        id,
        parent_id: parentId,
        hot_score: 0,
        quoted_commit_id: null,
        body,
        mentions: {},
        body_segments: [{ type: 'text', text: body }],
        preview_segments: null,
        status: 'approved',
        created_at: '2026-11-08T10:00:00Z',
        updated_at: '2026-11-08T10:00:00Z',
        stars_count: 2,
        pasteis_count: 0,
        starred: false,
        author: {
            type: 'user',
            id: `${handle}-id`,
            handle,
            name: handle === 'mira' ? 'Mira Okafor' : 'Kofi Mensah',
            avatar_url: null,
            bio: null,
            location: null,
            website: null,
            company: null,
            has_sponsors_listing: false,
            timezone: null,
            show_local_time: false,
            lane: 3,
        } as Commit['author'],
    };
}

const node = (
    value: Commit | null,
    replies: EmbedNode[] = [],
    tombstone: EmbedNode['tombstone'] = null,
    more = 0,
): EmbedNode => ({
    id: value?.id ?? 'deleted-id',
    tombstone,
    commit: value,
    replies,
    more,
});

type ServerApp = Extract<
    Parameters<typeof createInertiaApp>[0],
    { render: unknown }
>;

async function serverRender(props: Record<string, unknown>): Promise<string> {
    // The card's props aren't the app's shared ones: it renders without the web group.
    const page = {
        component: 'embed/show',
        props,
        url: '/embed/root',
        version: null,
        clearHistory: false,
        encryptHistory: false,
    } as unknown as ServerApp['page'];

    const rendered: unknown = await createInertiaApp({
        page,
        render: renderToString,
        resolve: async () =>
            (await import('../../resources/js/pages/embed/show'))
                .default as ComponentType,
        // The same providers as resources/js/ssr.tsx, which renders every page, cards included.
        setup: ({ App, props: appProps }) => (
            <TooltipProvider delayDuration={150}>
                <App {...appProps} />
                <Toaster />
                <TimeoutDialog />
            </TooltipProvider>
        ),
    });

    const html =
        typeof rendered === 'object' && rendered !== null && 'body' in rendered
            ? String(rendered.body)
            : String(rendered);

    // React separates adjacent text with empty comments, which a reader never sees.
    return html.replaceAll('<!-- -->', '');
}

test('the card reads in full from the server, without JavaScript', async () => {
    const html = await serverRender({
        ancestors: [],
        focal: node(
            commit('root', 'mira', 'rgt 0.9 is out'),
            [
                node(commit('reply', 'kofi', 'Finally streaming', 'root')),
                node(null, [], 'deleted'),
            ],
            null,
            4,
        ),
        thread: [],
        options: {
            thread: false,
            replies: 3,
            depth: 3,
            theme: 'auto',
            maxHeight: 640,
        },
        auth: { user: null, pasteis: null, organizations: [], isAdmin: false },
        now: Date.parse('2026-11-08T12:00:00Z'),
        commitUrl: 'https://dev-null.sh/@mira/commits/root',
    });

    assert.match(html, /rgt 0\.9 is out/);
    assert.match(html, /Mira Okafor/);
    assert.match(html, /Finally streaming/);
    assert.match(html, /\[deleted\]/);
    // Replies shown but cut by the count end with "Show the last N", and their parent counts them nowhere else.
    assert.match(html, />Show the last 4<\/span><\/a>/);
    assert.doesNotMatch(html, /\d replies<\/a>/);
    assert.match(html, /href="https:\/\/dev-null\.sh\/@mira\/commits\/root"/);
    assert.match(html, /View on \/dev\/null/);
    assert.doesNotMatch(html, /mirrored live/);
});

test('the card reads like /dev/null: the thread badge after the date, then a star and the replies link', async () => {
    const html = await serverRender({
        ancestors: [],
        focal: node(commit('root', 'mira', 'rgt 0.9 is out'), [], null, 5),
        thread: [node(commit('next', 'mira', 'Before / after', 'root'))],
        options: {
            thread: true,
            replies: 3,
            depth: 3,
            theme: 'auto',
            maxHeight: 640,
        },
        auth: { user: null, pasteis: null, organizations: [], isAdmin: false },
        now: Date.parse('2026-11-08T12:00:00Z'),
        commitUrl: 'https://dev-null.sh/@mira/commits/root',
    });

    assert.match(
        html,
        /<a href="\/@mira"[^>]*>Mira Okafor<\/a><a href="\/@mira"[^>]*>@mira<\/a><\/span><span[^>]*>·<\/span><a href="\/@mira\/commits\/root"[^>]*><time[^>]*>Nov 8<\/time><\/a><span[^>]*data-test="thread-position"[^>]*>1\/2<\/span>/,
    );
    // The hash sits on the right of every header, the embedded commit's included, and its date line is a date only.
    assert.match(
        html,
        /1\/2<\/span><span data-commit-hash[^>]*><a href="\/@mira\/commits\/root"[^>]*>root<\/a><\/span><\/div>/,
    );
    assert.doesNotMatch(html, /· commit /);
    assert.match(html, /aria-label="Star commit on \/dev\/null, 2 stars"/);
    // Replies left out are counted once, by the actions row, like collapsed replies on /dev/null.
    assert.match(html, /<\/svg>5 replies<\/a>/);
    assert.doesNotMatch(html, /Show the last/);
    assert.doesNotMatch(html, /Show thread/);
});

function card(focal: EmbedNode, options: { thread: boolean; replies: number }) {
    return serverRender({
        ancestors: [],
        focal,
        thread: [],
        options: { ...options, depth: 3, theme: 'auto', maxHeight: 640 },
        auth: { user: null, pasteis: null, organizations: [], isAdmin: false },
        now: Date.parse('2026-11-08T12:00:00Z'),
        commitUrl: 'https://dev-null.sh/@mira/commits/root',
    });
}

test('a hidden thread leaves a Show thread link to /dev/null, like in the log', async () => {
    const root = {
        ...commit('root', 'mira', 'rgt 0.9 is out'),
        continuation_id: 'next',
    };

    assert.match(
        await card(node(root), { thread: false, replies: 0 }),
        /<a href="https:\/\/dev-null\.sh\/@mira\/commits\/root"[^>]*aria-label="Show thread by @mira"[^>]*>.*Show thread<\/a>/,
    );
    assert.doesNotMatch(
        await card(node(commit('root', 'mira', 'Alone')), {
            thread: false,
            replies: 0,
        }),
        /Show thread/,
    );
});

test('hidden replies leave the replies link of the actions row, to /dev/null', async () => {
    const hidden = await card(
        node(commit('root', 'mira', 'rgt'), [], null, 5),
        {
            thread: false,
            replies: 0,
        },
    );

    assert.match(
        hidden,
        /href="https:\/\/dev-null\.sh\/@mira\/commits\/root"[^>]*>(<svg.*?<\/svg>)5 replies<\/a>/,
    );
    assert.doesNotMatch(hidden, /Show the last/);
    assert.match(
        await card(node(commit('root', 'mira', 'rgt'), [], null, 1), {
            thread: false,
            replies: 0,
        }),
        /<\/svg>1 reply<\/a>/,
    );
});

test('a tombstone reads like a deleted reply on /dev/null, with the link to its hidden replies', async () => {
    const html = await card(
        node(commit('root', 'mira', 'rgt'), [
            node(null, [], 'deleted', 1),
            node(null, [], 'unavailable'),
        ]),
        { thread: false, replies: 3 },
    );

    assert.match(
        html,
        /\[deleted\]<\/span>This commit was deleted\.<\/p><div[^>]*><a[^>]*>(<svg.*?<\/svg>)1 reply<\/a>/,
    );
    // An author's choice to turn embeds off is theirs alone: the card never names it.
    assert.doesNotMatch(html, /embedding disabled|turned embeds off/);
    // The line is /dev/null's own, the one a deleted reply reads in the app.
    assert.equal(html.match(/data-test="tombstone-line"/g)?.length, 2);
    // A tombstone's lines are drawn in the app's rail color, which the card's geometry must not shadow.
    assert.doesNotMatch(html, /--rail:\s*\d/);
    assert.match(
        html,
        /\[unavailable\]<\/span>This commit isn(&#x27;|')t available\./,
    );
});

test('the last reply left out of an open level reads "Show the last reply"', async () => {
    assert.match(
        await card(
            node(
                commit('root', 'mira', 'rgt'),
                [node(commit('reply', 'kofi', 'Finally', 'root'))],
                null,
                1,
            ),
            { thread: false, replies: 1 },
        ),
        />Show the last reply<\/span><\/a>/,
    );
});

test('polls and link previews are the ones of /dev/null, leading there to vote', async () => {
    const html = await card(
        node({
            ...commit('root', 'mira', 'What should 1.0 focus on?'),
            poll: {
                id: 'poll',
                closes_at: '2999-01-01T00:00:00Z',
                is_closed: false,
                has_voted: false,
                total_votes: null,
                options: [
                    {
                        id: 'a',
                        label: 'Fuzzy file picker',
                        votes_count: null,
                        voted: false,
                    },
                    {
                        id: 'b',
                        label: 'Regex builder',
                        votes_count: null,
                        voted: false,
                    },
                ],
            },
            link_preview: {
                url: 'https://jun.dev/rgt',
                title: 'Wiring rgt into Neovim',
                description: null,
                image_url: null,
                site_name: 'jun.dev',
                is_pending: false,
            },
        }),
        { thread: false, replies: 0 },
    );

    assert.match(html, /data-test="commit-poll"/);
    assert.equal(
        html.match(
            /<a href="https:\/\/dev-null\.sh\/@mira\/commits\/root" class="block rounded-md border/g,
        )?.length,
        2,
    );
    assert.match(html, /Vote on \/dev\/null · /);
    assert.match(
        html,
        /<a href="https:\/\/jun\.dev\/rgt"[^>]*data-test="link-preview"/,
    );
});

test("the card's star, replies and thread links are /dev/null's own", async () => {
    const root = {
        ...commit('root', 'mira', 'rgt 0.9 is out'),
        continuation_id: 'next',
    };
    const html = await card(
        node(root, [node(commit('reply', 'kofi', 'Finally', 'root'))], null, 2),
        { thread: false, replies: 1 },
    );

    assert.match(
        html,
        /<a href="https:\/\/dev-null\.sh\/@mira\/commits\/root"[^>]*data-test="show-thread-link"/,
    );
    assert.match(html, /data-test="star-count"[^>]*>2<\/span>/);
    assert.match(
        html,
        /<a href="https:\/\/dev-null\.sh\/@mira\/commits\/root"[^>]*data-test="more-replies-button"[^>]*>.*Show the last 2/,
    );
});

test("the card's rail is /dev/null's: its graph canvas, rail and reply elbows", async () => {
    const html = await card(
        node(commit('root', 'mira', 'rgt'), [
            node(commit('reply', 'kofi', 'Finally', 'root')),
        ]),
        { thread: false, replies: 1 },
    );

    assert.match(html, /data-test="graph-canvas"/);
    assert.match(html, /data-test="commit-reply-spine"/);
    assert.match(html, /data-test="thread-elbow"/);
});

test("a quoted commit shows like on /dev/null, a plain link there, or unavailable when the card can't show it", async () => {
    const quoted = commit('quoted', 'kofi', 'The quoted one');
    const shown = await card(
        node({
            ...commit('root', 'mira', 'Quoting'),
            quoted_commit_id: 'quoted',
            quoted_commit: quoted,
        } as Commit),
        { thread: false, replies: 0 },
    );

    assert.match(
        shown,
        /<a href="\/@kofi\/commits\/quoted"[^>]*data-test="quoted-commit"/,
    );
    assert.match(shown, /The quoted one/);

    const hidden = await card(
        node({
            ...commit('root', 'mira', 'Quoting'),
            quoted_commit_id: 'quoted',
            quoted_commit: null,
        } as Commit),
        { thread: false, replies: 0 },
    );

    assert.match(hidden, /data-test="quoted-commit-tombstone"/);
    assert.match(hidden, /\[unavailable\]/);
});

test("a mention in the card is a plain link, without the app's profile preview", async () => {
    const html = await card(
        node({
            ...commit('root', 'mira', 'Thanks @kofi for the review'),
            mentions: { kofi: 'kofi-id' },
        } as Commit),
        { thread: false, replies: 0 },
    );

    assert.match(html, /Thanks <a[^>]*data-test="mention"[^>]*>@kofi<\/a>/);
});
