import { router } from '@inertiajs/react';
import { useEffect } from 'react';

// The service worker posts a message on every push, see public/sw.js.
export function useLiveNotifications() {
    useEffect(() => {
        if (!('serviceWorker' in navigator)) {
            return;
        }

        const onMessage = (event: MessageEvent<{ type?: string }>) => {
            if (event.data?.type !== 'notification') {
                return;
            }

            // `notifications` only exists on the notifications page; elsewhere it's ignored.
            router.reload({
                only: ['auth', 'notifications'],
                reset: ['notifications'],
            });
        };

        navigator.serviceWorker.addEventListener('message', onMessage);

        return () =>
            navigator.serviceWorker.removeEventListener('message', onMessage);
    }, []);
}
