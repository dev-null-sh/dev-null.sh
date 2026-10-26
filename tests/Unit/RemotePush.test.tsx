import { remoteAlertsEnabled, setRemoteAlerts } from '../../resources/js/lib/remote-notifications';
import assert from 'node:assert/strict';
import { afterEach, beforeEach, test, vi } from 'vite-plus/test';
import { remotePushEnabled, setRemotePush, syncRemotePush } from '../../resources/js/lib/remote-push';

const endpoint = 'https://fcm.googleapis.com/wp/browser';
const save = vi.fn(async (_url: string, _options: RequestInit) => new Response(null, { status: 204 }));
const unsubscribe = vi.fn(async () => {subscription = null; return true;});
let subscription: { endpoint: string; unsubscribe: typeof unsubscribe } | null;
const subscribe = vi.fn(async () => subscription = { endpoint, unsubscribe });
const registration = { pushManager: { getSubscription: async () => subscription, subscribe } };

beforeEach(() => {
    subscription = null;
    let pending: Promise<unknown> = Promise.resolve();
    const locks = { request: (_name: string, _options: LockOptions, run: () => Promise<unknown>) => { const result = pending.then(run); pending = result.catch(() => undefined); return result; } };
    vi.stubGlobal('window', { isSecureContext: true, PushManager: class {}, Notification: {} });
    const storage = preferenceStorage();
    storage.set('remote-alerts:me:offsite', 'on');
    vi.stubGlobal('Notification', { permission: 'granted', requestPermission: vi.fn(async () => 'granted') });
    vi.stubGlobal('document', { cookie: 'XSRF-TOKEN=csrf%20token' });
    vi.stubGlobal('navigator', { locks, serviceWorker: { getRegistration: async () => registration, register: vi.fn(async () => registration), ready: Promise.resolve(registration) } });
    vi.stubGlobal('fetch', async (url: string, options: RequestInit) => url.endsWith('/push-subscriptions') && options.method === 'GET'
        ? Response.json({ public_key: 'BA' }) : save(url, options));
    save.mockReset(); save.mockImplementation(async () => new Response(null, { status: 204 })); subscribe.mockClear(); unsubscribe.mockClear();
});

afterEach(() => {vi.unstubAllGlobals();vi.restoreAllMocks();vi.useRealTimers();});

test('does not subscribe or request permission on page load', async () => {
    await syncRemotePush('me');
    assert.equal(subscribe.mock.calls.length, 0);
    assert.equal(save.mock.calls.length, 0);
    assert.equal(remotePushEnabled(), false);
});

test('creates an opt-in subscription bound to its initiating account without encryption secrets', async () => {
    await setRemotePush('me', true);
    assert.equal(remotePushEnabled(), true);
    assert.equal(subscribe.mock.calls.length, 1);
    const [, options] = save.mock.calls[0];
    assert.equal(options.method, 'POST');
    assert.equal(options.body, JSON.stringify({ endpoint, user_id: 'me' }));
    assert.equal((options.headers as Record<string, string>)['X-XSRF-TOKEN'], 'csrf token');
});

test('reassociates an existing browser subscription without creating another', async () => {
    subscription = { endpoint, unsubscribe };
    await syncRemotePush('me');
    assert.equal(subscribe.mock.calls.length, 0);
    assert.equal(save.mock.calls.length, 1);
    assert.equal(remotePushEnabled(), true);
});

test('a transient passive registration failure preserves opt-in and recovers on the next load', async () => {
    preferenceStorage();
    assert.equal(await setRemoteAlerts('me', true, 'offsite'), true);
    const enrolled = subscription;
    save.mockImplementationOnce(async () => new Response(null, { status: 503 }));

    await assert.rejects(syncRemotePush('me'));
    assert.equal(subscription, enrolled);
    assert.equal(unsubscribe.mock.calls.length, 0);
    assert.equal(save.mock.calls.filter(([, options]) => options.method === 'DELETE').length, 0);
    assert.equal(remoteAlertsEnabled('me', 'offsite'), true);
    assert.equal(remotePushEnabled(), true);

    await syncRemotePush('me');
    assert.equal(save.mock.calls.at(-1)?.[1].method, 'POST');
    assert.equal(subscribe.mock.calls.length, 1);
    assert.equal(remotePushEnabled(), true);
    assert.equal(remoteAlertsEnabled('me', 'offsite'), true);
});

