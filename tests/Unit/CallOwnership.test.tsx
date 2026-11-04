import assert from 'node:assert/strict';
import { afterEach, beforeEach, test, vi } from 'vite-plus/test';
import type { User } from '../../resources/js/types';
import { fakeConnection, joinAll } from './FakeRealtime';

let answer: (url: string, method?: string) => Promise<unknown>;
vi.mock('../../resources/js/lib/chat', async () => ({
    ...(await vi.importActual('../../resources/js/lib/chat')),
    request: (url: string, method?: string) => answer(url, method),
}));
const calls = await import('../../resources/js/lib/call');
const { connectRealtime } = await import('../../resources/js/lib/realtime');
let fake: ReturnType<typeof fakeConnection>;
let stop: () => void;
const peers: Peer[] = [];
class Peer {
    iceGatheringState = 'complete';
    localDescription = { sdp: 'offer' };
    closed = false;
    constructor() {
        peers.push(this);
    }
    addTrack() {}
    addTransceiver() {}
    createDataChannel() {
        return {};
    }
    async createOffer() {
        return { type: 'offer', sdp: 'offer' };
    }
    async setLocalDescription() {}
    close() {
        this.closed = true;
    }
}
const partner = (id: string) => ({ id, handle: id }) as User;
const flush = async () => {
    for (let i = 0; i < 20; i++) await Promise.resolve();
};
beforeEach(async () => {
    vi.useFakeTimers();
    peers.length = 0;
    vi.stubGlobal('window', {
        setTimeout,
        clearTimeout,
        localStorage: { getItem: () => 'off' },
    });
    vi.stubGlobal('document', {
        hidden: false,
        cookie: '',
        addEventListener() {},
        removeEventListener() {},
    });
    vi.stubGlobal(
        'Audio',
        class {
            async play() {}
            pause() {}
        },
    );
    vi.stubGlobal('navigator', {
        mediaDevices: { getUserMedia: async () => ({ getTracks: () => [] }) },
    });
    vi.stubGlobal('RTCPeerConnection', Peer);
    answer = async (url, method) =>
        url.includes('ice-server')
            ? { ice_servers: [], is_relay_only: false }
            : method === undefined
              ? { call: null, org_calls: [] }
              : {};
    fake = fakeConnection();
    connectRealtime(fake.connection);
    stop = calls.watchIncomingCalls('me');
    joinAll(fake);
    await flush();
});
afterEach(() => {
    calls.hangUp();
    calls.dismissCall();
    stop();
    connectRealtime(null);
    vi.unstubAllGlobals();
    vi.useRealTimers();
});

test('an ended call cannot check its old partner while the next offer is still being stored', async () => {
    await calls.startCall(partner('alice'));
    calls.hangUp();
    let finish!: (value: unknown) => void;
    const prior = answer;
    answer = (url, method) =>
        url.includes('bob') && method === 'POST'
            ? new Promise((resolve) => {
                  finish = resolve;
              })
            : prior(url, method);
    const second = calls.startCall(partner('bob'));
    await flush();
    assert.equal(typeof finish, 'function');
    fake.joined.get('private-users.me')?.emit('calls');
    await flush();
    assert.equal(calls.callSnapshot().status, 'calling');
    assert.equal(peers[1].closed, false);
    finish({});
    await second;
});

test('a late first offer does not install its answer checker over the next call', async () => {
    let finish!: (value: unknown) => void;
    const prior = answer;
    answer = (url, method) =>
        url.includes('alice') && method === 'POST'
            ? new Promise((resolve) => {
                  finish = resolve;
              })
            : prior(url, method);
    const first = calls.startCall(partner('alice'));
    await flush();
    calls.hangUp();
    await calls.startCall(partner('bob'));
    finish({});
    await first;
    answer = async (url) =>
        url.includes('bob') ? { call: { answer: null } } : { call: null };
    fake.joined.get('private-users.me')?.emit('calls');
    await flush();
    assert.equal(calls.callSnapshot().status, 'calling');
    assert.equal(peers[1].closed, false);
});
