import {
    destroy,
    index,
    store,
} from '@/actions/App/Http/Controllers/PushSubscriptionController';

let active = false;
const listeners = new Set<() => void>();
let pending = Promise.resolve();

export function remotePushEnabled(): boolean {
    return active;
}

export function subscribeRemotePush(listener: () => void): () => void {
    listeners.add(listener);
    return () => listeners.delete(listener);
}

async function request(
    url: string,
    method = 'GET',
    endpoint?: string,
): Promise<Response> {
    const response = await fetch(url, {
        method,
        credentials: 'same-origin',
        cache: 'no-store',
        signal: AbortSignal.timeout(10_000),
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-XSRF-TOKEN': decodeURIComponent(
                document.cookie
                    .split('; ')
                    .find((cookie) => cookie.startsWith('XSRF-TOKEN='))
                    ?.slice(11) ?? '',
            ),
        },
        ...(endpoint === undefined
            ? {}
            : { body: JSON.stringify({ endpoint }) }),
    });
    if (!response.ok)
        throw new Error('Could not save background notifications.');
    return response;
}

/** Reuse an existing subscription on load; only a user gesture may create one. */
export function syncRemotePush(
    enabled: boolean,
    create = false,
): Promise<void> {
    const sync = async () => {
        if (
            !window.isSecureContext ||
            !('serviceWorker' in navigator) ||
            !('PushManager' in window)
        )
            return;
        let registration = await navigator.serviceWorker.getRegistration();
        if (!registration && create && enabled) {
            await navigator.serviceWorker.register('/sw.js', {
                scope: '/',
                updateViaCache: 'none',
            });
            registration = await navigator.serviceWorker.ready;
        }
        if (!registration) return;
        let subscription = await registration.pushManager.getSubscription();
        if (!subscription) active = false;
        if (enabled && Notification.permission === 'granted') {
            const config = (await (await request(index.url())).json()) as {
                public_key: string | null;
            };
            if (!config.public_key) {
                active = false;
                listeners.forEach((listener) => listener());
                if (create)
                    throw new Error(
                        'Background notifications are unavailable.',
                    );
                return;
            }
            if (!subscription && create) {
                const key = config.public_key
                    .replace(/-/g, '+')
                    .replace(/_/g, '/');
                subscription = await registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: Uint8Array.from(
                        atob(key.padEnd(Math.ceil(key.length / 4) * 4, '=')),
                        (char) => char.charCodeAt(0),
                    ),
                });
            }
            if (subscription) {
                await request(store.url(), 'POST', subscription.endpoint);
                active = true;
            }
        } else if (subscription) {
            await request(destroy.url(), 'DELETE', subscription.endpoint);
            await subscription.unsubscribe();
            active = false;
        }
        listeners.forEach((listener) => listener());
    };
    const result = pending.then(sync);
    pending = result.catch(() => undefined);
    return result;
}
