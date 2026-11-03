/// <reference types="node" />
import assert from 'node:assert/strict';
import { createHash } from 'node:crypto';
import { afterEach, beforeEach, test, vi } from 'vite-plus/test';
import { fakeConnection, joinAll } from './FakeRealtime';
import { find, hookHarness } from './HookHarness';

const harness = hookHarness();
type Reload = {
    only?: string[];
    data?: Record<string, unknown>;
    onSuccess?: (page: { props: Record<string, unknown> }) => void;
    onFinish?: () => void;
};
const reloads: Reload[] = [];
let digests: Record<string, string> = {};

vi.doMock('react', () => harness.react);
vi.doMock('@inertiajs/react', async () => ({
    ...(await vi.importActual('@inertiajs/react')),
    usePage: () => ({ props: { auth: { user: { id: 'me', handle: 'me' } } } }),
    router: {
        reload: (options: Reload) => {
            reloads.push(options);

            if (options.only?.includes('loadedDigest')) {
                options.onSuccess?.({ props: { loadedDigest: digests } });
            }

            options.onFinish?.();
        },
        on: () => () => {},
    },
}));

const { connectRealtime } = await import('../../resources/js/lib/realtime');
const { default: Connections } = await import('../../resources/js/pages/user/connections');

type Props = Parameters<typeof Connections>[0];
const digestOf = (...ids: string[]) => createHash('sha256').update([...ids].sort().join(',')).digest('hex');
const props = {
    user: { id: 'profile', handle: 'profile', name: 'Profile' },
    type: 'followers',
    users: { data: [{ id: 'a', handle: 'a', name: 'A' }, { id: 'b', handle: 'b', name: 'B' }], next_cursor: null, prev_cursor: null },
} as unknown as Props;

let fake: ReturnType<typeof fakeConnection>;

beforeEach(() => {
    vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout', 'setInterval', 'clearInterval', 'Date'] });
    reloads.length = 0;
    fake = fakeConnection();
    connectRealtime(fake.connection);
});

afterEach(() => {
    harness.unmount();
    connectRealtime(null);
    vi.useRealTimers();
});

// Hashing runs off the event loop's timers, so it is waited for for real.
async function settle() {
    await vi.advanceTimersByTimeAsync(50);
    for (let i = 0; i < 5; i++) {
        await new Promise((resolve) => setImmediate(resolve));
    }
}

const offered = () => {
    const tree = harness.render(Connections, props);

    return find(tree, (props) => props.children === 'Refresh the list') !== null;
};

test('a list read before it listened offers a refresh when the people it loaded changed meanwhile', async () => {
    digests = { users: digestOf('a', 'replacement') };
    void harness.render(Connections, props);
    joinAll(fake);
    await settle();

    assert.deepEqual(reloads[0]?.data, { loaded: { users: 2, organizations: 0 } });
    assert.equal(offered(), true);
});

test('a list read before it listened stays as it is when nobody it loaded changed', async () => {
    digests = { users: digestOf('b', 'a') };
    void harness.render(Connections, props);
    joinAll(fake);
    await settle();

    assert.equal(reloads.length, 1);
    assert.equal(offered(), false);
});

test('a follow heard while the list is open is offered as a refresh', async () => {
    digests = { users: digestOf('a', 'b') };
    void harness.render(Connections, props);
    joinAll(fake);
    await settle();
    fake.joined.get('profiles.profile')?.emit('profile', { connections: true });

    assert.equal(offered(), true);
});
