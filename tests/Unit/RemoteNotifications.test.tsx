import assert from 'node:assert/strict';
import type { Page, SharedPageProps } from '@inertiajs/core';
import { afterEach, beforeEach, test, vi } from 'vite-plus/test';
import { chatSnapshot, loadTab, markThreadRead, seedThread, setConversationArchived, setConversationMute, watchRemoteAlerts, watchThread } from '../../resources/js/lib/chat';
import { pageTitle } from '../../resources/js/lib/page-title';
import { createRemoteAlerts, isRemoteMuted, remoteAlertsEnabled, setRemoteAlerts } from '../../resources/js/lib/remote-notifications';
import type { ConversationSummary } from '../../resources/js/types';

const storage = new Map<string, string>();
const shown: FakeNotification[] = [];
const announced: ConversationSummary[] = [];
const play = vi.fn(async () => {});
const assign = vi.fn();
let permission: NotificationPermission = 'granted';
let time = Date.parse('2026-10-22T12:00:00Z');
const readers: ReturnType<typeof createRemoteAlerts>[] = [];

class FakeNotification {
    static get permission() { return permission; }
    static requestPermission = async () => permission;
    title: string;
    options: NotificationOptions;
    onclick = () => {};
    onclose = () => {};
    close = vi.fn();
    constructor(title: string, options: NotificationOptions) {
        this.title = title;
        this.options = options;
        shown.push(this);
    }
}

function reader(reading: () => string[] = () => []) {
    const alerts = createRemoteAlerts('me', (item) => `/remotes/${item.id}`, reading, (item) => announced.push(item));
    readers.push(alerts);
    return alerts;
}

function conversation(id: number, changes: Partial<ConversationSummary> = {}): ConversationSummary {
    return {
        id: 'remote-one', kind: 'direct', name: null, partner: null, members: null,
        last_message: { id, user_id: 'other', client_id: '', body: 'private message', created_at: new Date(time).toISOString() },
        last_incoming_message_id: id,
        last_incoming_sender: 'Alice',
        last_message_at: new Date(time).toISOString(), ahead: 1,
        partner_read_through_id: 0, partner_read_at: null, is_archived: false, pinned_at: null, muted_until_at: null,
        ...changes,
    };
}

beforeEach(() => {
    time = Date.parse('2026-10-22T12:00:00Z');
    permission = 'granted';
    storage.clear();
    storage.set('remote-alerts:me:offsite', 'on');
    shown.length = 0;
    announced.length = 0;
    play.mockClear();
    assign.mockClear();
    vi.spyOn(Date, 'now').mockImplementation(() => time);
    vi.stubGlobal('Notification', FakeNotification);
    vi.stubGlobal('Audio', class {
        play = play;
        pause() {}
        currentTime = 0;
        muted = false;
    });
    const events = new EventTarget();
    vi.stubGlobal('window', {
        Notification: FakeNotification, isSecureContext: true,
        addEventListener: events.addEventListener.bind(events),
        removeEventListener: events.removeEventListener.bind(events),
        dispatchEvent: events.dispatchEvent.bind(events),
        setInterval, clearInterval, focus() {}, location: { assign },
        localStorage: {
            getItem: (key: string) => storage.get(key) ?? null,
            setItem: (key: string, value: string) => storage.set(key, value),
            removeItem: (key: string) => storage.delete(key),
        },
    });
    vi.stubGlobal('document', { addEventListener() {}, removeEventListener() {} });
    const locks = new Map<string, Promise<void>>();
    vi.stubGlobal('navigator', { locks: { request: (name: string, options: LockOptions | (() => Promise<void>), run?: () => Promise<void>) => {
        const callback = typeof options === 'function' ? options : () => {
            options.signal?.throwIfAborted();
            return run!();
        };
        const lock = (locks.get(name) ?? Promise.resolve()).then(callback, callback);
        locks.set(name, lock);
        return lock;
    } } });
});

afterEach(() => {
    readers.splice(0).forEach((alerts) => alerts.stop());
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
});

test('ignores backlog, own messages, org events, read conversations and active mutes without replaying them later', async () => {
    const alerts = reader();
    const away = () => false;
    await alerts.receive([conversation(10)], away);
    const own = conversation(11, { last_incoming_message_id: 10 });
    own.last_message!.user_id = 'me';
    await alerts.receive([own], away);
    const event = conversation(12, { last_incoming_message_id: 10 });
    event.last_message!.event = 'added';
    await alerts.receive([event], away);
    await alerts.receive([conversation(13)], () => true);
    await alerts.receive([conversation(14, { ahead: 0 })], away);
    await alerts.receive([conversation(15, { muted_until_at: new Date(time + 1).toISOString() })], away);
    time += 20_000;
    await alerts.receive([conversation(15)], away);
    assert.equal(shown.length, 0);
    assert.equal(announced.length, 0);
    assert.equal(play.mock.calls.length, 0);

    await alerts.receive([conversation(16, { muted_until_at: new Date(time).toISOString() })], away);
    assert.equal(shown.length, 1);
    assert.equal(play.mock.calls.length, 1);
    assert.equal(shown[0].options.body, 'You have a new message in Remotes.');
    assert.equal(shown[0].options.silent, true);
    assert.equal(JSON.stringify(shown[0].options).includes('private message'), false);
    shown[0].onclick();
    assert.deepEqual(assign.mock.calls, [['/remotes/remote-one']]);
});

