import assert from 'node:assert/strict';
import { afterEach, beforeEach, test, vi } from 'vite-plus/test';
import { fakeConnection } from './FakeRealtime';

const alerts: unknown[] = [];

vi.mock('../../resources/js/lib/remote-push', () => ({
    remotePushEnabled: () => false,
    remotePushOptedIn: () => false,
    syncRemotePush: async () => {},
    setRemotePush: async () => true,
}));

const chat = await import('../../resources/js/lib/chat');
const { connectRealtime } = await import('../../resources/js/lib/realtime');

const row = {
    id: 'private-a',
    kind: 'org',
    name: 'Private A title',
    partner: null,
    members: [],
    ahead: 1,
    pinned_at: null,
    muted_until_at: null,
    is_archived: false,
};
let target = { kind: 'org', org: { id: 'same-org', name: 'Demo org' } } as { kind: 'org'; org: { id: string; name: string } };
const message = (id: number) => ({ id, client_id: `c${id}`, user_id: 'x', body: `Message ${id}`, created_at: new Date(id * 1000).toISOString() });

type Held = { url: string; settle: (outcome: Response | Error) => void };
let held: Held[];
let answer: (url: string) => Response | null;
let fake: ReturnType<typeof fakeConnection>;

beforeEach(() => {
    vi.useFakeTimers();
    held = [];
    alerts.length = 0;
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
    vi.stubGlobal('document', {
        hidden: false,
        cookie: '',
        hasFocus: () => true,
        addEventListener: () => {},
        removeEventListener: () => {},
    });
    vi.stubGlobal('fetch', (input: string) => {
        const url = String(input);
        const now = answer(url);

        if (now !== null) {
            return Promise.resolve(now);
        }

        return new Promise<Response>((resolve, reject) => {
            held.push({ url, settle: (outcome) => (outcome instanceof Error ? reject(outcome) : resolve(outcome)) });
        });
    });
    fake = fakeConnection();
    connectRealtime(fake.connection);
});

afterEach(() => {
    connectRealtime(null);
    vi.unstubAllGlobals();
    vi.useRealTimers();
});

const inbox = (conversations: unknown[] = [row]) =>
    Response.json({ conversations, has_more: false, unread: 4, requests: 2 });

for (const [name, outcome] of [
    ['comes back', () => inbox()],
    ['fails', () => new Error('synthetic network failure')],
] as const) {
    test(`a poll whose second read ${name} after someone else signed in leaves the next account's remotes untouched`, async () => {
        // The first account has Requests loaded, so joining reads Open, then Requests again.
        answer = (url) => (url.includes('tab=fetched') && held.length === 0 && !url.includes('second') ? null : url.includes('tab=open') ? inbox() : null);
        const stopFirst = chat.watchRemoteUpdates('first');
        const loaded = chat.loadTab('fetched');
        held.shift()?.settle(inbox());
        await loaded;
        const stopInbox = chat.watchInbox();
        fake.setStatus('connected');
        fake.joined.get('private-users.first')?.join();
        await vi.advanceTimersByTimeAsync(300);
        const second = held.find(({ url }) => url.includes('tab=fetched'));
        assert.ok(second, 'the poll reads the loaded Requests again after Open');

        stopFirst();
        const stopSecond = chat.watchRemoteUpdates('second');
        const before = held.length;
        answer = () => null;
        second.settle(outcome());
        await vi.advanceTimersByTimeAsync(50);

        const snapshot = chat.chatSnapshot();
        assert.equal(JSON.stringify(snapshot).includes('private-a'), false);
        assert.equal(snapshot.unread, null);
        assert.equal(snapshot.requests, null);
        assert.equal(held.length, before, 'nothing is read again for the previous account');
        stopInbox();
        stopSecond();
    });
}

const seed = () =>
    chat.seedThread(target, {
        messages: [message(20)],
        has_older: true,
        read_through_id: 20,
        members: [],
        typing: [],
        role: 'contributor',
        pinned: [],
        mentions: [], mentions_count: 0,
        reactions_version: 0,
        is_archived: false,
        pinned_at: null,
        muted_until_at: null,
        access: 'open',
    } as never);
const thread = () => chat.chatSnapshot().threads[`org:${target.org.id}`];
const older = (ids: number[]) => Response.json({ messages: ids.map(message), has_older: false });

for (const [name, outcome] of [
    ['comes back', () => older([10])],
    ['fails', () => new Error('synthetic network failure')],
] as const) {
    test(`older history still on its way when the same account watches again, and ${name}, lets history load again`, async () => {
        answer = () => null;
        target = { kind: 'org', org: { id: `same-org-${name.replace(' ', '-')}`, name: 'Demo org' } };
        const stopFirst = chat.watchRemoteUpdates('same');
        seed();
        const first = chat.loadOlderMessages(target);
        stopFirst();
        const stopAgain = chat.watchRemoteUpdates('same');

        assert.equal(thread().isLoadingOlder, false);
        const second = chat.loadOlderMessages(target);
        assert.equal(held.length, 2, 'loading again reads again');

        // The old read settles after the new one started, and must not end it.
        held[0].settle(outcome());
        await first;
        assert.equal(thread().isLoadingOlder, true);

        held[1].settle(older([5]));
        await second;

        assert.equal(thread().isLoadingOlder, false);
        assert.deepEqual(thread().messages.map(({ id }) => id), [5, 20]);
        stopAgain();
    });
}

test('a reaction retired by a same-account remount does not block later server reactions', async () => {
    answer = () => Response.json({});
    const stopFirst = chat.watchRemoteUpdates('reactor');
    const target = { kind: 'direct' as const, partner: { id: 'peer', handle: 'peer' } };
    chat.seedThread(target as never, {
        messages: [{ ...message(981), reactions: [] }],
        has_older: false, reactions_version: 0, reaction_changes: [], read_through_id: 0,
    } as never);
    answer = () => null;
    const first = chat.toggleReaction(target as never, 981, '👍', 'reactor');
    const retired = held.shift();
    assert.ok(retired);
    stopFirst();
    const stopNext = chat.watchRemoteUpdates('reactor');
    retired.settle(Response.json({ reactions: [{ user_id: 'reactor', emoji: '👍' }] }));
    assert.equal(await first, false);

    const next = chat.toggleReaction(target as never, 981, '👍', 'reactor');
    const server = [{ user_id: 'someone', emoji: '🚀' }];
    held.shift()?.settle(Response.json({ reactions: server }));
    assert.equal(await next, true);
    assert.deepEqual(chat.chatSnapshot().threads.peer.messages[0].reactions, server);
    stopNext();
});
