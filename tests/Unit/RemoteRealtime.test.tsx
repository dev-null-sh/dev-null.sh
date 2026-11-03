import assert from 'node:assert/strict';
import { afterEach, beforeEach, test, vi } from 'vite-plus/test';
import {
    chatSnapshot,
    loadTab,
    targetKey,
    watchInbox,
    watchRemoteUpdates,
    watchThread,
} from '../../resources/js/lib/chat';
import type { ChatTarget } from '../../resources/js/lib/chat';
import { connectRealtime } from '../../resources/js/lib/realtime';
import type { ConversationSummary, Message } from '../../resources/js/types';
import { fakeConnection, joinAll } from './FakeRealtime';

vi.mock('../../resources/js/lib/remote-push', () => ({
    remotePushEnabled: () => false,
    remotePushOptedIn: () => false,
    syncRemotePush: async () => {},
    setRemotePush: async () => true,
}));

type Request = { url: URL; method: string };

let requests: Request[];
let fake: ReturnType<typeof fakeConnection>;
let visibility: EventTarget;
const stops: (() => void)[] = [];

const message = (id: number): Message =>
    ({
        id,
        user_id: 'alice',
        client_id: `client-${id}`,
        body: `Message ${id}`,
        created_at: new Date(id * 1000).toISOString(),
    }) as Message;

const orgThread = (
    org: string,
    messages: Message[],
    extra: Record<string, unknown> = {},
) => ({
    messages,
    has_older: false,
    reactions_version: 0,
    reaction_changes: [],
    read_through_id: 0,
    members: [],
    typing: [],
    role: 'contributor',
    access: 'open',
    pinned: [],
    is_archived: false,
    pinned_at: null,
    muted_until_at: null,
    org: { id: org, name: 'Team' },
    ...extra,
});

const summary = (id: string, kind: 'org' | 'direct' = 'org') =>
    ({
        id,
        kind,
        name: kind === 'org' ? 'Team' : null,
        partner:
            kind === 'direct'
                ? { id: `user-${id}`, handle: `user-${id}`, name: id }
                : null,
        members: null,
        last_message: message(1),
        last_incoming_message_id: 1,
        last_incoming_sender: 'Alice',
        last_message_at: new Date().toISOString(),
        ahead: 0,
        partner_read_through_id: 0,
        partner_read_at: null,
        is_archived: false,
        pinned_at: null,
        muted_until_at: null,
    }) as ConversationSummary;

function respond(
    handler: (url: URL, method: string) => unknown,
) {
    vi.stubGlobal('fetch', async (input: string, options: RequestInit = {}) => {
        const url = new URL(input, 'http://localhost');
        const method = options.method ?? 'GET';
        requests.push({ url, method });
        const answer = handler(url, method);

        if (answer instanceof Response) {
            return answer;
        }

        return Response.json(
            answer ??
                (url.pathname.endsWith('/inbox')
                    ? {
                          conversations: [],
                          has_more: false,
                          unread: 0,
                          requests: 0,
                      }
                    : { usage: [] }),
        );
    });
}

const threadReads = (org: string) =>
    requests.filter(
        ({ url, method }) =>
            method === 'GET' &&
            url.pathname === `/remotes/orgs/${org}/messages`,
    );
const inboxReads = (tab = 'open') =>
    requests.filter(
        ({ url }) =>
            url.pathname === '/remotes/inbox' &&
            url.searchParams.get('tab') === tab,
    );
const channel = () => fake.joined.get('private-users.me');

beforeEach(() => {
    vi.useFakeTimers();
    requests = [];
    visibility = new EventTarget();
    const windowEvents = new EventTarget();
    vi.stubGlobal('window', {
        setTimeout,
        clearTimeout,
        setInterval,
        clearInterval,
        addEventListener: windowEvents.addEventListener.bind(windowEvents),
        removeEventListener:
            windowEvents.removeEventListener.bind(windowEvents),
        localStorage: { getItem: () => null, setItem: () => {} },
    });
    vi.stubGlobal('document', {
        hidden: false,
        hasFocus: () => true,
        cookie: '',
        addEventListener: visibility.addEventListener.bind(visibility),
        removeEventListener: visibility.removeEventListener.bind(visibility),
    });
    vi.stubGlobal('navigator', {});
    fake = fakeConnection();
    connectRealtime(fake.connection);
});