test('turning notifications off revokes server delivery while retaining the shared browser subscription', async () => {
    subscription = { endpoint, unsubscribe };
    const existing = subscription;
    await setRemotePush('me', false);
    assert.equal(save.mock.calls[0][1].method, 'DELETE');
    assert.equal(subscription, existing);
    assert.equal(unsubscribe.mock.calls.length, 0);
    assert.equal(remotePushEnabled(), false);
});

test('failed server deletion keeps the subscription so disabling can be retried', async () => {
    subscription = { endpoint, unsubscribe };
    save.mockImplementationOnce(async () => new Response(null, { status: 503 }));
    await assert.rejects(setRemotePush('me', false));
    assert.equal(unsubscribe.mock.calls.length, 0);
    await setRemotePush('me', false);
    assert.equal(unsubscribe.mock.calls.length, 0);
});

test('missing server configuration reports failure rather than enabling background delivery', async () => {
    vi.stubGlobal('fetch', async () => Response.json({ public_key: null }));
    await assert.rejects(setRemotePush('me', true));
    assert.equal(remotePushEnabled(), false);
    assert.equal(subscribe.mock.calls.length, 0);
});

test('registers a service worker before the first opt-in', async () => {
    const register = vi.fn(async () => registration);
    vi.stubGlobal('navigator', { locks: navigator.locks, serviceWorker: { getRegistration: async () => undefined, register, ready: Promise.resolve(registration) } });
    await setRemotePush('me', true);
    assert.equal(register.mock.calls.length, 1);
    assert.equal(remotePushEnabled(), true);
});

test('unsupported browsers reject off-site enrollment and retain their page alert fallback', async () => {
    vi.stubGlobal('window', { isSecureContext: true });
    await assert.rejects(setRemotePush('me', true), /not supported/);
    assert.equal(save.mock.calls.length, 0);
});

test('page alerts can be re-enabled even when background delivery is unavailable', async () => {
    const storage = new Map([['remote-alerts:me:notifications', 'off']]);
    Object.assign(window, {localStorage: {getItem: (key: string) => storage.get(key) ?? null, setItem: (key: string, value: string) => storage.set(key, value)}});
    vi.stubGlobal('Notification', {permission: 'granted', requestPermission: async () => 'granted'});
    vi.stubGlobal('fetch', async () => Response.json({public_key: null}));
    assert.equal(await setRemoteAlerts('me', true), true);
    assert.equal(remoteAlertsEnabled('me'), true);
    assert.equal(remoteAlertsEnabled('me', 'offsite'), false);
    assert.equal(save.mock.calls.length, 0);
});

test('a stalled browser operation times out without saving and the next attempt can recover', async () => {
    vi.useFakeTimers();
    vi.spyOn(registration.pushManager, 'getSubscription').mockImplementationOnce(() => new Promise(() => {}));
    const rejected = assert.rejects(setRemotePush('me', true), /browser did not respond/);
    await vi.advanceTimersByTimeAsync(10_001);
    await rejected;
    assert.equal(save.mock.calls.length, 0);
    await setRemotePush('me', true);
    assert.equal(save.mock.calls.length, 1);
    assert.equal(remotePushEnabled(), true);
});

test('does not query native subscriptions before permission or server configuration is ready', async () => {
    const query = vi.spyOn(registration.pushManager, 'getSubscription');
    vi.stubGlobal('Notification', {permission: 'default'});
    await syncRemotePush('me');
    assert.equal(query.mock.calls.length, 0);
    vi.stubGlobal('Notification', {permission: 'granted'});
    vi.stubGlobal('fetch', async () => Response.json({public_key: null}));
    await assert.rejects(setRemotePush('me', true));
    assert.equal(query.mock.calls.length, 0);
});