test('only sound has a ten-second cooldown across remotes and tabs, while banners update without duplicates', async () => {
    const first = reader();
    const second = reader();
    await Promise.all([first.receive([], () => false), second.receive([], () => false)]);
    await Promise.all([first.receive([conversation(20)], () => false), second.receive([conversation(20)], () => false)]);
    time += 9999;
    await first.receive([conversation(21, { id: 'another-remote' })], () => false);
    assert.equal(shown.length, 2);
    assert.equal(play.mock.calls.length, 1);
    time++;
    await second.receive([conversation(21, { id: 'another-remote' })], () => false);
    assert.equal(shown.length, 2);
    assert.equal(play.mock.calls.length, 1);
    await second.receive([conversation(22, { kind: 'org' })], () => false);
    assert.equal(shown.length, 3);
    time += 10_000;
    await first.receive([conversation(22)], () => false);
    assert.equal(shown.length, 3);
    assert.equal(announced.length, 3);
    assert.equal(play.mock.calls.length, 2);
});

test('a banner sent with sound disabled does not start the sound cooldown', async () => {
    const alerts = reader();
    await alerts.receive([], () => false);
    await setRemoteAlerts('me', false, 'sound');
    await alerts.receive([conversation(20)], () => false);
    await setRemoteAlerts('me', true, 'sound');
    time++;
    await alerts.receive([conversation(21)], () => false);

    assert.equal(shown.length, 2);
    assert.equal(play.mock.calls.length, 1);
});

test.each([false, true])('out-of-order arrivals across remotes are not lost to %s cross-tab deduplication', async (otherTab) => {
    const first = reader();
    const second = reader();
    await first.receive([], () => false);
    await second.receive([], () => false);
    await first.receive([conversation(102, { id: 'fast-remote' })], () => true);
    assert.equal(shown.length, 0);

    await (otherTab ? second : first).receive([conversation(101, { id: 'slow-remote' })], () => false);
    assert.equal(shown.length, 1);
    assert.equal(play.mock.calls.length, 1);
    assert.equal(shown[0].options.data.url, '/remotes/slow-remote');

    time += 10_000;
    await (otherTab ? first : second).receive([conversation(101, { id: 'slow-remote' })], () => false);
    await second.receive([conversation(102, { id: 'fast-remote' })], () => false);
    assert.equal(shown.length, 1);
    await first.receive([conversation(103, { id: 'slow-remote' })], () => false);
    assert.equal(shown.length, 2);
});

test('a focused reader suppresses a background tab before its unread poll catches up, and releases focus and closed tabs', async () => {
    let focused = true;
    const foreground = reader(() => focused ? ['/remotes/remote-one'] : []);
    const background = reader();
    await Promise.all([foreground.receive([], () => false), background.receive([], () => false)]);

    await background.receive([conversation(20)], () => false);
    assert.equal(shown.length, 0);

    focused = false;
    window.dispatchEvent(new Event('blur'));
    await foreground.receive([conversation(20)], () => false);
    assert.equal(shown.length, 0);
    await background.receive([conversation(21)], () => false);
    assert.equal(shown.length, 1);

    focused = true;
    window.dispatchEvent(new Event('focus'));
    time += 10_000;
    await background.receive([conversation(22)], () => false);
    assert.equal(shown.length, 1);
    window.dispatchEvent(new Event('pagehide'));
    await background.receive([conversation(23)], () => false);
    assert.equal(shown.length, 2);

    window.dispatchEvent(new Event('pageshow'));
    time += 10_000;
    await background.receive([conversation(24)], () => false);
    assert.equal(shown.length, 2);
    foreground.stop();
    await background.receive([conversation(25)], () => false);
    assert.equal(shown.length, 3);
});

test('a reader that stops refreshing its focused state cannot suppress alerts indefinitely', async () => {
    reader(() => ['/remotes/remote-one']);
    const background = reader();
    await background.receive([], () => false);

    time += 14_999;
    await background.receive([conversation(20)], () => false);
    assert.equal(shown.length, 0);
    time++;
    await background.receive([conversation(21)], () => false);
    assert.equal(shown.length, 1);
});

test('a newer org membership event does not hide an incoming message or itself generate another alert', async () => {
    const alerts = reader();
    await alerts.receive([conversation(10)], () => false);
    const event = conversation(12, { kind: 'org', last_incoming_message_id: 11 });
    event.last_message!.event = 'added';

    await alerts.receive([event], () => false);
    assert.equal(shown.length, 1);

    time += 10_000;
    event.last_message!.id = 13;
    await alerts.receive([event], () => false);
    assert.equal(shown.length, 1);
});

