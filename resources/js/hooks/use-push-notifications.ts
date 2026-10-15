import { router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import PushSubscriptionController from '@/actions/App/Http/Controllers/PushSubscriptionController';

type PushState = 'unsupported' | 'denied' | 'disabled' | 'enabled' | 'busy';

const isSupported = () =>
    typeof window !== 'undefined' &&
    'serviceWorker' in navigator &&
    'PushManager' in window &&
    'Notification' in window;

// The browser wants the VAPID key as raw bytes, the server gives base64url.
function decodeKey(key: string): Uint8Array<ArrayBuffer> {
    const base64 = (key + '='.repeat((4 - (key.length % 4)) % 4))
        .replace(/-/g, '+')
        .replace(/_/g, '/');

    return Uint8Array.from(atob(base64), (char) => char.charCodeAt(0));
}

async function detectState(vapidPublicKey: string | null): Promise<PushState> {
    if (!isSupported() || !vapidPublicKey) {
        return 'unsupported';
    }

    if (Notification.permission === 'denied') {
        return 'denied';
    }

    const registration = await navigator.serviceWorker.register('/sw.js');
    const subscription = await registration.pushManager.getSubscription();

    return subscription ? 'enabled' : 'disabled';
}

export function usePushNotifications() {
    const { vapidPublicKey } = usePage().props;
    const [state, setState] = useState<PushState>('busy');

    useEffect(() => {
        void detectState(vapidPublicKey).then(setState);
    }, [vapidPublicKey]);

    const enable = async () => {
        setState('busy');

        if ((await Notification.requestPermission()) !== 'granted') {
            setState('denied');

            return;
        }

        const registration = await navigator.serviceWorker.ready;
        const subscription = await registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: decodeKey(vapidPublicKey!),
        });

        router.post(
            PushSubscriptionController.store(),
            {
                ...subscription.toJSON(),
                content_encoding:
                    PushManager.supportedContentEncodings?.[0] ?? 'aes128gcm',
            },
            {
                preserveScroll: true,
                onSuccess: () => setState('enabled'),
                onError: () => setState('disabled'),
            },
        );
    };

    const disable = async () => {
        setState('busy');

        const registration = await navigator.serviceWorker.ready;
        const subscription = await registration.pushManager.getSubscription();

        if (!subscription) {
            setState('disabled');

            return;
        }

        await subscription.unsubscribe();

        router.delete(PushSubscriptionController.destroy(), {
            data: { endpoint: subscription.endpoint },
            preserveScroll: true,
            onFinish: () => setState('disabled'),
        });
    };

    return { state, enable, disable };
}
