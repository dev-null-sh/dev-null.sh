import assert from 'node:assert/strict';
import { afterEach, beforeEach, test, vi } from 'vite-plus/test';
import { chatSnapshot, seedThread, setReplyingTo, targetKey, watchRemoteAlerts, watchThread } from '../../resources/js/lib/chat';
import type { ChatTarget } from '../../resources/js/lib/chat';
import type { ConversationSummary, MessageReaction, User } from '../../resources/js/types';

const push = vi.hoisted(() => ({ optedIn: true }));
vi.mock('../../resources/js/lib/remote-push', () => ({
    remotePushEnabled: () => true,
    remotePushOptedIn: () => push.optedIn,
    syncRemotePush: async () => {},
    setRemotePush: async () => true,
}));

const play = vi.fn(async () => {});
let serviceWorker: EventTarget;
let visibility: EventTarget;
let storage: Map<string, string>;
const stops: (() => void)[] = [];

beforeEach(() => {
    vi.useFakeTimers();
    push.optedIn = true;
    play.mockClear();
    serviceWorker = new EventTarget();
    visibility = new EventTarget();
    storage = new Map();
    const windowEvents = new EventTarget();
    vi.stubGlobal('window', {
        setTimeout, clearTimeout, setInterval, clearInterval,
        addEventListener: windowEvents.addEventListener.bind(windowEvents),
        removeEventListener: windowEvents.removeEventListener.bind(windowEvents),
        localStorage: {
            getItem: (key: string) => storage.get(key) ?? null,
            setItem: (key: string, value: string) => storage.set(key, value),
        },
    });
    vi.stubGlobal('document', {
        hidden: false, hasFocus: () => false, cookie: '',
        addEventListener: visibility.addEventListener.bind(visibility),
        removeEventListener: visibility.removeEventListener.bind(visibility),
    });
    vi.stubGlobal('navigator', { serviceWorker });
    vi.stubGlobal('Audio', class {
        play = play;
        pause() {}
        currentTime = 0;
        muted = false;
    });
});

afterEach(() => {
    stops.splice(0).reverse().forEach((stop) => stop());
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
    vi.useRealTimers();
});

test.each([['direct', 'on'], ['org', 'on'], ['org', 'off'], ['org', 'muted']] as const)('a push updates a hidden %s remote with sound %s without reading, navigating, or starting background polling', async (kind, sound) => {
    if (sound === 'off') storage.set('remote-alerts:me:sound', 'off');
    const mutedUntil = sound === 'muted' ? '9999-12-31T00:00:00Z' : null;
    const partner = { id: 'alice', handle: 'alice', name: 'Alice' } as User;
    const target: ChatTarget = kind === 'org'
        ? { kind, org: { id: 'push-org', name: 'Team' } }
        : { kind, partner };
    let id = 10;
    const message = () => ({ id, user_id: 'alice', client_id: '', body: `Message ${id}`, created_at: new Date().toISOString() });
    const summary = (): ConversationSummary => ({
        id: kind === 'org' ? 'push-org' : 'push-direct', kind,
        name: kind === 'org' ? 'Team' : null, partner: kind === 'direct' ? partner : null, members: null,
        last_message: message(), last_incoming_message_id: id, last_incoming_sender: 'Alice',
        last_message_at: new Date().toISOString(), ahead: 1, partner_read_through_id: 0,
        partner_read_at: null, is_archived: false, pinned_at: null, muted_until_at: mutedUntil,
    });
    const requests: { url: string; method: string }[] = [];
    vi.stubGlobal('fetch', async (url: string, options: RequestInit) => {
        requests.push({ url, method: options.method ?? 'GET' });
        if (url.includes('/inbox')) return Response.json({
            conversations: [summary()], has_more: false, unread: 1, requests: 0,
            notification_baseline: { [summary().id]: 10 },
        });
        if (url.includes('/messages')) return Response.json({
            messages: [message()], has_older: false, reactions_version: 0, reaction_changes: [],
            read_through_id: 0, partner_read_through_id: 0, partner_read_at: null, partner_typing: false,
            members: [], typing: [], role: 'contributor', access: 'open', pinned: [],
            is_archived: false, pinned_at: null, muted_until_at: mutedUntil,
            ...(kind === 'org' ? { org: { id: 'push-org', name: 'Team' } } : { partner }),
        });
        return Response.json({ usage: [] });
    });
    stops.push(watchRemoteAlerts('me'), watchThread(target));
    await vi.advanceTimersByTimeAsync(0);
    const reply = { id: 10, user_id: 'alice', body: 'Message 10', user: partner, images_count: 0, has_voice: false };
    setReplyingTo(target, reply);
    Object.assign(document, { hidden: true });
    const before = requests.length;
    id = 11;
    await vi.advanceTimersByTimeAsync(120_000);
    assert.equal(requests.length, before);
    assert.equal(play.mock.calls.length, 0);

    serviceWorker.dispatchEvent(new MessageEvent('message', { data: { type: 'remote-push' } }));
    await vi.advanceTimersByTimeAsync(0);
    const thread = chatSnapshot().threads[targetKey(target)];
    assert.equal(thread.messages.at(-1)?.id, 11);
    assert.equal(chatSnapshot().unread, 1);
    assert.equal(thread.read_through_id, 0);
    assert.deepEqual(thread.replyingTo, reply);
    assert.equal(play.mock.calls.length, Number(sound === 'on'));
    assert.equal(requests.length, before + 2);
    assert.ok(requests.every(({ method }) => method === 'GET'));

    serviceWorker.dispatchEvent(new MessageEvent('message', { data: { type: 'remote-push' } }));
    await vi.advanceTimersByTimeAsync(0);
    const afterPush = requests.length;
    await vi.advanceTimersByTimeAsync(120_000);
    assert.equal(requests.length, afterPush);
    assert.equal(chatSnapshot().unread, 1);
    assert.equal(play.mock.calls.length, Number(sound === 'on'));
    Object.assign(document, { hidden: false });
    visibility.dispatchEvent(new Event('visibilitychange'));
    await vi.advanceTimersByTimeAsync(0);
    assert.equal(play.mock.calls.length, Number(sound === 'on'));
});