test('alerts default on, preserve an explicit opt-out per account, and banners still require permission', async () => {
    assert.equal(remoteAlertsEnabled('me'), true);
    assert.equal(remoteAlertsEnabled('me', 'sound'), true);
    await setRemoteAlerts('me', false);
    assert.equal(storage.get('remote-alerts:me:notifications'), 'off');
    assert.equal(remoteAlertsEnabled('me'), false);
    assert.equal(remoteAlertsEnabled('me', 'sound'), true);
    assert.equal(remoteAlertsEnabled('someone-else'), true);
    permission = 'denied';
    assert.equal(await setRemoteAlerts('me', true), true);
    assert.equal(remoteAlertsEnabled('me'), true);
    permission = 'granted';
    assert.equal(await setRemoteAlerts('me', true), true);
    const alerts = reader();
    await alerts.receive([], () => false);
    permission = 'denied';
    await alerts.receive([conversation(1)], () => false);
    permission = 'granted';
    const pending = alerts.receive([conversation(2)], () => false);
    alerts.stop();
    await pending;
    assert.equal(shown.length, 0);
});

test('legacy opt-outs apply to both channels until each is changed independently', async () => {
    storage.set('remote-alerts:me', 'off');
    assert.equal(remoteAlertsEnabled('me'), false);
    assert.equal(remoteAlertsEnabled('me', 'sound'), false);
    permission = 'denied';
    const requestPermission = vi.spyOn(FakeNotification, 'requestPermission');

    assert.equal(await setRemoteAlerts('me', true, 'sound'), true);
    assert.equal(requestPermission.mock.calls.length, 0);
    assert.equal(remoteAlertsEnabled('me'), false);
    assert.equal(remoteAlertsEnabled('me', 'sound'), true);

    permission = 'granted';
    await setRemoteAlerts('me', true);
    await setRemoteAlerts('me', false, 'sound');
    assert.equal(remoteAlertsEnabled('me'), true);
    assert.equal(remoteAlertsEnabled('me', 'sound'), false);
});

test.each([
    [true, true], [true, false], [false, true], [false, false],
])('notifications=%s and sound=%s operate independently', async (notifications, sound) => {
    await setRemoteAlerts('me', notifications);
    await setRemoteAlerts('me', sound, 'sound');
    const alerts = reader();
    await alerts.receive([], () => false);
    await alerts.receive([conversation(1)], () => false);

    assert.equal(shown.length, 1);
    assert.equal(announced.length, Number(notifications));
    assert.equal(play.mock.calls.length, Number(sound));
});

test.each(['denied', 'unavailable', 'broken'] as const)('sound works when browser notifications are %s', async (state) => {
    if (state === 'denied') permission = 'denied';
    if (state === 'unavailable') {
        Reflect.deleteProperty(window, 'Notification');
        vi.stubGlobal('Notification', undefined);
    }
    if (state === 'broken') {
        vi.stubGlobal('Notification', class extends FakeNotification {
            constructor() { super('', {}); shown.pop(); throw new Error('Notifications blocked'); }
        });
    }
    await setRemoteAlerts('me', false);
    assert.equal(await setRemoteAlerts('me', true), true);
    const alerts = reader();
    await alerts.receive([], () => false);
    await alerts.receive([conversation(1)], () => false);
    time += 9999;
    await alerts.receive([conversation(2)], () => false);

    assert.equal(shown.length, 0);
    assert.equal(announced.length, 2);
    assert.equal(play.mock.calls.length, 1);
});

test('the default does not hide a failed preference save when storage is blocked', async () => {
    vi.spyOn(window.localStorage, 'setItem').mockImplementation(() => { throw new Error('Storage blocked'); });

    assert.equal(await setRemoteAlerts('me', true), false);
    assert.equal(await setRemoteAlerts('me', false), false);
    assert.equal(await setRemoteAlerts('me', false, 'sound'), false);
    assert.equal(remoteAlertsEnabled('me'), true);
    assert.equal(remoteAlertsEnabled('me', 'sound'), true);
});

test('expiry is exact and forever stays muted', () => {
    assert.equal(isRemoteMuted(null, time), false);
    assert.equal(isRemoteMuted(new Date(time - 1).toISOString(), time), false);
    assert.equal(isRemoteMuted(new Date(time).toISOString(), time), false);
    assert.equal(isRemoteMuted(new Date(time + 1).toISOString(), time), true);
    assert.equal(isRemoteMuted('9999-12-31T00:00:00Z', time), true);
});

test('mobile notification fallback works and rejected audio does not reject message polling', async () => {
    vi.stubGlobal('Notification', class extends FakeNotification {
        constructor() { super('', {}); shown.pop(); throw new TypeError('Use a service worker'); }
    });
    const showNotification = vi.fn(async () => {});
    vi.stubGlobal('navigator', { serviceWorker: { getRegistration: async () => ({ showNotification }) } });
    play.mockRejectedValueOnce(new Error('Autoplay blocked'));
    const alerts = reader();
    await alerts.receive([], () => false);
    await alerts.receive([conversation(1)], () => false);
    assert.equal(showNotification.mock.calls.length, 1);
    assert.equal(play.mock.calls.length, 1);
});

