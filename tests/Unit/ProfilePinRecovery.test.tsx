import assert from 'node:assert/strict';
import { afterEach, beforeEach, test, vi } from 'vite-plus/test';
import type { Commit, CursorPaginated } from '../../resources/js/types';
import { fakeConnection, joinAll } from './FakeRealtime';
import { hookHarness, find } from './HookHarness';

const harness = hookHarness();
let viewer = 'viewer';
let fake: ReturnType<typeof fakeConnection>;
let answer: () => Promise<Response>;
let reads: string[];
const flashes = new Set<
    (event: { detail: { flash: Record<string, unknown> } }) => void
>();
vi.doMock('react', () => harness.react);
vi.doMock('@inertiajs/react', async () => ({
    ...(await vi.importActual('@inertiajs/react')),
    usePage: () => ({
        props: { auth: { user: { id: viewer }, pasteis: null }, flash: {} },
    }),
    router: {
        on: (
            _event: string,
            callback: (event: {
                detail: { flash: Record<string, unknown> };
            }) => void,
        ) => {
            if (_event === 'flash') flashes.add(callback);
            return () => flashes.delete(callback);
        },
    },
}));
vi.doMock('../../resources/js/hooks/use-live-stream', () => ({
    useLiveStream: () => null,
}));
const { default: Show } = await import('../../resources/js/pages/user/show');
const { connectRealtime } = await import('../../resources/js/lib/realtime');
const { deletedResources } =
    await import('../../resources/js/lib/deleted-resources');
const commit = (id: string, minute: string) =>
    ({ id, created_at: `2026-11-03T10:${minute}:00Z` }) as Commit;
const pin = commit('old-pin', '20');
const props = {
    user: {
        id: 'viewer',
        handle: 'viewer',
        name: 'Viewer',
        lane: 1,
        followers_count: 0,
        following_count: 0,
        created_at: '2026-01-01T00:00:00Z',
        bio: '',
    },
    bioProfiles: {},
    pasteisReceived: null,
    isFollowing: false,
    followsYou: false,
    isMuting: false,
    followersYouKnow: null,
    canPush: false,
    moderation: null,
    activity: {},
    streaks: {},
    achievements: {},
    commits: {
        data: [commit('newer', '30'), commit('older', '10')],
        next_cursor: null,
    } as CursorPaginated<Commit>,
    tab: 'commits',
    pinnedCommit: pin,
    readme: null,
} as unknown as Parameters<typeof Show>[0];
function render(pinnedCommit: Commit | null, profileId = props.user.id) {
    const tree = harness.render(Show, {
        ...props,
        user: { ...props.user, id: profileId },
        pinnedCommit,
    });
    const feed = find(tree, (p) => 'commits' in p && 'pinnedCommit' in p);
    assert.ok(feed);
    return (feed.props.commits as CursorPaginated<Commit>).data.map(
        ({ id }) => id,
    );
}
beforeEach(() => {
    vi.useFakeTimers();
    viewer = 'viewer';
    reads = [];
    answer = async () =>
        Response.json({ status: 'approved', is_checking: false });
    vi.stubGlobal('fetch', async (url: string) => {
        reads.push(url);
        return answer();
    });
    fake = fakeConnection();
    connectRealtime(fake.connection);
});
afterEach(() => {
    connectRealtime(null);
    vi.unstubAllGlobals();
    vi.useRealTimers();
    harness.unmount();
    deletedResources.restoreCommit(pin.id);
    flashes.clear();
});

test('a real unpin returns, local deletion hides it, and undo brings it back', async () => {
    render(pin);
    render(null);
    render(null);
    await vi.advanceTimersByTimeAsync(300);
    assert.deepEqual(render(null), ['newer', 'old-pin', 'older']);
    flashes.forEach((notify) =>
        notify({ detail: { flash: { deleted: pin } } }),
    );
    assert.deepEqual(render(null), ['newer', 'older']);
    flashes.forEach((notify) =>
        notify({ detail: { flash: { restored: pin } } }),
    );
    assert.deepEqual(render(null), ['newer', 'old-pin', 'older']);
});