function preferenceStorage(): Map<string, string> {
    const storage = new Map<string, string>();
    Object.assign(window, {localStorage: {getItem: (key: string) => storage.get(key) ?? null, setItem: (key: string, value: string) => storage.set(key, value), removeItem: (key: string) => storage.delete(key)}});
    return storage;
}

test('off-site delivery requires a separate explicit opt-in and turning it off revokes delivery', async () => {
    const storage = preferenceStorage();
    assert.equal(remoteAlertsEnabled('me'), true);
    assert.equal(remoteAlertsEnabled('me', 'offsite'), false);
    await setRemoteAlerts('me', true);
    assert.equal(subscribe.mock.calls.length, 0);
    assert.equal((Notification.requestPermission as ReturnType<typeof vi.fn>).mock.calls.length, 0);
    assert.equal(await setRemoteAlerts('me', true, 'offsite'), true);
    assert.equal(storage.get('remote-alerts:me:offsite'), 'on');
    assert.equal(remotePushEnabled(), true);
    assert.equal(await setRemoteAlerts('me', false, 'offsite'), true);
    assert.equal(remoteAlertsEnabled('me', 'offsite'), false);
    assert.equal(remoteAlertsEnabled('me'), true);
    assert.equal(unsubscribe.mock.calls.length, 0);
    assert.equal(save.mock.calls.at(-1)?.[1].method, 'DELETE');
});

test('denied permission and failed enrollment leave off-site notifications off', async () => {
    const storage = preferenceStorage();
    vi.stubGlobal('Notification', {permission: 'denied', requestPermission: async () => 'denied'});
    await assert.rejects(setRemoteAlerts('me', true, 'offsite'));
    assert.equal(remoteAlertsEnabled('me', 'offsite'), false);
    assert.equal(subscribe.mock.calls.length, 0);
    vi.stubGlobal('Notification', {permission: 'granted', requestPermission: async () => 'granted'});
    save.mockImplementationOnce(async () => new Response(null, {status:503}));
    await assert.rejects(setRemoteAlerts('me', true, 'offsite'));
    assert.equal(remoteAlertsEnabled('me', 'offsite'), false);
    assert.equal(storage.has('remote-alerts:me:offsite'), false);
    assert.equal(save.mock.calls.at(-1)?.[1].method, 'DELETE');
    assert.equal(unsubscribe.mock.calls.length, 0);
});

test('blocked preference storage cannot activate off-site delivery', async () => {
    Object.assign(window, {localStorage: {getItem: () => null, setItem() {throw new Error('blocked');}}});
    assert.equal(await setRemoteAlerts('me', true, 'offsite'), false);
    assert.equal(subscribe.mock.calls.length, 0);
    assert.equal(save.mock.calls.length, 0);
});

test('a failed browser lock preserves consent and a later opt-out can retry', async () => {
    subscription = { endpoint, unsubscribe };
    const lock = vi.spyOn(navigator.locks, 'request');
    lock.mockRejectedValueOnce(new DOMException('Lock wait expired', 'AbortError'));
    await assert.rejects(setRemoteAlerts('me', false, 'offsite'));
    assert.equal(remoteAlertsEnabled('me', 'offsite'), true);
    assert.equal(save.mock.calls.length, 0);

    assert.equal(await setRemoteAlerts('me', false, 'offsite'), true);
    assert.equal(remoteAlertsEnabled('me', 'offsite'), false);
    assert.equal(save.mock.calls[0][1].method, 'DELETE');
});

test('browsers without shared locking cannot enroll off-site delivery', async () => {
    Reflect.deleteProperty(navigator, 'locks');
    await assert.rejects(setRemoteAlerts('me', true, 'offsite'), /not supported/);
    assert.equal(subscribe.mock.calls.length, 0);
    assert.equal(save.mock.calls.length, 0);
});