test.each([
    [49, 50, '(99) Remotes - /dev/null'],
    [50, 50, '(99+) Remotes - /dev/null'],
    [1000, 0, '(99+) Remotes - /dev/null'],
    [0, 1000, '(99+) Remotes - /dev/null'],
] as const)('tab titles cap the combined unread total with %s notifications and %s messages', (notifications, messages, expected) => {
    const page = { props: { auth: { user: { id: 'me' }, unreadNotificationsCount: notifications, unreadMessagesCount: messages } } } as Page<SharedPageProps>;

    assert.equal(pageTitle('Remotes', page), expected);
});

test.each([true, false])('background inbox polling alerts without consuming unread state, with banners=%s', async (banners) => {
    vi.useFakeTimers();
    Object.assign(window, { setTimeout, clearTimeout });
    vi.stubGlobal('document', { hidden: true, cookie: '', addEventListener() {}, removeEventListener() {} });
    await setRemoteAlerts('me', banners);
    storage.set('remote-alerts:me:offsite', banners ? 'on' : 'off');
    let messages = [conversation(30, { kind: 'org' })];
    let unread = 1;
    const page = { props: { auth: { user: { id: 'me' }, unreadNotificationsCount: 2, unreadMessagesCount: 8 } } } as Page<SharedPageProps>;
    const requestedBaselines: boolean[] = [];
    vi.stubGlobal('fetch', async (url: string) => {
        const baseline = url.includes('notification_baseline=1');
        requestedBaselines.push(baseline);
        return Response.json({ conversations: messages, has_more: false, unread, requests: 0, ...(baseline ? { notification_baseline: { 'remote-one': 30 } } : {}) });
    });
    const stop = watchRemoteAlerts('me');

    try {
        await vi.advanceTimersByTimeAsync(59_999);
        assert.deepEqual(requestedBaselines, []);
        await vi.advanceTimersByTimeAsync(1);
        assert.deepEqual(requestedBaselines, [true]);
        assert.equal(shown.length, 0);
        messages = [conversation(31, { kind: 'org' })];
        await vi.advanceTimersByTimeAsync(59_999);
        assert.deepEqual(requestedBaselines, [true]);
        assert.equal(shown.length, 0);
        assert.equal(play.mock.calls.length, 0);
        await vi.advanceTimersByTimeAsync(1);
        assert.equal(shown.length, Number(banners));
        assert.equal(play.mock.calls.length, 1);
        assert.equal(chatSnapshot().unread, 1);
        assert.equal(chatSnapshot().conversations?.[0].ahead, 1);
        assert.deepEqual(requestedBaselines, [true, false]);
        assert.equal(pageTitle('Remotes', page), '(3) Remotes - /dev/null');

        unread = 4;
        messages = [conversation(32, { kind: 'org', ahead: 4 })];
        await vi.advanceTimersByTimeAsync(60_000);
        assert.equal(pageTitle('Settings', page), '(6) Settings - /dev/null');
        assert.equal(shown.length, 2 * Number(banners));
        assert.equal(play.mock.calls.length, 2);

        unread = 97;
        await vi.advanceTimersByTimeAsync(60_000);
        assert.equal(pageTitle('Settings', page), '(99) Settings - /dev/null');

        unread = 98;
        await vi.advanceTimersByTimeAsync(60_000);
        assert.equal(pageTitle('', page), '(99+) /dev/null');

        unread = 0;
        messages = [conversation(32, { kind: 'org', ahead: 0 })];
        await vi.advanceTimersByTimeAsync(60_000);
        assert.equal(pageTitle('', page), '(2) /dev/null');
        page.props.auth.unreadNotificationsCount = 0;
        assert.equal(pageTitle('Remotes', page), 'Remotes - /dev/null');
    } finally {
        stop();
        vi.useRealTimers();
    }
    assert.equal(pageTitle('Settings', page), '(8) Settings - /dev/null');
    page.props.auth.user = null;
    assert.equal(pageTitle('Settings', page), 'Settings - /dev/null');
});

