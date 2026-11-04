import assert from 'node:assert/strict';
import { afterEach, beforeEach, test, vi } from 'vite-plus/test';
import { callSnapshot, watchIncomingCalls } from '../../resources/js/lib/call';
import { connectRealtime } from '../../resources/js/lib/realtime';
import { fakeConnection, joinAll } from './FakeRealtime';

let calls: number;
let ringing: { partner: { id: string; handle: string }; sdp: string; is_muted: boolean } | null;
let fake: ReturnType<typeof fakeConnection>;
const stops: (() => void)[] = [];

beforeEach(() => {
    vi.useFakeTimers();
    calls = 0;
    ringing = null;
    vi.stubGlobal('window', {
        setTimeout,
        clearTimeout,
        setInterval,
        clearInterval,
        localStorage: { getItem: () => 'off', setItem: () => {} },
        addEventListener: () => {},
        removeEventListener: () => {},
    });
    vi.stubGlobal('document', { hidden: false, cookie: '', addEventListener: () => {}, removeEventListener: () => {} });
    vi.stubGlobal('fetch', async (url: string) => {
        if (url.startsWith('/remotes/call')) {
            calls++;

            return Response.json({ call: ringing, org_calls: [] });
        }

        return Response.json({});
    });
    fake = fakeConnection();
    connectRealtime(fake.connection);
});

afterEach(() => {
    stops.splice(0).reverse().forEach((stop) => stop());
    connectRealtime(null);
    vi.unstubAllGlobals();
    vi.useRealTimers();
});

test('a call rings as soon as it is heard about, and checking slows down while calls are heard about live', async () => {
    stops.push(watchIncomingCalls('me'));
    await vi.advanceTimersByTimeAsync(0);
    joinAll(fake);
    await vi.advanceTimersByTimeAsync(0);
    const listening = calls;

    await vi.advanceTimersByTimeAsync(25_000);
    assert.equal(calls, listening);

    ringing = { partner: { id: 'alice', handle: 'alice' }, sdp: 'v=0 offer', is_muted: true };
    fake.joined.get('private-users.me')?.emit('calls');
    await vi.advanceTimersByTimeAsync(0);

    assert.equal(calls, listening + 1);
    assert.equal(callSnapshot().status, 'ringing');
});

test('a call left ringing by a tab that closed stops ringing without anyone saying so', async () => {
    ringing = { partner: { id: 'bob', handle: 'bob' }, sdp: 'v=0 another offer', is_muted: true };
    stops.push(watchIncomingCalls('me'));
    await vi.advanceTimersByTimeAsync(0);
    joinAll(fake);
    await vi.advanceTimersByTimeAsync(0);
    assert.equal(callSnapshot().status, 'ringing');

    ringing = null;
    await vi.advanceTimersByTimeAsync(3_000);

    assert.equal(callSnapshot().status, 'ended');
});

test('without live updates, calls are checked for as often as before', async () => {
    connectRealtime(null);
    stops.push(watchIncomingCalls('me'));
    await vi.advanceTimersByTimeAsync(0);
    const before = calls;

    await vi.advanceTimersByTimeAsync(9_000);

    assert.equal(calls, before + 3);
});
