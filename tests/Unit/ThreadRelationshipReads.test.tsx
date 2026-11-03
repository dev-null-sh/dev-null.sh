/// <reference types="node" />
import assert from 'node:assert/strict';
import { afterEach, beforeEach, test, vi } from 'vite-plus/test';
import type { ThreadCommit } from '../../resources/js/types';
import { fakeConnection } from './FakeRealtime';
import { hookHarness } from './HookHarness';

const harness = hookHarness();
const visitListeners = { start: new Set<() => void>(), finish: new Set<() => void>() };
let viewer = 'a';

vi.doMock('react', () => harness.react);
vi.doMock('@inertiajs/react', async () => ({
    ...(await vi.importActual('@inertiajs/react')),
    usePage: () => ({
        props: { repliesPerPage: 2, continuation: null, now: Date.now(), auth: { user: { id: viewer } } },
    }),
    useRemember: (initial: unknown) => harness.react.useState(initial),
    router: {
        restore: () => undefined,
        reload: () => {},
        on: (event: 'start' | 'finish', listener: () => void) => {
            visitListeners[event]?.add(listener);

            return () => visitListeners[event]?.delete(listener);
        },
    },
}));

const { connectRealtime } = await import('../../resources/js/lib/realtime');
const { useThread } = await import('../../resources/js/components/commit-thread');
const { sharedValue } = await import('../../resources/js/hooks/use-shared-state');
const { commitStateKey } = await import('../../resources/js/lib/commit-state');

const reply = (id: string, parent = 'root') =>
    ({ id, replies_count: 0, deleted_at: null, parent_id: parent }) as unknown as ThreadCommit;
const visit = (event: 'start' | 'finish') => visitListeners[event].forEach((listener) => listener());

type Held = { commit: string; viewer: string; settle: (replies: string[]) => void };
let threads: Record<string, string[]>;
let held: Held[];
let holds: (commit: string) => boolean;
let replyReads: { commit: string; viewer: string; url: URL }[];
let fake: ReturnType<typeof fakeConnection>;

function page(commit: string, url: URL, ids: string[]) {
    const from = Number(url.searchParams.get('cursor') ?? 0);
    const limit = Number(url.searchParams.get('limit') ?? 2);

    return Response.json({
        data: ids.slice(from, from + limit).map((id) => reply(id, commit)),
        next_cursor: from + limit < ids.length ? String(from + limit) : null,
        parent: { replies_count: ids.length },
    });
}

beforeEach(() => {
    vi.useFakeTimers();
    viewer = 'a';
    held = [];
    holds = () => false;
    replyReads = [];
    threads = { root: ['r1', 'r2', 'r3', 'r4'] };
    vi.stubGlobal('fetch', async (input: string) => {
        const url = new URL(input, 'http://localhost');
        const commit = url.pathname.match(/^\/commits\/([^/]+)\/replies$/)?.[1];

        if (url.pathname === '/commits/changes') {
            return Response.json({ changes: {} });
        }

        if (commit === undefined) {
            return Response.json({});
        }

        const reader = viewer;
        replyReads.push({ commit, viewer: reader, url });

        if (holds(commit)) {
            return new Promise<Response>((resolve) => {
                held.push({ commit, viewer: reader, settle: (ids) => resolve(page(commit, url, ids)) });
            });
        }

        return page(commit, url, threads[commit] ?? []);
    });
    fake = fakeConnection();
    connectRealtime(fake.connection);
});

afterEach(() => {
    harness.unmount();
    connectRealtime(null);
    vi.unstubAllGlobals();
    vi.useRealTimers();
});

const initial = (ids: string[], parent = 'root', more = true) => ({
    data: ids.map((id) => reply(id, parent)),
    next_cursor: more ? '2' : null,
    prev_cursor: null,
});

type Thread = ReturnType<typeof useThread>;
type Component = () => { thread?: Thread } | null;
let shown: Thread;
let target = { id: 'root', replies_count: 4, deleted_at: null } as unknown as ThreadCommit;
const firstPage = initial(['r1', 'r2']);

function Probe() {
    return { thread: useThread(target, firstPage, false) };
}

const render = (component: Component) => {
    const rendered = harness.render(component as unknown as () => null, {}) as unknown as ReturnType<Component>;

    if (rendered?.thread) {
        shown = rendered.thread;
    }
};

async function settle(component: Component = Probe) {
    for (let i = 0; i < 4; i++) {
        await vi.advanceTimersByTimeAsync(50);
        render(component);
    }
}

const ids = () => shown.replies?.map((one) => one.id);
const relationshipsChanged = () => fake.joined.get(`private-users.${viewer}`)?.emit('relationships');

