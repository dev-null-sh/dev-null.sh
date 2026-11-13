import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { store } from '@/actions/App/Http/Controllers/BroadcastAuthorizationController';
import type { RealtimeConnection } from '@/lib/realtime';

const xsrfToken = (): string => {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);

    return match ? decodeURIComponent(match[1]) : '';
};

/**
 * Connects to the server speaking the Pusher protocol that the build was configured with, such as Laravel Cloud's
 * WebSockets or Reverb. A build without one, like local development by default, has no live updates.
 */
export function createEcho(): RealtimeConnection | null {
    const key = import.meta.env.VITE_REVERB_APP_KEY as string | undefined;

    if (!key || typeof window === 'undefined') {
        return null;
    }

    const scheme =
        (import.meta.env.VITE_REVERB_SCHEME as string | undefined) ?? 'https';
    const port = Number(
        (import.meta.env.VITE_REVERB_PORT as string | undefined) ??
            (scheme === 'https' ? 443 : 80),
    );

    return new Echo({
        broadcaster: 'reverb',
        key,
        Pusher,
        wsHost:
            (import.meta.env.VITE_REVERB_HOST as string | undefined) ??
            window.location.hostname,
        wsPort: port,
        wssPort: port,
        forceTLS: scheme === 'https',
        enabledTransports: ['ws', 'wss'],
        channelAuthorization: {
            endpoint: store.url(),
            transport: 'ajax',
            headersProvider: () => ({
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': xsrfToken(),
            }),
        },
    });
}