test('passive work queued behind opt-out reads the committed off preference', async () => {
    subscription = { endpoint, unsubscribe };
    let resume!: () => void;
    const gate = new Promise<void>((resolve) => { resume = resolve; });
    const query = vi.spyOn(registration.pushManager, 'getSubscription').mockImplementationOnce(async () => { await gate; return subscription; });
    const disabling = setRemoteAlerts('me', false, 'offsite');
    await vi.waitFor(() => assert.equal(query.mock.calls.length, 1));
    const passive = syncRemotePush('me');
    resume();
    await Promise.all([disabling, passive]);

    assert.equal(remoteAlertsEnabled('me', 'offsite'), false);
    assert.deepEqual(save.mock.calls.map(([, options]) => options.method), ['DELETE', 'DELETE']);
});


test('a full account reports the enrollment limit and leaves this browser opted out', async () => {
    preferenceStorage();
    const message = 'You have off-site notifications enabled in 20 browsers or apps. Turn them off in one before enabling another.';
    save.mockImplementationOnce(async () => Response.json({errors: {endpoint: [message]}}, {status: 422}));
    await assert.rejects(setRemoteAlerts('me', true, 'offsite'), {message});
    assert.equal(remoteAlertsEnabled('me', 'offsite'), false);
    assert.equal(unsubscribe.mock.calls.length, 0);
});


for (const phase of ['permission', 'subscription', 'queue'] as const) {
    test(`an account switch during ${phase} cannot apply another account's consent`, async () => {
        preferenceStorage();
        let current = 'account-a';
        let owner: string | null = null;
        let resume!: () => void;
        const gate = new Promise<void>((resolve) => { resume = resolve; });
        let blocker: Promise<boolean> | undefined;
        let blocked = false;
        save.mockImplementation(async (_url, options) => {
            const body = JSON.parse(options.body as string) as { user_id?: string };
            if (body.user_id !== current) return Response.json({ message: 'Your signed-in account changed.' }, { status: 403 });
            owner = current;
            return new Response(null, { status: 204 });
        });
        if (phase === 'permission') {
            vi.stubGlobal('Notification', { permission: 'granted', requestPermission: async () => { await gate; return 'granted'; } });
        } else if (phase === 'subscription') {
            subscribe.mockImplementationOnce(async () => { await gate; return subscription = { endpoint, unsubscribe }; });
        } else {
            vi.spyOn(navigator.serviceWorker, 'getRegistration').mockImplementationOnce(async () => { blocked = true; await gate; return undefined; });
            blocker = setRemoteAlerts('blocker', false, 'offsite');
        }
        const enabling = setRemoteAlerts('account-a', true, 'offsite');
        const rejected = assert.rejects(enabling);
        if (phase === 'subscription') await vi.waitFor(() => assert.equal(subscribe.mock.calls.length, 1));
        if (phase === 'queue') await vi.waitFor(() => assert.equal(blocked, true));
        current = 'account-b';
        resume();
        if (blocker) await blocker;
        await rejected;
        const post = save.mock.calls.find(([, options]) => options.method === 'POST');
        assert.equal(JSON.parse(post![1].body as string).user_id, 'account-a');
        assert.equal(owner, null);
        assert.equal(remoteAlertsEnabled('account-a', 'offsite'), false);
        assert.equal(remoteAlertsEnabled('account-b', 'offsite'), false);
        assert.deepEqual(save.mock.calls.map(([, options]) => options.method), ['POST']);
        assert.equal(unsubscribe.mock.calls.length, 0);
    });
}