afterEach(() => {
    stops.splice(0).reverse().forEach((stop) => stop());
    connectRealtime(null);
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
    vi.useRealTimers();
});

test('a burst of new messages reads the shown conversation and the inbox once, without marking anything read', async () => {
    const target: ChatTarget = { kind: 'org', org: { id: 'burst', name: 'Team' } };
    let latest = 1;
    respond((url) =>
        url.pathname === '/remotes/orgs/burst/messages'
            ? orgThread('burst', [message(latest)])
            : undefined,
    );
    stops.push(watchRemoteUpdates('me'), watchInbox(), watchThread(target));
    await vi.advanceTimersByTimeAsync(0);
    joinAll(fake);
    await vi.advanceTimersByTimeAsync(500);
    const [threadBefore, inboxBefore] = [threadReads('burst').length, inboxReads().length];

    latest = 2;
    channel()?.emit('remotes.messages', { conversation: 'burst' });
    channel()?.emit('remotes.messages', { conversation: 'burst' });
    channel()?.emit('remotes.messages', { conversation: 'burst' });
    await vi.advanceTimersByTimeAsync(500);

    assert.equal(threadReads('burst').length, threadBefore + 1);
    assert.equal(inboxReads().length, inboxBefore + 1);
    assert.equal(chatSnapshot().threads[targetKey(target)].messages.at(-1)?.id, 2);
    assert.ok(requests.every(({ method }) => method === 'GET'));
});

test('an update about another conversation leaves the shown one alone', async () => {
    const target: ChatTarget = { kind: 'org', org: { id: 'shown', name: 'Team' } };
    respond((url) =>
        url.pathname === '/remotes/orgs/shown/messages'
            ? orgThread('shown', [message(1)])
            : undefined,
    );
    stops.push(watchRemoteUpdates('me'), watchThread(target));
    await vi.advanceTimersByTimeAsync(0);
    joinAll(fake);
    await vi.advanceTimersByTimeAsync(500);
    const before = threadReads('shown').length;

    channel()?.emit('remotes.conversation', { conversation: 'elsewhere' });
    await vi.advanceTimersByTimeAsync(500);

    assert.equal(threadReads('shown').length, before);
});

test('polling only backs live updates up while they are heard, and speeds up again once they stop', async () => {
    const target: ChatTarget = { kind: 'org', org: { id: 'paced', name: 'Team' } };
    respond((url) =>
        url.pathname === '/remotes/orgs/paced/messages'
            ? orgThread('paced', [message(1)])
            : undefined,
    );
    stops.push(watchRemoteUpdates('me'), watchThread(target));
    await vi.advanceTimersByTimeAsync(0);
    joinAll(fake);
    await vi.advanceTimersByTimeAsync(500);
    const listening = threadReads('paced').length;

    await vi.advanceTimersByTimeAsync(29_000);
    assert.equal(threadReads('paced').length, listening);
    await vi.advanceTimersByTimeAsync(1_500);
    assert.equal(threadReads('paced').length, listening + 1);

    fake.setStatus('unavailable');
    const dropped = threadReads('paced').length;
    await vi.advanceTimersByTimeAsync(4_500);
    assert.ok(threadReads('paced').length >= dropped + 2);
});

