import assert from 'node:assert/strict';
import { afterEach, beforeEach, test, vi } from 'vite-plus/test';
import { fakeConnection } from './FakeRealtime';

vi.mock('../../resources/js/lib/remote-push', () => ({
    remotePushEnabled: () => false,
    remotePushOptedIn: () => false,
    syncRemotePush: async () => {},
    setRemotePush: async () => true,
}));

const chat = await import('../../resources/js/lib/chat');
const { connectRealtime } = await import('../../resources/js/lib/realtime');

let org = { id: 'mentions-org', name: 'Core' };
let target = { kind: 'org' as const, org };
let orgs = 0;
const mention = (id: number) => ({ id, client_id: `c${id}`, user_id: 'nuno', body: `@mark look ${id}`, created_at: new Date(id * 1000).toISOString() });
const thread = (mentions: number[], count = mentions.length) => ({
    org,
    messages: [mention(10)],
    has_older: false,
    reactions_version: 0,
    reaction_changes: [],
    read_through_id: 10,
    members: [],
    typing: [],
    role: 'contributor',
    access: 'open',
    pinned: [],
    mentions: mentions.map(mention),
    mentions_count: count,
    is_archived: false,
    pinned_at: null,
    muted_until_at: null,
});
const shown = () => chat.chatSnapshot().threads[`org:${org.id}`];
const shownIds = () => shown().mentions.map(({ id }) => id);

type Held = { url: string; method: string; settle: (outcome: Response | Error) => void };
let held: Held[];

const take = (method: string, part: string): Held => {
    const index = held.findIndex((request) => request.method === method && request.url.includes(part));
    assert.notEqual(index, -1, `a ${method} request to ${part} was sent`);

    return held.splice(index, 1)[0];
};

beforeEach(() => {
    vi.useFakeTimers();
    held = [];
    org = { id: `mentions-org-${++orgs}`, name: 'Core' };
    target = { kind: 'org' as const, org };
    const events = new EventTarget();
    vi.stubGlobal('window', {
        setTimeout,
        clearTimeout,
        setInterval,
        clearInterval,
        addEventListener: events.addEventListener.bind(events),
        removeEventListener: events.removeEventListener.bind(events),
        localStorage: { getItem: () => null, setItem: () => {} },
    });
    vi.stubGlobal('document', { hidden: false, cookie: '', hasFocus: () => true, addEventListener: () => {}, removeEventListener: () => {} });
    vi.stubGlobal('fetch', (input: string, init?: RequestInit) => {
        const url = String(input);

        if (url.includes('/inbox') || url.includes('/call') || url.includes('/reactions')) {
            return Promise.resolve(Response.json({ conversations: [], has_more: false, unread: 0, requests: 0 }));
        }

        return new Promise<Response>((resolve, reject) => {
            held.push({ url, method: init?.method ?? 'GET', settle: (outcome) => (outcome instanceof Error ? reject(outcome) : resolve(outcome)) });
        });
    });
    connectRealtime(fakeConnection().connection);
});

afterEach(() => {
    connectRealtime(null);
    vi.unstubAllGlobals();
    vi.useRealTimers();
});

test('a cleared mention stays gone from reads sent before clearing finished, and the bell then shows what the server has left', async () => {
    const stop = chat.watchRemoteUpdates('mark');
    chat.seedThread(target, thread([3, 2, 1]) as never);
    const stopThread = chat.watchThread(target);
    await vi.advanceTimersByTimeAsync(0);
    const before = take('GET', '/messages');

    const clearing = chat.clearMentions(org, 2);
    assert.deepEqual(shownIds(), [3, 1]);
    assert.equal(shown().mentions_count, 2);

    before.settle(Response.json(thread([3, 2, 1])));
    await vi.advanceTimersByTimeAsync(0);
    assert.deepEqual(shownIds(), [3, 1]);

    take('DELETE', '/mentions').settle(Response.json({ mentions: [mention(3), mention(1)], mentions_count: 120 }));
    await clearing;
    assert.deepEqual(shownIds(), [3, 1]);
    assert.equal(shown().mentions_count, 120);

    chat.seedThread(target, thread([3, 2, 1]) as never);
    assert.deepEqual(shownIds(), [3, 1]);

    stopThread();
    stop();
});

test('marking all read also drops the mentions the bell had not loaded, even when an older read comes back last', async () => {
    const stop = chat.watchRemoteUpdates('mark');
    chat.seedThread(target, thread([1], 2) as never);
    const stopThread = chat.watchThread(target);
    await vi.advanceTimersByTimeAsync(0);
    const before = take('GET', '/messages');

    const clearing = chat.clearMentions(org);
    assert.deepEqual(shownIds(), []);
    assert.equal(shown().mentions_count, 0);

    take('DELETE', '/mentions').settle(Response.json({ mentions: [], mentions_count: 0 }));
    await clearing;
    before.settle(Response.json(thread([2, 1])));
    await vi.advanceTimersByTimeAsync(0);

    assert.deepEqual(shownIds(), []);
    assert.equal(shown().mentions_count, 0);

    stopThread();
    stop();
});

test('a refused clear brings the mention back, then takes what the thread reads again', async () => {
    const stop = chat.watchRemoteUpdates('mark');
    chat.seedThread(target, thread([3, 2]) as never);
    const stopThread = chat.watchThread(target);
    await vi.advanceTimersByTimeAsync(0);
    take('GET', '/messages').settle(Response.json(thread([3, 2])));
    await vi.advanceTimersByTimeAsync(0);

    const clearing = chat.clearMentions(org, 2);
    take('DELETE', '/mentions').settle(Response.json({}, { status: 500 }));
    await assert.rejects(clearing);
    assert.deepEqual(shownIds(), [3, 2]);
    assert.equal(shown().mentions_count, 2);

    await vi.advanceTimersByTimeAsync(200);
    take('GET', '/messages').settle(Response.json(thread([3])));
    await vi.advanceTimersByTimeAsync(0);
    assert.deepEqual(shownIds(), [3]);

    stopThread();
    stop();
});

test('what one account cleared never hides the mentions of the next account', async () => {
    const stopFirst = chat.watchRemoteUpdates('mark');
    chat.seedThread(target, thread([5]) as never);
    const clearing = chat.clearMentions(org, 5);
    take('DELETE', '/mentions').settle(Response.json({ mentions: [], mentions_count: 0 }));
    await clearing;
    stopFirst();

    const stopSecond = chat.watchRemoteUpdates('taylor');
    chat.seedThread(target, thread([5]) as never);

    assert.deepEqual(shownIds(), [5]);
    stopSecond();
});
