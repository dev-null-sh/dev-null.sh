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
// Hashing finishes on the thread pool, whenever it does: every digest asked for is waited for until it has, so a late
// one can't land in the next test.
const hashing = new Set<Promise<ArrayBuffer>>();
const digest = globalThis.crypto.subtle.digest.bind(globalThis.crypto.subtle);
vi.spyOn(globalThis.crypto.subtle, 'digest').mockImplementation((...args: Parameters<SubtleCrypto['digest']>) => {
    const hashed = digest(...args);
    hashing.add(hashed);
    void hashed.finally(() => hashing.delete(hashed));

    return hashed;
});

async function hashed() {
    while (hashing.size > 0) {
        await Promise.allSettled(hashing);
        await new Promise((resolve) => setImmediate(resolve));
    }
}

beforeEach(() => {
    vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout', 'setInterval', 'clearInterval', 'Date'] });
    reloads.length = 0;
    fake = fakeConnection();
    connectRealtime(fake.connection);
});

afterEach(async () => {
    await hashed();
    harness.unmount();
    connectRealtime(null);
    vi.useRealTimers();
});

async function settle() {
    await vi.advanceTimersByTimeAsync(50);
    await hashed();
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