test('tab titles blink on focused pages and stay until the receiving remote is visible and focused', async () => {
    vi.useFakeTimers();
    Object.assign(window, { setTimeout, clearTimeout, setInterval, clearInterval });
    const events = new EventTarget();
    let focused = true;
    vi.stubGlobal('document', {
        hidden: false, hasFocus: () => focused, cookie: '',
        addEventListener: events.addEventListener.bind(events),
        removeEventListener: events.removeEventListener.bind(events),
    });
    permission = 'denied';
    await setRemoteAlerts('me', false, 'sound');
    let messages: ConversationSummary[] = [];
    vi.stubGlobal('fetch', async (url: string) => url.includes('/inbox')
        ? Response.json({ conversations: messages, has_more: false, unread: 1, requests: 0, notification_baseline: {} })
        : new Response(null, { status: 503 }));
    const stop = watchRemoteAlerts('me');
    const stops: (() => void)[] = [];

    try {
        await vi.advanceTimersByTimeAsync(0);
        messages = [conversation(101, { kind: 'org', name: 'Design & <code>' })];
        events.dispatchEvent(new Event('visibilitychange'));
        await vi.advanceTimersByTimeAsync(0);
        assert.equal(chatSnapshot().notificationTitle, 'Alice in Design & <code> sent you a message');

        await vi.advanceTimersByTimeAsync(999);
        assert.equal(chatSnapshot().notificationTitle, 'Alice in Design & <code> sent you a message');
        await vi.advanceTimersByTimeAsync(1);
        assert.equal(chatSnapshot().notificationTitle, null);
        messages = [conversation(102, { kind: 'org', name: 'Core', last_incoming_sender: 'Bob' })];
        events.dispatchEvent(new Event('visibilitychange'));
        await vi.advanceTimersByTimeAsync(0);
        assert.equal(chatSnapshot().notificationTitle, null);
        for (const title of ['Bob in Core sent you a message', null, 'Bob in Core sent you a message', null, 'Bob in Core sent you a message']) {
            await vi.advanceTimersByTimeAsync(1_000);
            assert.equal(chatSnapshot().notificationTitle, title);
        }
        await vi.advanceTimersByTimeAsync(10_000);
        assert.equal(chatSnapshot().notificationTitle, 'Bob in Core sent you a message');
        stops.push(watchThread({ kind: 'org', org: { id: 'unrelated', name: 'Other remote' } }));
        assert.equal(chatSnapshot().notificationTitle, 'Bob in Core sent you a message');
        focused = true;
        Object.assign(document, { hidden: true });
        const stopReceiving = watchThread({ kind: 'org', org: { id: 'remote-one', name: 'Core' } });
        stops.push(stopReceiving);
        window.dispatchEvent(new Event('focus'));
        assert.equal(chatSnapshot().notificationTitle, 'Bob in Core sent you a message');
        focused = false;
        Object.assign(document, { hidden: false });
        events.dispatchEvent(new Event('visibilitychange'));
        await vi.advanceTimersByTimeAsync(0);
        assert.equal(chatSnapshot().notificationTitle, 'Bob in Core sent you a message');
        focused = true;
        window.dispatchEvent(new Event('focus'));
        assert.equal(chatSnapshot().notificationTitle, null);
        assert.equal(shown.length, 0);
        assert.equal(play.mock.calls.length, 0);

        stopReceiving();
        stops.pop();
        focused = false;
        messages = [conversation(103, { kind: 'org', name: null })];
        events.dispatchEvent(new Event('visibilitychange'));
        await vi.advanceTimersByTimeAsync(0);
        assert.equal(chatSnapshot().notificationTitle, 'Alice in an unnamed org sent you a message');
        focused = true;
        window.dispatchEvent(new Event('focus'));
        assert.equal(chatSnapshot().notificationTitle, 'Alice in an unnamed org sent you a message');
        stops.push(watchThread({ kind: 'org', org: { id: 'remote-one', name: 'Core' } }));
        await vi.advanceTimersByTimeAsync(7_000);
        assert.equal(chatSnapshot().notificationTitle, null);

        messages = [conversation(104, { kind: 'org' })];
        events.dispatchEvent(new Event('visibilitychange'));
        await vi.advanceTimersByTimeAsync(0);
        assert.equal(chatSnapshot().notificationTitle, null);
        focused = false;
        messages = [conversation(105)];
        events.dispatchEvent(new Event('visibilitychange'));
        await vi.advanceTimersByTimeAsync(0);
        assert.equal(chatSnapshot().notificationTitle, 'Alice sent you a message');
    } finally {
        stops.forEach((stopThread) => stopThread());
        stop();
        vi.useRealTimers();
    }
    assert.equal(chatSnapshot().notificationTitle, null);
});

test('native broadcasts only acknowledge the receiving remote, without extra sound or banners', async () => {
    vi.useFakeTimers();
    Object.assign(window, { BroadcastChannel, setTimeout, clearTimeout, setInterval, clearInterval });
    let focused = false;
    Object.assign(document, { hidden: false, hasFocus: () => focused, cookie: '' });
    vi.stubGlobal('fetch', async (url: string) => url.includes('/inbox')
        ? Response.json({ conversations: [], has_more: false, unread: 0, requests: 0, notification_baseline: {} })
        : new Response(null, { status: 503 }));
    const otherTab = new BroadcastChannel('remote-titles:me');
    const acknowledged: unknown[] = [];
    otherTab.addEventListener('message', (event) => acknowledged.push(event.data));
    const stop = watchRemoteAlerts('me');
    let stopThread: (() => void) | undefined;

    try {
        otherTab.postMessage({ key: 'org:core', title: 'Alice in Core sent you a message' });
        await vi.waitFor(() => assert.equal(chatSnapshot().notificationTitle, 'Alice in Core sent you a message'));
        await vi.advanceTimersByTimeAsync(6_000);
        otherTab.postMessage({ key: 'bob', title: 'Bob sent you a message' });
        await vi.waitFor(() => assert.equal(chatSnapshot().notificationTitle, 'Bob sent you a message'));
        await vi.advanceTimersByTimeAsync(10_000);
        assert.equal(chatSnapshot().notificationTitle, 'Bob sent you a message');

        otherTab.postMessage({ key: 'org:core', title: null });
        await vi.advanceTimersByTimeAsync(1_000);
        assert.equal(chatSnapshot().notificationTitle, 'Bob sent you a message');
        otherTab.postMessage({ key: 'bob', title: null });
        await vi.waitFor(() => assert.equal(chatSnapshot().notificationTitle, null));
        otherTab.postMessage({ key: 'org:core', title: 'Alice sent you a message' });
        await vi.waitFor(() => assert.equal(chatSnapshot().notificationTitle, 'Alice sent you a message'));
        focused = true;
        window.dispatchEvent(new Event('focus'));
        assert.equal(chatSnapshot().notificationTitle, 'Alice sent you a message');
        assert.deepEqual(acknowledged, []);
        stopThread = watchThread({ kind: 'org', org: { id: 'core', name: 'Core' } });
        await vi.waitFor(() => assert.deepEqual(acknowledged, [{ key: 'org:core', title: null }]));
        assert.equal(chatSnapshot().notificationTitle, null);

        otherTab.postMessage({ key: 'org:core', title: 'Carol sent you a message' });
        await vi.waitFor(() => assert.deepEqual(acknowledged, [{ key: 'org:core', title: null }, { key: 'org:core', title: null }]));
        assert.equal(chatSnapshot().notificationTitle, null);
        assert.equal(shown.length, 0);
        assert.equal(play.mock.calls.length, 0);
    } finally {
        stopThread?.();
        stop();
        otherTab.close();
        vi.useRealTimers();
    }
});