test('a former pin hidden by another device does not become an active feed card', () => {
    render(pin);
    deletedResources.markCommit(pin.id);
    render(null);
    assert.deepEqual(render(null), ['newer', 'older']);
});

test('a disappearing pin waits for authorization and a missed deletion never returns', async () => {
    let resolve!: (value: Response) => void;
    answer = () =>
        new Promise((done) => {
            resolve = done;
        });
    render(pin);
    render(null);
    assert.deepEqual(render(null), ['newer', 'older']);
    await vi.advanceTimersByTimeAsync(300);
    assert.equal(reads.length, 1);
    resolve(new Response(null, { status: 404 }));
    await vi.advanceTimersByTimeAsync(0);
    assert.deepEqual(render(null), ['newer', 'older']);
    assert.equal(deletedResources.hasCommit(pin.id), true);
});

for (const failure of ['http', 'network', 'json']) {
    test(`a failed ${failure} availability read retries on rejoin without returning an unverified pin`, async () => {
        answer = async () => {
            if (failure === 'network') throw new Error('offline');
            return failure === 'http'
                ? new Response(null, { status: 503 })
                : new Response('{', {
                      headers: { 'Content-Type': 'application/json' },
                  });
        };
        render(pin);
        render(null);
        render(null);
        await vi.advanceTimersByTimeAsync(300);
        assert.deepEqual(render(null), ['newer', 'older']);
        assert.equal(deletedResources.hasCommit(pin.id), false);
        answer = async () =>
            Response.json({ status: 'approved', is_checking: false });
        joinAll(fake);
        await vi.advanceTimersByTimeAsync(300);
        assert.deepEqual(render(null), ['newer', 'old-pin', 'older']);
        assert.equal(reads.length, 2);
    });
}

for (const retired of ['unmount', 'viewer', 'profile']) {
    test(`an availability response after ${retired} cannot mark the former pin unavailable`, async () => {
        let resolve!: (value: Response) => void;
        answer = () =>
            new Promise((done) => {
                resolve = done;
            });
        render(pin);
        render(null);
        render(null);
        await vi.advanceTimersByTimeAsync(300);
        assert.equal(reads.length, 1);
        if (retired === 'unmount') harness.unmount();
        else if (retired === 'profile') {
            render(null, 'other-profile');
            render(null, 'other-profile');
        } else {
            viewer = 'other';
            render(null);
            render(null);
        }
        resolve(new Response(null, { status: 404 }));
        await vi.advanceTimersByTimeAsync(0);
        assert.equal(deletedResources.hasCommit(pin.id), false);
    });
}

test('a second disappearance cannot reuse availability from a previous unpin', async () => {
    render(pin);
    render(null);
    render(null);
    await vi.advanceTimersByTimeAsync(300);
    assert.deepEqual(render(null), ['newer', 'old-pin', 'older']);
    render(pin);
    render(pin);
    answer = async () => new Response(null, { status: 404 });
    render(null);
    assert.deepEqual(render(null), ['newer', 'older']);
    await vi.advanceTimersByTimeAsync(300);
    assert.deepEqual(render(null), ['newer', 'older']);
});

test('a delete heard during availability lookup retires the earlier success', async () => {
    let resolve!: (value: Response) => void;
    answer = () =>
        new Promise((done) => {
            resolve = done;
        });
    render(pin);
    render(null);
    render(null);
    await vi.advanceTimersByTimeAsync(300);
    flashes.forEach((notify) =>
        notify({ detail: { flash: { deleted: pin } } }),
    );
    resolve(Response.json({ status: 'approved', is_checking: false }));
    await vi.advanceTimersByTimeAsync(0);
    assert.equal(deletedResources.hasCommit(pin.id), true);
    assert.deepEqual(render(null), ['newer', 'older']);
    answer = async () => new Response(null, { status: 404 });
    await vi.advanceTimersByTimeAsync(300);
    assert.deepEqual(render(null), ['newer', 'older']);
});