test('unrelated messages, opted-out accounts, and stopped watchers do not refresh', async () => {
    Object.assign(document, { hidden: true });
    const fetch = vi.fn();
    vi.stubGlobal('fetch', fetch);
    const stop = watchRemoteAlerts('me');
    for (const data of [null, 'remote-push', {}, { type: 'other' }]) {
        serviceWorker.dispatchEvent(new MessageEvent('message', { data }));
    }
    push.optedIn = false;
    serviceWorker.dispatchEvent(new MessageEvent('message', { data: { type: 'remote-push' } }));
    await vi.advanceTimersByTimeAsync(0);
    assert.equal(fetch.mock.calls.length, 0);
    stop();
    push.optedIn = true;
    serviceWorker.dispatchEvent(new MessageEvent('message', { data: { type: 'remote-push' } }));
    await vi.advanceTimersByTimeAsync(0);
    assert.equal(fetch.mock.calls.length, 0);
});


test.each([false, true])('pushes during an inbox request coalesce into a fresh read unless the watcher stops: stopped=%s', async (stopped) => {
    let resolveInbox: (response: Response) => void = () => {};
    const summary = (id: number) => ({
        id: 'in-flight', kind: 'org', name: 'Team', partner: null, members: null,
        last_message: null, last_incoming_message_id: id, last_incoming_sender: 'Alice',
        last_message_at: new Date().toISOString(), ahead: 1, partner_read_through_id: 0,
        partner_read_at: null, is_archived: false, pinned_at: null, muted_until_at: null,
    });
    const fetch = vi.fn().mockImplementationOnce(() => new Promise<Response>((resolve) => { resolveInbox = resolve; }))
        .mockImplementation(async () => Response.json({ conversations: [summary(11)], has_more: false, unread: 1, requests: 0 }));
    vi.stubGlobal('fetch', fetch);
    const stop = watchRemoteAlerts('me');
    Object.assign(document, { hidden: true });
    for (let i = 0; i < 3; i++) {
        serviceWorker.dispatchEvent(new MessageEvent('message', { data: { type: 'remote-push' } }));
    }
    assert.equal(fetch.mock.calls.length, 1);
    if (stopped) stop(); else stops.push(stop);
    resolveInbox(Response.json({ conversations: [summary(10)], has_more: false, unread: 1, requests: 0, notification_baseline: { 'in-flight': 10 } }));
    await vi.advanceTimersByTimeAsync(0);
    assert.equal(fetch.mock.calls.length, stopped ? 1 : 2);
    assert.equal(play.mock.calls.length, stopped ? 0 : 1);
    if (!stopped) assert.equal(chatSnapshot().conversations?.find((item) => item.id === 'in-flight')?.last_incoming_message_id, 11);
});

test.each(['direct', 'org'] as const)('a push response keeps newer %s reactions when an older visible poll finishes last', async (kind) => {
    vi.advanceTimersByTime(1);
    const partner = { id: 'alice', handle: 'reaction-race', name: 'Alice' } as User;
    const target: ChatTarget = kind === 'org'
        ? { kind, org: { id: 'reaction-race', name: 'Team' } }
        : { kind, partner };
    const message = { id: 501, user_id: 'alice', client_id: '', body: 'React to this', created_at: new Date().toISOString(), reactions: [] as MessageReaction[] };
    const payload = (version: number, reactions: MessageReaction[]) => ({
        messages: [], has_older: false, reactions_version: version,
        reaction_changes: [{ id: message.id, reactions }], read_through_id: 0,
        is_archived: false, pinned_at: null, muted_until_at: null, access: 'open' as const,
        ...(kind === 'org'
            ? { org: { id: 'reaction-race', name: 'Team' }, members: [], typing: [], role: 'contributor' as const, pinned: [{ ...message, reactions }] }
            : { partner, partner_read_through_id: 0, partner_read_at: null, partner_typing: false }),
    });
    seedThread(target, { ...payload(0, []), messages: [message] });
    const replies: ((response: Response) => void)[] = [];
    vi.stubGlobal('fetch', async (url: string) => {
        if (url.includes('/messages')) return new Promise<Response>((resolve) => replies.push(resolve));
        if (url.includes('/inbox')) return Response.json({ conversations: [], has_more: false, unread: 0, requests: 0, notification_baseline: {} });
        return Response.json({ usage: [] });
    });
    stops.push(watchRemoteAlerts('me'), watchThread(target));
    assert.equal(replies.length, 1);
    serviceWorker.dispatchEvent(new MessageEvent('message', { data: { type: 'remote-push' } }));
    assert.equal(replies.length, 2);
    const newest = [{ user_id: 'bob', emoji: '🚀' }];
    replies[1](Response.json(payload(2, newest)));
    await vi.advanceTimersByTimeAsync(0);
    assert.deepEqual(chatSnapshot().threads[targetKey(target)].messages[0].reactions, newest);
    replies[0](Response.json(payload(1, [])));
    await vi.advanceTimersByTimeAsync(0);
    const thread = chatSnapshot().threads[targetKey(target)];
    assert.deepEqual(thread.messages[0].reactions, newest);
    assert.equal(thread.reactions_version, 2);
    if (kind === 'org') assert.deepEqual(thread.pinned[0].reactions, newest);
});