test('loading more while a mute waits for the visit that made it keeps every reply loaded', async () => {
    target = { id: 'root', replies_count: 4, deleted_at: null } as unknown as ThreadCommit;
    await settle();
    visit('start');
    relationshipsChanged();
    await settle();
    assert.equal(replyReads.length, 0, 'the visit goes first');

    shown.loadMore();
    await settle();
    assert.deepEqual(ids(), ['r1', 'r2', 'r3', 'r4']);

    visit('finish');
    await settle();

    assert.deepEqual(ids(), ['r1', 'r2', 'r3', 'r4']);
    assert.equal(shown.hasMore, false);
    assert.equal(replyReads.length, 3, 'Load more, then the four loaded read again a page at a time, and no further');
});

test('loading more while a mute waits its turn behind other threads keeps every reply loaded', async () => {
    target = { id: 'root', replies_count: 4, deleted_at: null } as unknown as ThreadCommit;
    threads = { ...threads, busy1: ['b1'], busy2: ['b2'] };
    const busy = (id: string) => ({ id, replies_count: 1, deleted_at: null }) as unknown as ThreadCommit;
    const busy1 = initial(['b1'], 'busy1', false);
    const busy2 = initial(['b2'], 'busy2', false);
    function Three() {
        useThread(busy('busy1'), busy1, false);
        useThread(busy('busy2'), busy2, false);

        return { thread: useThread(target, firstPage, false) };
    }

    await settle(Three);
    holds = (commit) => commit.startsWith('busy');
    relationshipsChanged();
    await settle(Three);
    assert.deepEqual(held.map((read) => read.commit).sort(), ['busy1', 'busy2'], 'two at a time');

    holds = () => false;
    shown.loadMore();
    await settle(Three);
    assert.deepEqual(ids(), ['r1', 'r2', 'r3', 'r4']);

    held.splice(0).forEach((read) => read.settle(threads[read.commit]));
    await settle(Three);

    assert.deepEqual(ids(), ['r1', 'r2', 'r3', 'r4']);
    assert.equal(shown.hasMore, false);
});

test('many changes heard while a read waits make one read', async () => {
    target = { id: 'root', replies_count: 4, deleted_at: null } as unknown as ThreadCommit;
    await settle();
    visit('start');
    relationshipsChanged();
    relationshipsChanged();
    relationshipsChanged();
    visit('finish');
    await settle();

    assert.equal(replyReads.length, 1);
});

test('a thread gone before its turn comes reads nothing, and changes no count', async () => {
    target = { id: 'gone', replies_count: 2, deleted_at: null } as unknown as ThreadCommit;
    threads = { gone: ['g1', 'g2'] };
    await settle();
    visit('start');
    relationshipsChanged();
    await settle();
    harness.unmount();
    viewer = 'b';
    visit('finish');
    await settle(() => null);

    assert.equal(replyReads.length, 0);
    assert.equal(sharedValue(commitStateKey('replies_count', 'gone')), undefined);
});

test('a read made for one account never lands once another is signed in, and what is shown stays', async () => {
    target = { id: 'owned', replies_count: 2, deleted_at: null } as unknown as ThreadCommit;
    threads = { owned: ['o1', 'o2'] };
    const owned = initial(['o1', 'o2'], 'owned', false);
    function Owned() {
        return { thread: useThread(target, owned, false) };
    }

    await settle(Owned);
    holds = () => true;
    relationshipsChanged();
    await settle(Owned);
    assert.deepEqual(held.map((read) => read.viewer), ['a']);

    viewer = 'b';
    await settle(Owned);
    held.find((read) => read.viewer === 'a')?.settle(['only-a-could-see']);
    await settle(Owned);

    assert.deepEqual(ids(), ['o1', 'o2']);
    assert.equal(shown.isLoading, false);
    assert.notEqual(sharedValue(commitStateKey('replies_count', 'owned')), 1);
});

test('a read made for one thread never lands on another the same place now shows', async () => {
    target = { id: 'first', replies_count: 2, deleted_at: null } as unknown as ThreadCommit;
    threads = { first: ['f1', 'f2'], second: ['s1', 's2'] };
    const pages: Record<string, ReturnType<typeof initial>> = {
        first: initial(['f1', 'f2'], 'first', false),
        second: initial(['s1', 's2'], 'second', false),
    };
    function Reused() {
        return { thread: useThread(target, pages[target.id], false) };
    }

    await settle(Reused);
    holds = () => true;
    relationshipsChanged();
    await settle(Reused);
    assert.deepEqual(held.map((read) => read.commit), ['first']);

    target = { id: 'second', replies_count: 2, deleted_at: null } as unknown as ThreadCommit;
    await settle(Reused);
    held.find((read) => read.commit === 'first')?.settle(['f-late']);
    await settle(Reused);

    assert.deepEqual(ids(), ['s1', 's2']);
});