test('reconnecting drains every message missed in between, beyond a single read', async () => {
    const target: ChatTarget = { kind: 'org', org: { id: 'drain', name: 'Team' } };
    let online = true;
    respond((url) => {
        if (url.pathname !== '/remotes/orgs/drain/messages') {
            return undefined;
        }

        const after = Number(url.searchParams.get('after') ?? 0);

        if (after === 0 || online) {
            return orgThread('drain', [message(1)].filter(({ id }) => id > after));
        }

        const missed = Array.from({ length: 150 }, (_, index) => message(index + 2))
            .filter(({ id }) => id > after)
            .slice(0, 100);

        return orgThread('drain', missed);
    });
    stops.push(watchRemoteUpdates('me'), watchThread(target));
    await vi.advanceTimersByTimeAsync(0);
    joinAll(fake);
    await vi.advanceTimersByTimeAsync(500);

    fake.setStatus('unavailable');
    online = false;
    fake.setStatus('connected');
    channel()?.join();
    await vi.advanceTimersByTimeAsync(500);

    const ids = chatSnapshot().threads[targetKey(target)].messages.map(({ id }) => id);
    assert.deepEqual(ids, Array.from({ length: 151 }, (_, index) => index + 1));
    assert.deepEqual(
        threadReads('drain').slice(-2).map(({ url }) => url.searchParams.get('after')),
        ['1', '101'],
    );
    assert.equal(threadReads('drain').at(-1)?.url.searchParams.get('after'), '101');
});

test('typing that stops without another message expires on its own', async () => {
    const target: ChatTarget = { kind: 'org', org: { id: 'typing', name: 'Team' } };
    let typing = ['alice'];
    respond((url) =>
        url.pathname === '/remotes/orgs/typing/messages'
            ? orgThread('typing', [message(1)], { typing })
            : undefined,
    );
    stops.push(watchRemoteUpdates('me'), watchThread(target));
    await vi.advanceTimersByTimeAsync(0);
    joinAll(fake);
    channel()?.emit('remotes.conversation', { conversation: 'typing' });
    await vi.advanceTimersByTimeAsync(500);
    assert.deepEqual(chatSnapshot().threads[targetKey(target)].typing, ['alice']);

    typing = [];
    await vi.advanceTimersByTimeAsync(5_500);

    assert.deepEqual(chatSnapshot().threads[targetKey(target)].typing, []);
});

test('someone removed from an org elsewhere stops seeing its messages', async () => {
    const target: ChatTarget = { kind: 'org', org: { id: 'removed', name: 'Team' } };
    let isMember = true;
    respond((url) => {
        if (url.pathname === '/remotes/orgs/removed/messages') {
            return isMember
                ? orgThread('removed', [message(1)])
                : Response.json({ message: 'Not Found' }, { status: 404 });
        }

        return undefined;
    });
    stops.push(watchRemoteUpdates('me'), watchThread(target));
    await vi.advanceTimersByTimeAsync(0);
    joinAll(fake);
    await vi.advanceTimersByTimeAsync(500);
    assert.equal(chatSnapshot().threads[targetKey(target)].messages.length, 1);

    isMember = false;
    channel()?.emit('remotes.inbox', { conversation: 'removed' });
    await vi.advanceTimersByTimeAsync(500);

    const thread = chatSnapshot().threads[targetKey(target)];
    assert.deepEqual(thread.messages, []);
    assert.equal(thread.access, 'closed');
});

test('a request replaced by another reloads the loaded requests even though their count stays the same', async () => {
    let requested = 'first-request';
    respond((url) => {
        if (url.pathname !== '/remotes/inbox') {
            return undefined;
        }

        return {
            conversations: url.searchParams.get('tab') === 'fetched' ? [summary(requested, 'direct')] : [],
            has_more: false,
            unread: 0,
            requests: 1,
        };
    });
    stops.push(watchRemoteUpdates('me'), watchInbox());
    await loadTab('fetched');
    joinAll(fake);
    await vi.advanceTimersByTimeAsync(500);
    assert.deepEqual(chatSnapshot().fetched?.map(({ id }) => id), ['first-request']);

    requested = 'second-request';
    channel()?.emit('remotes.inbox', { conversation: 'second-request' });
    await vi.advanceTimersByTimeAsync(500);

    assert.deepEqual(chatSnapshot().fetched?.map(({ id }) => id), ['second-request']);
});

