import { remoteAlertsEnabled, setRemoteAlerts } from '../../resources/js/lib/remote-notifications';
import assert from 'node:assert/strict';
import { afterEach, beforeEach, test, vi } from 'vite-plus/test';
import { remotePushEnabled, subscribeRemotePush, syncRemotePush } from '../../resources/js/lib/remote-push';

const endpoint = 'https://fcm.googleapis.com/wp/browser';
const save = vi.fn(async (_url: string, _options: RequestInit) => new Response(null, { status: 204 }));
const unsubscribe = vi.fn(async () => true);
let subscription: { endpoint: string; unsubscribe: typeof unsubscribe } | null;
const subscribe = vi.fn(async () => ({ endpoint, unsubscribe }));
const registration = { pushManager: { getSubscription: async () => subscription, subscribe } };

beforeEach(() => {
    subscription = null;
    vi.stubGlobal('window', { isSecureContext: true, PushManager: class {} });
    vi.stubGlobal('Notification', { permission: 'granted' });
    vi.stubGlobal('document', { cookie: 'XSRF-TOKEN=csrf%20token' });
    vi.stubGlobal('navigator', { serviceWorker: { getRegistration: async () => registration, register: vi.fn(async () => registration), ready: Promise.resolve(registration) } });
    vi.stubGlobal('fetch', async (url: string, options: RequestInit) => url.endsWith('/push-subscriptions') && options.method === 'GET'
        ? Response.json({ public_key: 'BA' }) : save(url, options));
    save.mockClear(); subscribe.mockClear(); unsubscribe.mockClear();
});

afterEach(() => {vi.unstubAllGlobals();vi.restoreAllMocks();vi.useRealTimers();});

test('does not subscribe or request permission on page load', async () => {
    await syncRemotePush(true);
    assert.equal(subscribe.mock.calls.length, 0);
    assert.equal(save.mock.calls.length, 0);
    assert.equal(remotePushEnabled(), false);
});

test('creates an opt-in subscription with only its endpoint sent to the account', async () => {
    const changed = vi.fn();
    const stop = subscribeRemotePush(changed);
    await syncRemotePush(true, true);
    stop();
    assert.equal(remotePushEnabled(), true);
    assert.equal(subscribe.mock.calls.length, 1);
    assert.equal(changed.mock.calls.length, 1);
    const [, options] = save.mock.calls[0];
    assert.equal(options.method, 'POST');
    assert.equal(options.body, JSON.stringify({ endpoint }));
    assert.equal((options.headers as Record<string, string>)['X-XSRF-TOKEN'], 'csrf token');
});

test('reassociates an existing browser subscription without creating another', async () => {
    subscription = { endpoint, unsubscribe };
    await syncRemotePush(true);
    assert.equal(subscribe.mock.calls.length, 0);
    assert.equal(save.mock.calls.length, 1);
    assert.equal(remotePushEnabled(), true);
});

test('turning notifications off revokes server delivery before unsubscribing', async () => {
    subscription = { endpoint, unsubscribe };
    await syncRemotePush(false, true);
    assert.equal(save.mock.calls[0][1].method, 'DELETE');
    assert.equal(unsubscribe.mock.calls.length, 1);
    assert.equal(remotePushEnabled(), false);
});

test('failed server deletion keeps the subscription so disabling can be retried', async () => {
    subscription = { endpoint, unsubscribe };
    save.mockImplementationOnce(async () => new Response(null, { status: 503 }));
    await assert.rejects(syncRemotePush(false, true));
    assert.equal(unsubscribe.mock.calls.length, 0);
    await syncRemotePush(false, true);
    assert.equal(unsubscribe.mock.calls.length, 1);
});

test('missing server configuration reports failure rather than enabling background delivery', async () => {
    vi.stubGlobal('fetch', async () => Response.json({ public_key: null }));
    await assert.rejects(syncRemotePush(true, true));
    assert.equal(remotePushEnabled(), false);
    assert.equal(subscribe.mock.calls.length, 0);
});

test('registers a service worker before the first opt-in', async () => {
    const register = vi.fn(async () => registration);
    vi.stubGlobal('navigator', { serviceWorker: { getRegistration: async () => undefined, register, ready: Promise.resolve(registration) } });
    await syncRemotePush(true, true);
    assert.equal(register.mock.calls.length, 1);
    assert.equal(remotePushEnabled(), true);
});

test('unsupported browsers retain their page alert fallback', async () => {
    vi.stubGlobal('window', { isSecureContext: true });
    await syncRemotePush(true, true);
    assert.equal(save.mock.calls.length, 0);
});

test('page alerts can be re-enabled even when background delivery is unavailable', async () => {
    const storage = new Map([['remote-alerts:me:notifications', 'off']]);
    Object.assign(window, {localStorage: {getItem: (key: string) => storage.get(key) ?? null, setItem: (key: string, value: string) => storage.set(key, value)}});
    vi.stubGlobal('Notification', {permission: 'granted', requestPermission: async () => 'granted'});
    vi.stubGlobal('fetch', async () => Response.json({public_key: null}));
    await assert.rejects(setRemoteAlerts('me', true));
    assert.equal(remoteAlertsEnabled('me'), true);
    assert.equal(remotePushEnabled(), false);
});

test('a stalled browser operation times out without saving and the next attempt can recover', async () => {
    vi.useFakeTimers();
    vi.spyOn(registration.pushManager, 'getSubscription').mockImplementationOnce(() => new Promise(() => {}));
    const rejected = assert.rejects(syncRemotePush(true, true), /browser did not respond/);
    await vi.advanceTimersByTimeAsync(10_001);
    await rejected;
    assert.equal(save.mock.calls.length, 0);
    await syncRemotePush(true, true);
    assert.equal(save.mock.calls.length, 1);
    assert.equal(remotePushEnabled(), true);
});

test('does not query native subscriptions before permission or server configuration is ready', async () => {
    const query = vi.spyOn(registration.pushManager, 'getSubscription');
    vi.stubGlobal('Notification', {permission: 'default'});
    await syncRemotePush(true);
    assert.equal(query.mock.calls.length, 0);
    vi.stubGlobal('Notification', {permission: 'granted'});
    vi.stubGlobal('fetch', async () => Response.json({public_key: null}));
    await assert.rejects(syncRemotePush(true, true));
    assert.equal(query.mock.calls.length, 0);
});
