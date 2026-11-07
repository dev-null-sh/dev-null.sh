/// <reference types="node" />
import assert from 'node:assert/strict';
import { afterEach, beforeEach, test, vi } from 'vite-plus/test';
import type { ThreadCommit } from '../../resources/js/types';
import { fakeConnection, joinAll } from './FakeRealtime';
import { hookHarness } from './HookHarness';

const harness = hookHarness();

vi.doMock('react', () => harness.react);
vi.doMock('../../resources/js/hooks/use-media-query', () => ({
    useMediaQuery: () => false,
}));
vi.doMock('@inertiajs/react', async () => ({
    ...(await vi.importActual('@inertiajs/react')),
    usePage: () => ({
        props: {
            repliesPerPage: 2,
            continuation: null,
            now: Date.now(),
            auth: { user: { id: 'me' } },
        },
    }),
    useRemember: (initial: unknown) => harness.react.useState(initial),
    router: { restore: () => undefined, on: () => () => {}, reload: () => {} },
}));

const { connectRealtime } = await import('../../resources/js/lib/realtime');
const { useThread } = await import('../../resources/js/components/commit-thread');

const reply = (id: string) =>
    ({ id, replies_count: 0, deleted_at: null, parent_id: 'root' }) as unknown as ThreadCommit;
const root = { id: 'root', replies_count: 2, deleted_at: null } as unknown as ThreadCommit;

let server: string[];
let change: { changed: boolean; replies: string[]; truncated: boolean } | null;
let replyReads: URL[];
let fake: ReturnType<typeof fakeConnection>;
let thread: ReturnType<typeof useThread>;

type Initial = { data: ThreadCommit[]; next_cursor: null; prev_cursor: null };

function Probe(props: { initial: Initial }) {
    thread = useThread(root, props.initial, true);

    return null;
}

const initialOf = (ids: string[]): Initial => ({ data: ids.map(reply), next_cursor: null, prev_cursor: null });

beforeEach(() => {
    vi.useFakeTimers();
    replyReads = [];
    change = { changed: false, replies: [], truncated: false };
    vi.stubGlobal('fetch', async (input: string) => {
        const url = new URL(input, 'http://localhost');

        if (url.pathname === '/commits/changes') {
            return Response.json({ changes: { root: change } });
        }

        if (url.pathname === '/commits/root/replies') {
            replyReads.push(url);
            const ids = url.searchParams.getAll('ids[]');

            if (ids.length > 0) {
                return Response.json({ data: server.filter((id) => ids.includes(id)).map(reply), next_cursor: null, parent: { replies_count: server.length } });
            }

            const from = Number(url.searchParams.get('cursor') ?? 0);
            const limit = Number(url.searchParams.get('limit') ?? 2);
            const page = server.slice(from, from + limit);

            return Response.json({
                data: page.map(reply),
                next_cursor: from + limit < server.length ? String(from + limit) : null,
                parent: { replies_count: server.length },
            });
        }

        return Response.json({});
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

async function settle() {
    for (let i = 0; i < 5; i++) {
        await vi.advanceTimersByTimeAsync(300);
        void harness.render(Probe, props);
    }
}

let props = { initial: initialOf(['r1', 'r2']) };

const shownIds = () => thread.replies?.map((shown) => shown.id);

test('unmuting someone elsewhere brings their reply back into the open thread, where it belongs', async () => {
    props = { initial: initialOf(['r1', 'r2']) };
    server = ['r1', 'r2'];
    void harness.render(Probe, props);
    joinAll(fake);
    await settle();
    assert.deepEqual(shownIds(), ['r1', 'r2']);

    server = ['r1', 'unmuted', 'r2'];
    fake.joined.get('private-users.me')?.emit('relationships');
    await settle();

    assert.deepEqual(shownIds(), ['r1', 'unmuted', 'r2']);
});

test('muting someone while disconnected takes their reply out once the thread listens again', async () => {
    props = { initial: initialOf(['r1', 'muted', 'r2']) };
    server = ['r1', 'muted', 'r2'];
    void harness.render(Probe, props);
    joinAll(fake);
    await settle();

    fake.setStatus('unavailable');
    server = ['r1', 'r2'];
    fake.setStatus('connected');
    joinAll(fake);
    await settle();

    assert.deepEqual(shownIds(), ['r1', 'r2']);
});

test('catching up on many changes reads again only what was loaded, leaving a large new tail to load deliberately', async () => {
    props = { initial: initialOf(['r1', 'r2']) };
    server = ['r1', 'r2'];
    void harness.render(Probe, props);
    joinAll(fake);
    await settle();
    const before = replyReads.length;

    server = ['r1', 'r2', ...Array.from({ length: 3_000 }, (_, index) => `new-${index}`)];
    change = { changed: false, replies: [], truncated: true };
    fake.setStatus('unavailable');
    fake.setStatus('connected');
    joinAll(fake);
    await settle();

    assert.deepEqual(shownIds(), ['r1', 'r2']);
    assert.equal(thread.hasMore, true);
    assert.ok(replyReads.length - before <= 2, `read ${replyReads.length - before} pages`);
});
