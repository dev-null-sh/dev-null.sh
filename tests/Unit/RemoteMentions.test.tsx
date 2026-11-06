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
const { watchSeenMentions } = await import('../../resources/js/lib/seen-mentions');
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

type Held = { url: string; method: string; body: unknown; settle: (outcome: Response | Error) => void };
let held: Held[];
let isFocused: boolean;
let report: (entries: { target: Bubble; isIntersecting: boolean; intersectionRatio: number; intersectionRect: { height: number }; rootBounds: { height: number } }[]) => void;

type Bubble = { dataset: { messageId: string }; isConnected: boolean };
const bubbles = new Map<number, Bubble>();
const bubble = (id: number) => bubbles.get(id) ?? bubbles.set(id, { dataset: { messageId: String(id) }, isConnected: true }).get(id)!;
const scroller = (ids: number[]) => ({ querySelectorAll: () => ids.map(bubble) }) as unknown as HTMLElement;
const onScreen = (id: number, ratio = 1, height = 100 * ratio) => ({ target: bubble(id), isIntersecting: ratio > 0, intersectionRatio: ratio, intersectionRect: { height }, rootBounds: { height: 500 } });
const sent = (method: string, part: string) => held.some((request) => request.method === method && request.url.includes(part));

const take = async (method: string, part: string): Promise<Held> => {
    await vi.advanceTimersByTimeAsync(0);
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
        dispatchEvent: events.dispatchEvent.bind(events),
        localStorage: { getItem: () => null, setItem: () => {} },
    });
    isFocused = true;
    vi.stubGlobal('document', { hidden: false, cookie: '', hasFocus: () => isFocused, addEventListener: () => {}, removeEventListener: () => {} });
    vi.stubGlobal(
        'IntersectionObserver',
        class {
            constructor(callback: typeof report) {
                report = callback;
            }
            observe() {}
            unobserve() {}
            disconnect() {}
        },
    );
    vi.stubGlobal('fetch', (input: string, init?: RequestInit) => {
        const url = String(input);

        if (url.includes('/inbox') || url.includes('/call') || url.includes('/reactions')) {
            return Promise.resolve(Response.json({ conversations: [], has_more: false, unread: 0, requests: 0 }));
        }

        return new Promise<Response>((resolve, reject) => {
            held.push({ url, method: init?.method ?? 'GET', body: init?.body === undefined ? undefined : JSON.parse(init.body as string), settle: (outcome) => (outcome instanceof Error ? reject(outcome) : resolve(outcome)) });
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
    const before = await take('GET', '/messages');

    const clearing = chat.clearMentions(org, [2]);
    assert.deepEqual(shownIds(), [3, 1]);
    assert.equal(shown().mentions_count, 2);

    before.settle(Response.json(thread([3, 2, 1])));
    await vi.advanceTimersByTimeAsync(0);
    assert.deepEqual(shownIds(), [3, 1]);

    (await take('DELETE', '/mentions')).settle(Response.json({ mentions: [mention(3), mention(1)], mentions_count: 120 }));
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
    const before = await take('GET', '/messages');

    const clearing = chat.clearMentions(org);
    assert.deepEqual(shownIds(), []);
    assert.equal(shown().mentions_count, 0);

    (await take('DELETE', '/mentions')).settle(Response.json({ mentions: [], mentions_count: 0 }));
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
    (await take('GET', '/messages')).settle(Response.json(thread([3, 2])));
    await vi.advanceTimersByTimeAsync(0);

    const clearing = chat.clearMentions(org, [2]);
    (await take('DELETE', '/mentions')).settle(Response.json({}, { status: 500 }));
    await assert.rejects(clearing);
    assert.deepEqual(shownIds(), [3, 2]);
    assert.equal(shown().mentions_count, 2);

    await vi.advanceTimersByTimeAsync(200);
    (await take('GET', '/messages')).settle(Response.json(thread([3])));
    await vi.advanceTimersByTimeAsync(0);
    assert.deepEqual(shownIds(), [3]);

    stopThread();
    stop();
});

test('what one account cleared never hides the mentions of the next account', async () => {
    const stopFirst = chat.watchRemoteUpdates('mark');
    chat.seedThread(target, thread([5]) as never);
    const clearing = chat.clearMentions(org, [5]);
    (await take('DELETE', '/mentions')).settle(Response.json({ mentions: [], mentions_count: 0 }));
    await clearing;
    stopFirst();

    const stopSecond = chat.watchRemoteUpdates('taylor');
    chat.seedThread(target, thread([5]) as never);

    assert.deepEqual(shownIds(), [5]);
    stopSecond();
});

test('clears in one org go out one at a time, so an earlier answer never brings back a later clear', async () => {
    const stop = chat.watchRemoteUpdates('mark');
    chat.seedThread(target, thread([3, 2, 1]) as never);

    const first = chat.clearMentions(org, [1]);
    const second = chat.clearMentions(org, [2]);
    assert.deepEqual(shownIds(), [3]);
    await vi.advanceTimersByTimeAsync(0);
    assert.equal(held.filter(({ method }) => method === 'DELETE').length, 1);

    (await take('DELETE', '/mentions')).settle(Response.json({ mentions: [mention(3), mention(2)], mentions_count: 2 }));
    await first;
    assert.deepEqual(shownIds(), [3]);
    assert.equal(shown().mentions_count, 1);

    await vi.advanceTimersByTimeAsync(0);
    (await take('DELETE', '/mentions')).settle(Response.json({ mentions: [mention(3)], mentions_count: 1 }));
    await second;
    assert.deepEqual(shownIds(), [3]);
    assert.equal(shown().mentions_count, 1);

    stop();
});

test('a clear still waiting its turn is never sent for the next account', async () => {
    const stopFirst = chat.watchRemoteUpdates('mark');
    chat.seedThread(target, thread([2, 1]) as never);
    const first = chat.clearMentions(org, [1]);
    const all = chat.clearMentions(org).catch(() => undefined);
    const sent = await take('DELETE', '/mentions');
    stopFirst();

    const stopSecond = chat.watchRemoteUpdates('taylor');
    sent.settle(Response.json({ mentions: [mention(2)], mentions_count: 1 }));
    await first.catch(() => undefined);
    await all;
    await vi.advanceTimersByTimeAsync(0);

    assert.equal(held.filter(({ method }) => method === 'DELETE').length, 0);
    stopSecond();
});

test('a clear dropped when the remotes stop being watched leaves the bell to the next read', async () => {
    const stopFirst = chat.watchRemoteUpdates('mark');
    chat.seedThread(target, thread([2, 1]) as never);
    const clearing = chat.clearMentions(org, [1]);
    const sent = await take('DELETE', '/mentions');
    stopFirst();

    const stopAgain = chat.watchRemoteUpdates('mark');
    sent.settle(Response.json({ mentions: [mention(2)], mentions_count: 1 }));
    await clearing;
    chat.seedThread(target, thread([2]) as never);

    assert.deepEqual(shownIds(), [2]);
    assert.deepEqual(shown().clearingMentions, []);
    stopAgain();
});

test('a clear dropped for one account never undoes the next account clearing the same mention', async () => {
    const stopFirst = chat.watchRemoteUpdates('mark');
    chat.seedThread(target, thread([1]) as never);
    const first = chat.clearMentions(org, [1]);
    const stale = await take('DELETE', '/mentions');
    stopFirst();

    const stopSecond = chat.watchRemoteUpdates('taylor');
    chat.seedThread(target, thread([1]) as never);
    const second = chat.clearMentions(org, [1]);
    stale.settle(Response.json({ mentions: [], mentions_count: 0 }));
    await first;
    chat.seedThread(target, thread([1]) as never);

    assert.deepEqual(shownIds(), []);
    assert.deepEqual(shown().clearingMentions, [1]);

    (await take('DELETE', '/mentions')).settle(Response.json({ mentions: [], mentions_count: 0 }));
    await second;
    stopSecond();
});

test('a clear dropped before the account came back never undoes the clear it started since', async () => {
    const stopFirst = chat.watchRemoteUpdates('mark');
    chat.seedThread(target, thread([1]) as never);
    const first = chat.clearMentions(org, [1]);
    const stale = await take('DELETE', '/mentions');
    stopFirst();
    chat.watchRemoteUpdates('taylor')();

    const stopAgain = chat.watchRemoteUpdates('mark');
    chat.seedThread(target, thread([1]) as never);
    const again = chat.clearMentions(org, [1]);
    stale.settle(Response.json({ mentions: [], mentions_count: 0 }));
    await first;
    chat.seedThread(target, thread([1]) as never);

    assert.deepEqual(shownIds(), []);
    assert.deepEqual(shown().clearingMentions, [1]);

    (await take('DELETE', '/mentions')).settle(Response.json({ mentions: [], mentions_count: 0 }));
    await again;
    stopAgain();
});

test('mentions that stay on screen for a second clear together, including a long one filling most of the screen', async () => {
    const stop = chat.watchRemoteUpdates('mark');
    chat.seedThread(target, thread([3, 2, 1]) as never);
    const seen = watchSeenMentions(scroller([10, 3, 2, 1]), org);
    seen.track([3, 2, 1]);

    report([onScreen(3), onScreen(2, 0.2, 400), onScreen(1, 0.3)]);
    await vi.advanceTimersByTimeAsync(999);
    assert.equal(sent('DELETE', '/mentions'), false);
    assert.deepEqual(shownIds(), [3, 2, 1]);

    await vi.advanceTimersByTimeAsync(1);
    const clearing = await take('DELETE', '/mentions');
    assert.deepEqual(clearing.body, { message_ids: [3, 2] });
    assert.deepEqual(shownIds(), [1]);
    assert.equal(sent('DELETE', '/mentions'), false);

    seen.stop();
    stop();
});

test('a mention scrolled past within a second stays in the bell', async () => {
    const stop = chat.watchRemoteUpdates('mark');
    chat.seedThread(target, thread([3]) as never);
    const seen = watchSeenMentions(scroller([3]), org);
    seen.track([3]);

    report([onScreen(3)]);
    await vi.advanceTimersByTimeAsync(600);
    report([onScreen(3, 0)]);
    await vi.advanceTimersByTimeAsync(2000);

    assert.equal(sent('DELETE', '/mentions'), false);
    assert.deepEqual(shownIds(), [3]);

    seen.stop();
    stop();
});

test('nothing clears while the window is in the background, and the second starts once it is back', async () => {
    const stop = chat.watchRemoteUpdates('mark');
    chat.seedThread(target, thread([3]) as never);
    isFocused = false;
    const seen = watchSeenMentions(scroller([3]), org);
    seen.track([3]);

    report([onScreen(3)]);
    await vi.advanceTimersByTimeAsync(2000);
    assert.equal(sent('DELETE', '/mentions'), false);

    isFocused = true;
    window.dispatchEvent(new Event('focus'));
    await vi.advanceTimersByTimeAsync(999);
    assert.equal(sent('DELETE', '/mentions'), false);
    await vi.advanceTimersByTimeAsync(1);
    assert.deepEqual((await take('DELETE', '/mentions')).body, { message_ids: [3] });

    seen.stop();
    stop();
});

test('a refused clear of mentions on screen is not sent again while they stay there', async () => {
    const stop = chat.watchRemoteUpdates('mark');
    chat.seedThread(target, thread([3]) as never);
    const seen = watchSeenMentions(scroller([3]), org);
    seen.track([3]);

    report([onScreen(3)]);
    await vi.advanceTimersByTimeAsync(1000);
    (await take('DELETE', '/mentions')).settle(new Response(null, { status: 500 }));
    await vi.advanceTimersByTimeAsync(0);
    assert.deepEqual(shownIds(), [3]);

    seen.track([3]);
    report([onScreen(3)]);
    await vi.advanceTimersByTimeAsync(2000);
    assert.equal(sent('DELETE', '/mentions'), false);

    seen.stop();
    stop();
});