test('restarting alerts during an inbox request waits for its own baseline without replaying old messages', async () => {
    vi.useFakeTimers();
    Object.assign(window, { setTimeout, clearTimeout });
    Object.assign(document, { hidden: false, hasFocus: () => false, cookie: '' });
    let resolveOld: ((response: Response) => void) | undefined;
    let id = 80;
    const requestedBaselines: boolean[] = [];
    vi.stubGlobal('fetch', async (url: string) => {
        const baseline = url.includes('notification_baseline=1');
        requestedBaselines.push(baseline);
        if (requestedBaselines.length === 1) return new Promise<Response>((resolve) => { resolveOld = resolve; });
        return Response.json({ conversations: [conversation(id, { id: 'restarted', kind: 'org' })], has_more: false, unread: 1, requests: 0, ...(baseline ? { notification_baseline: { restarted: 80 } } : {}) });
    });
    const stopOld = watchRemoteAlerts('me');
    stopOld();
    const stop = watchRemoteAlerts('me');
    try {
        assert.ok(resolveOld);
        resolveOld(Response.json({ conversations: [], notification_baseline: {}, has_more: false, unread: 0, requests: 0 }));
        await vi.advanceTimersByTimeAsync(15_000);
        assert.equal(shown.length, 0);
        assert.equal(play.mock.calls.length, 0);
        id = 81;
        await vi.advanceTimersByTimeAsync(15_000);
        assert.equal(shown.length, 1);
        assert.equal(play.mock.calls.length, 1);
        assert.deepEqual(requestedBaselines, [true, true, false]);
    } finally {
        stop();
        vi.useRealTimers();
    }
});

test('an inbox response from before a successful mute cannot alert or replay after unmuting', async () => {
    vi.useFakeTimers();
    Object.assign(window, { setTimeout, clearTimeout });
    Object.assign(document, { hidden: true, cookie: '' });
    const target = { kind: 'org' as const, org: { id: 'mute-race', name: 'Team' } };
    let messages: ConversationSummary[] = [];
    let resolveInbox: ((response: Response) => void) | undefined;
    let delayInbox = false;
    vi.stubGlobal('fetch', async (_url: string, options?: RequestInit) => {
        if (options?.method === 'PUT') {
            return Response.json({ muted_until_at: JSON.parse(options.body as string).duration === 'off' ? null : '9999-12-31T00:00:00Z' });
        }
        if (delayInbox) return new Promise<Response>((resolve) => { resolveInbox = resolve; });
        return Response.json({ conversations: messages, notification_baseline: {}, has_more: false, unread: messages.length, requests: 0 });
    });
    const stop = watchRemoteAlerts('me');
    try {
        await vi.advanceTimersByTimeAsync(60_000);
        delayInbox = true;
        await vi.advanceTimersByTimeAsync(60_000);
        assert.ok(resolveInbox);
        await setConversationMute(target, 'forever');
        messages = [conversation(70, { id: 'mute-race', kind: 'org' })];
        resolveInbox(Response.json({ conversations: messages, has_more: false, unread: 1, requests: 0 }));
        await vi.advanceTimersByTimeAsync(0);
        assert.equal(shown.length, 0);
        assert.equal(play.mock.calls.length, 0);

        delayInbox = false;
        await setConversationMute(target, 'off');
        await vi.advanceTimersByTimeAsync(60_000);
        assert.equal(shown.length, 0);
        messages = [conversation(71, { id: 'mute-race', kind: 'org' })];
        await vi.advanceTimersByTimeAsync(60_000);
        assert.equal(shown.length, 1);
        assert.equal(play.mock.calls.length, 1);
    } finally {
        stop();
        vi.useRealTimers();
    }
});

