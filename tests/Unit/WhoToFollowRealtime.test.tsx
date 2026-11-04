import assert from 'node:assert/strict';
import { afterEach, beforeEach, test, vi } from 'vite-plus/test';
import type { SuggestedUser } from '../../resources/js/types';
import { fakeConnection, joinAll } from './FakeRealtime';
import { hookHarness } from './HookHarness';

const harness = hookHarness();
let viewer: { id: string } | null;
let suggestedUsers: SuggestedUser[];
let states: { id: string; is_following: boolean; is_dismissed: boolean }[];
let visibility: EventTarget & { hidden: boolean };
let reads: string[][];
let topUps: number;
let fake: ReturnType<typeof fakeConnection>;

vi.doMock('react', async () => ({
    ...(await vi.importActual('react')),
    ...harness.react,
}));
vi.doMock('@inertiajs/react', async () => ({
    ...(await vi.importActual('@inertiajs/react')),
    usePage: () => ({
        props: {
            auth: { user: viewer },
            suggestedUsers,
            showsWhoToFollow: true,
        },
    }),
    router: {
        replaceProp: (
            _key: string,
            update: (current: SuggestedUser[]) => SuggestedUser[],
        ) => {
            suggestedUsers = update(suggestedUsers);
        },
        reload: (options: {
            onBeforeUpdate?: () => void;
            onFinish?: () => void;
        }) => {
            topUps++;
            options.onBeforeUpdate?.();
            options.onFinish?.();
        },
    },
}));

const { WhoToFollow } =
    await import('../../resources/js/components/who-to-follow');
const { refreshSharedState, sharedValue } =
    await import('../../resources/js/hooks/use-shared-state');
const { connectRealtime, listen, pageReadAt, userChannel } =
    await import('../../resources/js/lib/realtime');
const render = () => {
    void harness.render(WhoToFollow, {});
};
const settle = () => vi.advanceTimersByTimeAsync(200);

beforeEach(() => {
    vi.useFakeTimers();
    viewer = { id: 'viewer' };
    suggestedUsers = ['a', 'b', 'c', 'd', 'e', 'f'].map(
        (id) => ({ id, handle: id, name: id }) as SuggestedUser,
    );
    states = suggestedUsers.map(({ id }) => ({
        id,
        is_following: false,
        is_dismissed: false,
    }));
    refreshSharedState(
        new Map<string, string | boolean | number>([
            ...suggestedUsers.map(
                ({ id }) => [`is_following:${id}`, false] as [string, boolean],
            ),
            ['who-to-follow-dismissing', 0],
            ['who-to-follow-top-up', 'idle'],
            ['who-to-follow-dismissed', false],
        ]),
    );
    reads = [];
    topUps = 0;
    visibility = Object.assign(new EventTarget(), {
        hidden: false,
        cookie: '',
    });
    vi.stubGlobal('document', visibility);
    vi.stubGlobal('window', { setTimeout, clearTimeout });
    vi.stubGlobal('fetch', async (url: string) => {
        reads.push([...new URL(url, 'http://localhost').searchParams.values()]);
        return Response.json({ users: states });
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

test('an open sidebar reconciles follows on join and relationship events without changing its rows', async () => {
    states[0].is_following = true;
    const rows = [...suggestedUsers];
    render();
    joinAll(fake);
    await settle();

    assert.deepEqual(reads, [['a', 'b', 'c', 'd', 'e', 'f']]);
    assert.equal(sharedValue('is_following:a'), true);
    states[0].is_following = false;
    fake.joined.get('private-users.viewer')?.emit('relationships');
    await settle();

    assert.equal(sharedValue('is_following:a'), false);
    assert.deepEqual(suggestedUsers, rows);
    assert.equal(topUps, 0);
});

test('a dismissal heard elsewhere fills only that slot from the spares', async () => {
    render();
    joinAll(fake);
    await settle();
    states[1].is_dismissed = true;
    fake.joined.get('private-users.viewer')?.emit('suggestions');
    await settle();

    assert.deepEqual(
        suggestedUsers.slice(0, 3).map((user) => user.id),
        ['a', 'd', 'c'],
    );
    assert.equal(topUps, 1);
});

test('rejoining recovers missed follows and dismissals even when the counts match', async () => {
    render();
    joinAll(fake);
    await settle();
    states[2].is_following = true;
    states[0].is_dismissed = true;
    fake.joined.get('private-users.viewer')?.join();
    await settle();

    assert.equal(sharedValue('is_following:c'), true);
    assert.deepEqual(
        suggestedUsers.slice(0, 3).map((user) => user.id),
        ['d', 'b', 'c'],
    );
});

test('hidden tabs defer a burst of changes until shown, then make one batched read', async () => {
    render();
    joinAll(fake);
    await settle();
    reads.length = 0;
    visibility.hidden = true;
    states[0].is_following = true;
    fake.joined.get('private-users.viewer')?.emit('relationships');
    fake.joined.get('private-users.viewer')?.emit('suggestions');
    await settle();
    assert.equal(reads.length, 0);
    visibility.hidden = false;
    visibility.dispatchEvent(new Event('visibilitychange'));
    await settle();

    assert.equal(reads.length, 1);
    assert.equal(sharedValue('is_following:a'), true);
});

test('an old account response cannot change the next accounts buttons or suggestions', async () => {
    let answer: (response: Response) => void = () => {};
    vi.stubGlobal(
        'fetch',
        () =>
            new Promise<Response>((resolve) => {
                answer = resolve;
            }),
    );
    render();
    joinAll(fake);
    await settle();
    viewer = { id: 'another-viewer' };
    render();
    answer(
        Response.json({
            users: [{ id: 'a', is_following: true, is_dismissed: true }],
        }),
    );
    await settle();

    assert.equal(sharedValue('is_following:a'), false);
    assert.deepEqual(
        suggestedUsers.map((user) => user.id),
        ['a', 'b', 'c', 'd', 'e', 'f'],
    );
    assert.equal(topUps, 0);
});

test('a failed read retries when the channel rejoins', async () => {
    let failing = true;
    vi.stubGlobal('fetch', async () =>
        failing
            ? new Response(null, { status: 503 })
            : Response.json({ users: states }),
    );
    render();
    joinAll(fake);
    await settle();
    failing = false;
    states[0].is_following = true;
    fake.joined.get('private-users.viewer')?.join();
    await settle();

    assert.equal(sharedValue('is_following:a'), true);
});

test('guests never request private suggestion state', async () => {
    viewer = null;
    render();
    joinAll(fake);
    await settle();

    assert.deepEqual(reads, []);
});

test('returning with cached suggestions reads them even when the private channel was already joined', async () => {
    const stop = listen(userChannel('viewer'), {});
    joinAll(fake);
    pageReadAt(Date.now() + 1);
    states[0].is_following = true;
    render();
    await settle();

    assert.equal(sharedValue('is_following:a'), true);
    assert.equal(reads.length, 1);
    stop();
});
