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
    static failOffer = false;
    static failConstructor = false;
    iceGatheringState = 'complete';
    localDescription = { sdp: 'offer' };
    closed = false;
    connectionState = 'new';
    onconnectionstatechange: (() => void) | null = null;
    constructor() {
        if (Peer.failConstructor) throw new Error('peer setup failed');
        peers.push(this);
    }
    addTrack() {}
    addTransceiver() {}
    createDataChannel() {
        return {};
    }
    async createOffer() {
        if (Peer.failOffer) throw new Error('offer failed');
        return { type: 'offer', sdp: 'offer' };
    }
    async setLocalDescription() {}
    async setRemoteDescription() {}
    getTransceivers() {
        return [];
    }
    async createAnswer() {
        return { type: 'answer', sdp: 'answer' };
    }
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
    Peer.failOffer = false;
    Peer.failConstructor = false;
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

for (const stage of ['offer', 'post']) {
    for (const replaced of [false, true]) {
        test(`a canceled microphone acquisition releases its stream before a late ${stage} failure${replaced ? ' without disturbing the next call' : ''}`, async () => {
            const oldTrack = {
                stopped: false,
                stop() {
                    this.stopped = true;
                },
            };
            const nextTrack = {
                stopped: false,
                stop() {
                    this.stopped = true;
                },
            };
            let acquire!: (stream: unknown) => void;
            let prompts = 0;
            vi.stubGlobal('navigator', {
                mediaDevices: {
                    getUserMedia: () =>
                        ++prompts === 1
                            ? new Promise((resolve) => {
                                  acquire = resolve;
                              })
                            : Promise.resolve({ getTracks: () => [nextTrack] }),
                },
            });
            const prior = answer;
            answer = async (url, method) => {
                if (
                    url.includes('alice') &&
                    method === 'POST' &&
                    stage === 'post'
                )
                    throw new Error('signalling failed');
                return prior(url, method);
            };
            const first = calls.startCall(partner('alice'));
            await flush();
            assert.equal(prompts, 1);
            calls.hangUp();
            if (replaced) await calls.startCall(partner('bob'));
            Peer.failOffer = stage === 'offer';
            acquire({ getTracks: () => [oldTrack] });
            await first;
            assert.equal(oldTrack.stopped, true);
            assert.equal(peers.length, replaced ? 1 : 0);
            if (replaced) {
                assert.equal(calls.callSnapshot().status, 'calling');
                assert.equal(peers[0].closed, false);
                assert.equal(nextTrack.stopped, false);
                calls.hangUp();
                assert.equal(nextTrack.stopped, true);
            }
        });
    }
}

test('a canceled configuration lookup never asks for microphone permission', async () => {
    let finish!: (value: unknown) => void;
    let prompts = 0;
    vi.stubGlobal('navigator', {
        mediaDevices: {
            getUserMedia: async () => {
                prompts++;
                return { getTracks: () => [] };
            },
        },
    });
    const prior = answer;
    answer = (url, method) =>
        url.includes('ice-server')
            ? new Promise((resolve) => {
                  finish = resolve;
              })
            : prior(url, method);
    const first = calls.startCall(partner('alice'));
    await flush();
    calls.hangUp();
    finish({ ice_servers: [], is_relay_only: false });
    await first;
    assert.equal(prompts, 0);
    assert.equal(peers.length, 0);
});

test('failure to construct a peer releases the microphone already acquired', async () => {
    const track = {
        stopped: false,
        stop() {
            this.stopped = true;
        },
    };
    vi.stubGlobal('navigator', {
        mediaDevices: {
            getUserMedia: async () => ({ getTracks: () => [track] }),
        },
    });
    Peer.failConstructor = true;
    await calls.startCall(partner('alice'));
    assert.equal(track.stopped, true);
    assert.equal(calls.callSnapshot().status, 'ended');
});

test('answering also releases a microphone acquired after hangup without touching the next call', async () => {
    const prior = answer;
    answer = (url, method) =>
        method === undefined && !url.includes('ice-server')
            ? Promise.resolve({
                  call: {
                      partner: partner('alice'),
                      sdp: 'incoming-alice',
                      is_muted: true,
                  },
                  org_calls: [],
              })
            : prior(url, method);
    fake.joined.get('private-users.me')?.emit('calls');
    await flush();
    assert.equal(calls.callSnapshot().status, 'ringing');
    let acquire!: (stream: unknown) => void;
    let prompts = 0;
    const oldTrack = {
        stopped: false,
        stop() {
            this.stopped = true;
        },
    };
    const nextTrack = {
        stopped: false,
        stop() {
            this.stopped = true;
        },
    };
    vi.stubGlobal('navigator', {
        mediaDevices: {
            getUserMedia: () =>
                ++prompts === 1
                    ? new Promise((resolve) => {
                          acquire = resolve;
                      })
                    : Promise.resolve({ getTracks: () => [nextTrack] }),
        },
    });
    const first = calls.acceptCall();
    await flush();
    calls.hangUp();
    await calls.startCall(partner('bob'));
    acquire({ getTracks: () => [oldTrack] });
    await first;
    assert.equal(oldTrack.stopped, true);
    assert.equal(peers.length, 1);
    assert.equal(peers[0].closed, false);
    assert.equal(nextTrack.stopped, false);
    calls.hangUp();
    assert.equal(nextTrack.stopped, true);
});

test('an offer finishing after hangup is never sent to the partner', async () => {
    let finish!: (value: { type: string; sdp: string }) => void;
    const create = vi.spyOn(Peer.prototype, 'createOffer').mockImplementation(
        () =>
            new Promise((resolve) => {
                finish = resolve;
            }),
    );
    let posts = 0;
    const prior = answer;
    answer = (url, method) => {
        if (method === 'POST') posts++;
        return prior(url, method);
    };
    try {
        const first = calls.startCall(partner('alice'));
        await flush();
        calls.hangUp();
        finish({ type: 'offer', sdp: 'late' });
        await first;
        assert.equal(posts, 0);
        assert.equal(peers[0].closed, true);
    } finally {
        create.mockRestore();
    }
});

test('a current answer may become connected while its signalling request is held', async () => {
    const prior = answer;
    let finish!: (value: unknown) => void;
    answer = (url, method) => {
        if (method === 'PUT')
            return new Promise((resolve) => {
                finish = resolve;
            });
        if (method === undefined && !url.includes('ice-server'))
            return Promise.resolve({
                call: {
                    partner: partner('alice'),
                    sdp: 'incoming-connected-control',
                    is_muted: true,
                },
                org_calls: [],
            });
        return prior(url, method);
    };
    fake.joined.get('private-users.me')?.emit('calls');
    await flush();
    assert.equal(calls.callSnapshot().status, 'ringing');
    const track = {
        stopped: false,
        stop() {
            this.stopped = true;
        },
    };
    vi.stubGlobal('navigator', {
        mediaDevices: {
            getUserMedia: async () => ({ getTracks: () => [track] }),
        },
    });
    const accepting = calls.acceptCall();
    await flush();
    assert.equal(typeof finish, 'function');
    peers[0].connectionState = 'connected';
    peers[0].onconnectionstatechange?.();
    assert.equal(calls.callSnapshot().status, 'connected');
    finish({});
    await accepting;
    assert.equal(calls.callSnapshot().status, 'connected');
    assert.equal(track.stopped, false);
    calls.hangUp();
    assert.equal(track.stopped, true);
});