test('muting while an alert waits for the cross-tab lock suppresses it', async () => {
    vi.useFakeTimers();
    Object.assign(window, { setTimeout, clearTimeout });
    Object.assign(document, { hidden: true, cookie: '' });
    const target = { kind: 'org' as const, org: { id: 'lock-mute', name: 'Team' } };
    let messages: ConversationSummary[] = [];
    let releaseAlert: (() => Promise<void>) | undefined;
    vi.stubGlobal('navigator', { locks: { request: (_name: string, options: LockOptions | (() => Promise<void>), run?: () => Promise<void>) => {
        if (typeof options !== 'function') return run!();
        return new Promise<void>((resolve) => {
            releaseAlert = async () => { await options(); resolve(); };
        });
    } } });
    vi.stubGlobal('fetch', async (_url: string, options?: RequestInit) => {
        if (options?.method === 'PUT') return Response.json({ muted_until_at: '9999-12-31T00:00:00Z' });
        return Response.json({ conversations: messages, notification_baseline: {}, has_more: false, unread: messages.length, requests: 0 });
    });
    const stop = watchRemoteAlerts('me');
    try {
        await vi.advanceTimersByTimeAsync(60_000);
        messages = [conversation(75, { id: 'lock-mute', kind: 'org' })];
        await vi.advanceTimersByTimeAsync(60_000);
        assert.ok(releaseAlert);
        await setConversationMute(target, 'forever');
        await releaseAlert();
        assert.equal(shown.length, 0);
        assert.equal(play.mock.calls.length, 0);
    } finally {
        stop();
        vi.useRealTimers();
    }
});

test('popping an unread stash without opening its thread does not replay old messages, but a new delivery alerts', async () => {
    vi.useFakeTimers();
    Object.assign(window, { setTimeout, clearTimeout });
    Object.assign(document, { hidden: true, cookie: '' });
    const target = { kind: 'org' as const, org: { id: 'old-stash', name: 'Old stash' } };
    let archived = true;
    let id = 80;
    vi.stubGlobal('fetch', async (url: string, options?: RequestInit) => {
        if (options?.method === 'DELETE') {
            archived = false;
            return new Response(null, { status: 204 });
        }
        const stashed = url.includes('tab=stashed');
        return Response.json({
            conversations: stashed === archived ? [conversation(id, { id: 'old-stash', kind: 'org', is_archived: archived })] : [],
            ...(url.includes('notification_baseline=1') ? { notification_baseline: { 'old-stash': id } } : {}),
            has_more: false, unread: archived ? 0 : 1, requests: 0,
        });
    });
    const stop = watchRemoteAlerts('me');
    try {
        await vi.advanceTimersByTimeAsync(60_000);
        await loadTab('stashed');
        assert.equal(await setConversationArchived(target, false), true);
        await vi.advanceTimersByTimeAsync(60_000);
        assert.equal(chatSnapshot().threads['org:old-stash'], undefined);
        assert.equal(chatSnapshot().unread, 1);
        assert.equal(shown.length, 0);
        assert.equal(play.mock.calls.length, 0);

        id = 81;
        await vi.advanceTimersByTimeAsync(60_000);
        assert.equal(shown.length, 1);
        assert.equal(play.mock.calls.length, 1);
    } finally {
        stop();
        vi.useRealTimers();
    }
});

test.each(['blur', 'hidden', 'leave'] as const)('viewing one remote allows alerts from others and suppresses only that remote until %s', async (release) => {
    vi.useFakeTimers();
    Object.assign(window, { setTimeout, clearTimeout, setInterval, clearInterval });
    let focused = true;
    Object.assign(document, { hidden: false, hasFocus: () => focused, cookie: '' });
    let id = 90;
    vi.stubGlobal('fetch', async (url: string) => url.includes('/inbox')
        ? Response.json({ conversations: [conversation(id, { id: 'chat-b', kind: 'org' })], notification_baseline: { 'chat-b': 90 }, has_more: false, unread: 1, requests: 0 })
        : new Response(null, { status: 503 }));
    const background = createRemoteAlerts('me', (item) => `/remotes/orgs/${item.id}`, () => [], (item) => announced.push(item));
    readers.push(background);
    await background.receive([], () => false);
    const stopAlerts = watchRemoteAlerts('me');
    let stopThread: (() => void) | undefined = watchThread({ kind: 'org', org: { id: 'chat-a', name: 'Chat A' } });
    try {
        await vi.advanceTimersByTimeAsync(0);
        id = 91;
        await vi.advanceTimersByTimeAsync(15_000);
        await background.receive([conversation(92, { id: 'chat-a', kind: 'org' })], () => false);
        assert.equal(shown.length, 1);
        assert.equal(play.mock.calls.length, 1);
        assert.equal(chatSnapshot().notificationTitle, 'Alice in an unnamed org sent you a message');
        assert.equal(chatSnapshot().unread, 1);
        assert.equal(chatSnapshot().conversations?.find((item) => item.id === 'chat-b')?.ahead, 1);

        if (release === 'leave') {
            stopThread();
            stopThread = undefined;
        } else {
            focused = false;
            Object.assign(document, { hidden: release === 'hidden' });
            window.dispatchEvent(new Event('blur'));
        }
        await background.receive([conversation(92, { id: 'chat-a', kind: 'org' })], () => false);
        assert.equal(shown.length, 1);
        await background.receive([conversation(93, { id: 'chat-a', kind: 'org' })], () => false);
        assert.equal(shown.length, 2);
        await vi.advanceTimersByTimeAsync(10_000);
        id = 94;
        await vi.advanceTimersByTimeAsync(release === 'hidden' ? 60_000 : 15_000);
        assert.equal(shown.length, 3);
        assert.equal(play.mock.calls.length, 2);
    } finally {
        stopThread?.();
        stopAlerts();
        vi.useRealTimers();
    }
});

