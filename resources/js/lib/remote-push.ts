import {
    destroy,
    index,
    store,
} from '@/actions/App/Http/Controllers/PushSubscriptionController';
import { readStorage, writeStorage } from '@/lib/storage';

class RemotePushRejectedError extends Error {}

export class RemotePushValidationError extends RemotePushRejectedError {}

class RemotePushRevocationError extends RemotePushValidationError {}

let active = false;
const preferenceKey = (userId: string) => `remote-alerts:${userId}:offsite`;

export function remotePushOptedIn(userId: string): boolean {
    return readStorage(preferenceKey(userId)) === 'on';
}

export function remotePushEnabled(): boolean {
    return active;
}

export function remotePushSupported(): boolean {
    return (
        window.isSecureContext &&
        'Notification' in window &&
        'PushManager' in window &&
        'locks' in navigator &&
        'serviceWorker' in navigator
    );
}

async function request(
    url: string,
    userId: string,
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
            : { body: JSON.stringify({ endpoint, user_id: userId }) }),
    });
    if (response.status === 422) {
        const validation = (await response.json()) as {
            errors: { endpoint: string[] };
        };
        throw new RemotePushValidationError(validation.errors.endpoint[0]);
    }
    if (!response.ok) {
        if (response.status >= 400 && response.status < 500)
            throw new RemotePushRejectedError(
                'Could not save background notifications.',
            );
        throw new Error('Could not save background notifications.');
    }
    return response;
}

/** Native push operations cannot be aborted, but must not hold the settings queue forever. */
function pushResult<T>(operation: Promise<T>): Promise<T> {
    let timer: ReturnType<typeof setTimeout>;
    const timeout = new Promise<never>((_, reject) => {
        timer = setTimeout(
            () =>
                reject(
                    new Error('The browser did not respond. Please try again.'),
                ),
            10_000,
        );
    });
    return Promise.race([operation, timeout]).finally(() =>
        clearTimeout(timer),
    );
}

/** Recheck consent after acquiring the same origin-wide lock used by explicit choices. */
export function syncRemotePush(userId: string): Promise<void> {
    if (!remotePushSupported()) return Promise.resolve();
    return navigator.locks.request(
        'remote-push',
        { signal: AbortSignal.timeout(10_000) },
        () => updateRemotePush(userId, remotePushOptedIn(userId)),
    );
}

export async function setRemotePush(
    userId: string,
    enabled: boolean,
): Promise<boolean> {
    if (!remotePushSupported())
        throw new Error('Off-site notifications are not supported here.');
    if (enabled) {
        const permission = await Notification.requestPermission();
        if (permission !== 'granted')
            throw new Error('Notification permission was not granted.');
    }

    // Keep preference commits inside the lock so another tab cannot enroll after a successful opt-out.
    return navigator.locks.request(
        'remote-push',
        { signal: AbortSignal.timeout(10_000) },
        async () => {
            const key = preferenceKey(userId);
            const previous = readStorage(key);
            if (enabled) {
                writeStorage(key, 'on');
                if (!remotePushOptedIn(userId)) return false;
                try {
                    await updateRemotePush(userId, true, true);
                } catch (error) {
                    if (!(error instanceof RemotePushRevocationError))
                        writeStorage(key, previous);
                    throw error;
                }
            } else {
                await updateRemotePush(userId, false, true);
                writeStorage(key, 'off');
            }
            return remotePushOptedIn(userId) === enabled;
        },
    );
}

/** Reuse an existing subscription on load; only a user gesture may create one. */
async function updateRemotePush(
    userId: string,
    enabled: boolean,
    create = false,
): Promise<void> {
    if (Notification.permission !== 'granted' && (!create || enabled)) return;
    let publicKey: string | null = null;
    if (enabled) {
        const config = (await (await request(index.url(), userId)).json()) as {
            public_key: string | null;
        };
        publicKey = config.public_key;
        if (!publicKey) {
            active = false;
            if (create)
                throw new Error('Background notifications are unavailable.');
            return;
        }
    }
    let registration = await pushResult(
        navigator.serviceWorker.getRegistration(),
    );
    if (!registration && create && enabled) {
        await pushResult(
            navigator.serviceWorker.register('/sw.js', {
                scope: '/',
                updateViaCache: 'none',
            }),
        );
        registration = await pushResult(navigator.serviceWorker.ready);
    }
    if (!registration) return;
    let subscription = await pushResult(
        registration.pushManager.getSubscription(),
    );
    if (!subscription) active = false;
    if (enabled && publicKey) {
        if (!subscription && create) {
            const key = publicKey.replace(/-/g, '+').replace(/_/g, '/');
            subscription = await pushResult(
                registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: Uint8Array.from(
                        atob(key.padEnd(Math.ceil(key.length / 4) * 4, '=')),
                        (char) => char.charCodeAt(0),
                    ),
                }),
            );
        }
        if (subscription) {
            try {
                await request(
                    store.url(),
                    userId,
                    'POST',
                    subscription.endpoint,
                );
            } catch (error) {
                if (error instanceof RemotePushRejectedError) {
                    active = false;
                    throw error;
                }
                if (create) {
                    active = false;
                    // A failed opt-in may have been saved before its response was lost.
                    try {
                        await request(
                            destroy.url(),
                            userId,
                            'DELETE',
                            subscription.endpoint,
                        );
                    } catch {
                        throw new RemotePushRevocationError(
                            'Could not turn off-site notifications off. Please try again.',
                        );
                    }
                }
                throw error;
            }
            active = true;
        }
    } else if (subscription) {
        await request(destroy.url(), userId, 'DELETE', subscription.endpoint);
        active = false;
    }
}
