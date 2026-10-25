import assert from 'node:assert/strict';
import { existsSync, statSync } from 'node:fs';
import { registerHooks } from 'node:module';
import { test } from 'node:test';
import { fileURLToPath, pathToFileURL } from 'node:url';

const resources = new URL('../../resources/js/', import.meta.url);

// Separate module realms model tabs sharing an origin subscription and preference storage.
registerHooks({
    resolve(specifier, context, nextResolve) {
        if (specifier.startsWith('@/') || specifier.startsWith('.')) {
            const target = specifier.startsWith('@/')
                ? new URL(specifier.slice(2), resources)
                : new URL(specifier, context.parentURL);
            const path = fileURLToPath(target);
            const resolved = [path + '.ts', path + '/index.ts', path].find(
                (candidate) => existsSync(candidate) && statSync(candidate).isFile(),
            );
            if (resolved) {
                const realm = context.parentURL ? new URL(context.parentURL).search : '';
                return nextResolve(pathToFileURL(resolved).href + realm, context);
            }
        }
        return nextResolve(specifier, context);
    },
});

for (const cleanup of [false, true]) {
    void test(`a delayed successful ${cleanup ? 'enrollment cleanup' : 'opt-out'} preserves another tab's enrollment`, { timeout: 5_000 }, async () => {
        const originals = new Map(['window', 'document', 'navigator', 'Notification', 'fetch'].map(
            (key) => [key, Object.getOwnPropertyDescriptor(globalThis, key)] as const,
        ));
        const storage = new Map([
            ['remote-alerts:account-a:offsite', 'on'],
            ['remote-alerts:account-b:offsite', 'on'],
        ]);
        let unsubscribes = 0;
        let subscription: { endpoint: string; unsubscribe(): Promise<boolean> } | null = {
            endpoint: 'https://fcm.googleapis.com/wp/shared-browser',
            unsubscribe: async () => { unsubscribes++; subscription = null; return true; },
        };
        const originalSubscription = subscription;
        const registration = { pushManager: {
            getSubscription: async () => subscription,
            subscribe: async () => { throw new Error('The existing subscription must be reused.'); },
        } };
        let current = 'account-a';
        let owner: string | null = 'account-a';
        let releaseDelete!: () => void;
        let notifyDelete!: () => void;
        const deletion = new Promise<void>((resolve) => { notifyDelete = resolve; });
        const response = new Promise<void>((resolve) => { releaseDelete = resolve; });
        let posts = 0;
        const globals = {
            window: { isSecureContext: true, PushManager: class {}, Notification: {}, localStorage: {
                getItem: (key: string) => storage.get(key) ?? null,
                setItem: (key: string, value: string) => storage.set(key, value),
                removeItem: (key: string) => storage.delete(key),
            } },
            document: { cookie: 'XSRF-TOKEN=fixture' },
            Notification: { permission: 'granted', requestPermission: async () => 'granted' },
            navigator: { serviceWorker: { getRegistration: async () => registration } },
            fetch: async (_url: string, options: RequestInit) => {
                if (options.method === 'GET') return Response.json({ public_key: 'BA' });
                assert.ok(typeof options.body === 'string');
                const body = JSON.parse(options.body) as { user_id: string };
                if (body.user_id !== current)
                    return Response.json({ message: 'Your signed-in account changed.' }, { status: 403 });
                if (options.method === 'DELETE') {
                    if (owner === current) owner = null;
                    notifyDelete();
                    await response;
                } else {
                    posts++;
                    if (cleanup && current === 'account-a') return new Response(null, { status: 503 });
                    owner = current;
                }
                return new Response(null, { status: 204 });
            },
        };
        for (const [key, value] of Object.entries(globals))
            Object.defineProperty(globalThis, key, { value, configurable: true, writable: true });
        try {
            const realm = cleanup ? 'cleanup' : 'optout';
            const a = await import(new URL(`lib/remote-notifications.ts?tab=a-${realm}`, resources).href) as typeof import('../../resources/js/lib/remote-notifications');
            const b = await import(new URL(`lib/remote-notifications.ts?tab=b-${realm}`, resources).href) as typeof import('../../resources/js/lib/remote-notifications');
            const bPush = await import(new URL(`lib/remote-push.ts?tab=b-${realm}`, resources).href) as typeof import('../../resources/js/lib/remote-push');
            const disabling = a.setRemoteAlerts('account-a', cleanup, 'offsite').then(() => 'resolved', () => 'rejected');
            await deletion;
            current = 'account-b';
            await b.setRemoteAlerts('account-b', true, 'offsite');
            assert.equal(owner, 'account-b');
            releaseDelete();
            assert.equal(await disabling, cleanup ? 'rejected' : 'resolved');
            assert.equal(subscription, originalSubscription);
            assert.equal(unsubscribes, 0);
            assert.equal(b.remoteAlertsEnabled('account-b', 'offsite'), true);
            const beforeReconcile = posts;
            await bPush.syncRemotePush('account-b', true);
            assert.equal(posts, beforeReconcile + 1);
            assert.equal(owner, 'account-b');
            assert.equal(bPush.remotePushEnabled(), true);
        } finally {
            for (const [key, descriptor] of originals) {
                if (descriptor) Object.defineProperty(globalThis, key, descriptor);
                else Reflect.deleteProperty(globalThis, key);
            }
        }
    });
}