test('a direct conversation blocked elsewhere stops showing its messages too', async () => {
    const partner = { id: 'blocker', handle: 'blocker', name: 'Blocker' };
    const target: ChatTarget = { kind: 'direct', partner: partner as never };
    let isAllowed = true;
    respond((url) => {
        if (url.pathname === '/remotes/@blocker/messages') {
            return isAllowed
                ? { ...orgThread('x', [message(1)]), org: undefined, partner, partner_typing: false, partner_read_through_id: 0, partner_read_at: null }
                : Response.json({ message: 'Not Found' }, { status: 404 });
        }

        return undefined;
    });
    stops.push(watchRemoteUpdates('me'), watchThread(target));
    await vi.advanceTimersByTimeAsync(0);
    joinAll(fake);
    await vi.advanceTimersByTimeAsync(500);
    assert.equal(chatSnapshot().threads[targetKey(target)].messages.length, 1);

    isAllowed = false;
    channel()?.emit('remotes.inbox', { conversation: 'direct-blocked' });
    await vi.advanceTimersByTimeAsync(500);

    assert.deepEqual(chatSnapshot().threads[targetKey(target)].messages, []);
    assert.equal(chatSnapshot().threads[targetKey(target)].access, 'closed');
});

test('a read still on its way when the remotes stop being watched, as on signing out, is dropped', async () => {
    const target: ChatTarget = { kind: 'org', org: { id: 'held', name: 'Team' } };
    let release = () => {};
    let held = false;
    vi.stubGlobal('fetch', async (input: string) => {
        const url = new URL(input, 'http://localhost');
        requests.push({ url, method: 'GET' });

        if (url.pathname === '/remotes/orgs/held/messages') {
            if (held) {
                await new Promise<void>((resolve) => (release = resolve));

                return Response.json(orgThread('held', [message(1), message(2)]));
            }

            return Response.json(orgThread('held', [message(1)]));
        }

        return Response.json({ conversations: [], has_more: false, unread: 0, requests: 0 });
    });
    const stopUpdates = watchRemoteUpdates('me');
    const stopThread = watchThread(target);
    await vi.advanceTimersByTimeAsync(0);
    joinAll(fake);
    await vi.advanceTimersByTimeAsync(500);

    held = true;
    channel()?.emit('remotes.messages', { conversation: 'held' });
    await vi.advanceTimersByTimeAsync(200);
    stopUpdates();
    stopThread();
    release();
    await vi.advanceTimersByTimeAsync(0);

    assert.deepEqual(chatSnapshot().threads[targetKey(target)].messages.map(({ id }) => id), [1]);
});

test('signing in as someone else forgets the remotes kept for the previous account', async () => {
    const target: ChatTarget = { kind: 'org', org: { id: 'previous', name: 'Team' } };
    respond((url) => (url.pathname === '/remotes/orgs/previous/messages' ? orgThread('previous', [message(1)]) : undefined));
    const stopUpdates = watchRemoteUpdates('first');
    const stopThread = watchThread(target);
    await vi.advanceTimersByTimeAsync(500);
    stopThread();
    stopUpdates();
    assert.ok(chatSnapshot().threads[targetKey(target)]);

    stops.push(watchRemoteUpdates('second'));

    assert.equal(chatSnapshot().threads[targetKey(target)], undefined);
});

test('a partner reading the conversation refreshes the inbox too, where how far they pulled shows', async () => {
    respond(() => undefined);
    stops.push(watchRemoteUpdates('me'), watchInbox());
    await vi.advanceTimersByTimeAsync(0);
    joinAll(fake);
    await vi.advanceTimersByTimeAsync(500);
    const before = inboxReads().length;

    channel()?.emit('remotes.conversation', { conversation: 'read', read: 1 });
    await vi.advanceTimersByTimeAsync(500);
    channel()?.emit('remotes.conversation', { conversation: 'typing' });
    await vi.advanceTimersByTimeAsync(500);

    assert.equal(inboxReads().length, before + 1);
});