test('opening and closing a remote publishes reading state even while its requests fail', async () => {
    vi.useFakeTimers();
    Object.assign(window, { setTimeout, clearTimeout });
    Object.assign(document, { hidden: false, hasFocus: () => true, cookie: '' });
    vi.stubGlobal('fetch', async () => new Response(null, { status: 503 }));
    const background = createRemoteAlerts('me', (item) => `/remotes/orgs/${item.id}`, () => [], (item) => announced.push(item));
    readers.push(background);
    await background.receive([], () => false);
    const stopAlerts = watchRemoteAlerts('me');
    const stopThread = watchThread({ kind: 'org', org: { id: 'reading-org', name: 'Team' } });

    try {
        try {
            await vi.advanceTimersByTimeAsync(0);
            await background.receive([conversation(60, { id: 'reading-org', kind: 'org' })], () => false);
            assert.equal(shown.length, 0);
        } finally {
            stopThread();
        }

        await background.receive([conversation(61, { id: 'reading-org', kind: 'org' })], () => false);
        assert.equal(shown.length, 1);
    } finally {
        stopAlerts();
        vi.useRealTimers();
    }
});

test('an unfocused window does not mark incoming messages read before it can alert', async () => {
    let focused = false;
    vi.stubGlobal('document', { hidden: false, hasFocus: () => focused, cookie: '' });
    vi.stubGlobal('fetch', async () => new Response(null, { status: 204 }));
    const target = { kind: 'org' as const, org: { id: 'unfocused-org', name: 'Team' } };
    seedThread(target, {
        messages: [conversation(50).last_message!], has_older: false,
        reactions_version: 0, reaction_changes: [], read_through_id: 0,
        members: [], typing: [], is_archived: false, pinned_at: null, muted_until_at: null,
        role: 'contributor', access: 'open', pinned: [],
    });

    markThreadRead(target);
    assert.equal(chatSnapshot().threads['org:unfocused-org'].read_through_id, 0);
    focused = true;
    markThreadRead(target);
    assert.equal(chatSnapshot().threads['org:unfocused-org'].read_through_id, 50);
});


test('alerts from recent activity even when older pins occupy the visible first page', async () => {
    vi.useFakeTimers();
    Object.assign(window, { setTimeout, clearTimeout });
    vi.stubGlobal('document', { hidden: true, cookie: '', addEventListener() {}, removeEventListener() {} });
    await setRemoteAlerts('me', true);
    let incoming = conversation(100, { id: 'recent-unpinned', kind: 'org' });
    const pinned = conversation(10, { id: 'old-pinned', kind: 'org', pinned_at: '2026-10-23T10:00:00Z' });
    const requested: string[] = [];
    vi.stubGlobal('fetch', async (url: string) => {
        requested.push(url);
        return Response.json({ conversations: [pinned], notification_conversations: [incoming], notification_baseline: { 'recent-unpinned': 100, 'old-pinned': 10 }, has_more: true, unread: 2, requests: 0 });
    });
    const stop = watchRemoteAlerts('me');
    try {
        await vi.advanceTimersByTimeAsync(60_000);
        incoming = conversation(101, { id: 'recent-unpinned', kind: 'org' });
        await vi.advanceTimersByTimeAsync(60_000);
        assert.equal(shown.length, 1);
        assert.equal(play.mock.calls.length, 1);
        assert.equal(chatSnapshot().conversations?.[0].id, 'old-pinned');
        assert.ok(requested.every((url) => url.includes('notification_conversations=1')));
    } finally {
        stop();
        vi.useRealTimers();
    }
});

test('default notifications stay on-site even when this browser already has OS permission', async () => {
    storage.delete('remote-alerts:me:offsite');
    storage.set('remote-alerts:me', 'on');
    const permissionRequest = vi.spyOn(FakeNotification, 'requestPermission');
    assert.equal(remoteAlertsEnabled('me'), true);
    assert.equal(remoteAlertsEnabled('me', 'offsite'), false);
    await setRemoteAlerts('me', true);
    const alerts = reader();
    await alerts.receive([], () => false);
    await alerts.receive([conversation(1)], () => false);
    assert.equal(announced.length, 1);
    assert.equal(shown.length, 0);
    assert.equal(permissionRequest.mock.calls.length, 0);
});