test('a changed account rejection preserves the current accounts existing browser subscription', async () => {
    const storage = preferenceStorage();
    storage.set('remote-alerts:account-b:offsite', 'on');
    subscription = { endpoint, unsubscribe };
    const existing = subscription;
    save.mockImplementation(async () => Response.json({ message: 'Your signed-in account changed.' }, { status: 403 }));
    await assert.rejects(setRemoteAlerts('account-a', true, 'offsite'));
    assert.equal(subscription, existing);
    assert.equal(unsubscribe.mock.calls.length, 0);
    assert.deepEqual(save.mock.calls.map(([, options]) => options.method), ['POST']);
    assert.equal(remoteAlertsEnabled('account-a', 'offsite'), false);
    assert.equal(remoteAlertsEnabled('account-b', 'offsite'), true);
});

test('failed enrollment cleanup cannot unsubscribe a changed account', async () => {
    preferenceStorage();
    subscription = { endpoint, unsubscribe };
    save.mockImplementation(async (_url, options) => options.method === 'POST'
        ? new Response(null, { status: 503 })
        : Response.json({ message: 'Your signed-in account changed.' }, { status: 403 }));
    await assert.rejects(setRemoteAlerts('account-a', true, 'offsite'), /Could not turn off-site notifications off/);
    assert.equal(remoteAlertsEnabled('account-a', 'offsite'), true);
    assert.equal(unsubscribe.mock.calls.length, 0);
    assert.deepEqual(save.mock.calls.map(([, options]) => JSON.parse(options.body as string).user_id), ['account-a', 'account-a']);
});

for (const status of [401, 403, 419, 429]) {
    test(`a rejected enrollment (${status}) leaves consent off without deleting a shared registration`, async () => {
        preferenceStorage();
        subscription = { endpoint, unsubscribe };
        const existing = subscription;
        save.mockImplementationOnce(async () => new Response(null, { status }));
        await assert.rejects(setRemoteAlerts('me', true, 'offsite'));
        assert.equal(remoteAlertsEnabled('me', 'offsite'), false);
        assert.equal(subscription, existing);
        assert.deepEqual(save.mock.calls.map(([, options]) => options.method), ['POST']);
    });
}

for (const failure of ['response', 'network'] as const) {
    test(`an uncertain ${failure} enrollment keeps consent visible until server revocation succeeds`, async () => {
        preferenceStorage();
        let enrolled = false;
        let revocationFails = true;
        save.mockImplementation(async (_url, options) => {
            if (options.method === 'POST') {
                enrolled = true;
                if (failure === 'network') throw new TypeError('Connection lost');
                return new Response(null, { status: 503 });
            }
            if (revocationFails) return new Response(null, { status: 503 });
            enrolled = false;
            return new Response(null, { status: 204 });
        });
        await assert.rejects(setRemoteAlerts('me', true, 'offsite'), /Could not turn off-site notifications off/);
        assert.equal(enrolled, true);
        assert.equal(remoteAlertsEnabled('me', 'offsite'), true);
        const existing = subscription;
        assert.ok(existing);
        revocationFails = false;
        assert.equal(await setRemoteAlerts('me', false, 'offsite'), true);
        assert.equal(enrolled, false);
        assert.equal(remoteAlertsEnabled('me', 'offsite'), false);
        assert.equal(subscription, existing);
        assert.deepEqual(save.mock.calls.map(([, options]) => options.method), ['POST', 'DELETE', 'DELETE']);
    });
}

test('a stale account opt-out preserves the current accounts consent and native subscription', async () => {
    const storage = preferenceStorage();
    storage.set('remote-alerts:account-a:offsite', 'on');
    storage.set('remote-alerts:account-b:offsite', 'on');
    subscription = { endpoint, unsubscribe };
    save.mockImplementation(async () => Response.json({ message: 'Your signed-in account changed.' }, { status: 403 }));
    await assert.rejects(setRemoteAlerts('account-a', false, 'offsite'));
    assert.equal(JSON.parse(save.mock.calls[0][1].body as string).user_id, 'account-a');
    assert.equal(unsubscribe.mock.calls.length, 0);
    assert.equal(remoteAlertsEnabled('account-a', 'offsite'), true);
    assert.equal(remoteAlertsEnabled('account-b', 'offsite'), true);
});
