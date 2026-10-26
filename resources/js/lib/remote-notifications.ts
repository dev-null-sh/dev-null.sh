import {
    remotePushEnabled,
    remotePushOptedIn,
    setRemotePush,
    syncRemotePush,
} from '@/lib/remote-push';
import { readStorage, writeStorage } from '@/lib/storage';
import type { ConversationSummary } from '@/types';

export type RemoteAlertChannel = 'notifications' | 'sound' | 'offsite';

const listeners = new Set<() => void>();
const preferenceKey = (userId: string, channel: RemoteAlertChannel) =>
    `remote-alerts:${userId}:${channel}`;

export function remoteAlertsEnabled(
    userId: string,
    channel: RemoteAlertChannel = 'notifications',
): boolean {
    if (channel === 'offsite') return remotePushOptedIn(userId);

    return (
        (readStorage(preferenceKey(userId, channel)) ||
            readStorage(`remote-alerts:${userId}`)) !== 'off'
    );
}

export function remoteNotificationsSupported(): boolean {
    return 'Notification' in window && window.isSecureContext;
}

export function remoteNotificationsAllowed(): boolean {
    return (
        remoteNotificationsSupported() && Notification.permission === 'granted'
    );
}

export function subscribeRemoteAlerts(listener: () => void): () => void {
    let active = true;
    let permission: PermissionStatus | undefined;
    listeners.add(listener);
    window.addEventListener('storage', listener);
    window.addEventListener('focus', listener);
    document.addEventListener('visibilitychange', listener);
    void navigator.permissions
        ?.query({ name: 'notifications' })
        .then((status) => {
            if (!active) return;
            permission = status;
            status.addEventListener('change', listener);
            listener();
        })
        .catch(() => undefined);

    return () => {
        active = false;
        listeners.delete(listener);
        window.removeEventListener('storage', listener);
        window.removeEventListener('focus', listener);
        document.removeEventListener('visibilitychange', listener);
        permission?.removeEventListener('change', listener);
    };
}

export async function setRemoteAlerts(
    userId: string,
    enabled: boolean,
    channel: RemoteAlertChannel = 'notifications',
): Promise<boolean> {
    if (channel === 'offsite') {
        try {
            return await setRemotePush(userId, enabled);
        } finally {
            listeners.forEach((listener) => listener());
        }
    }

    writeStorage(preferenceKey(userId, channel), enabled ? 'on' : 'off');
    listeners.forEach((listener) => listener());

    return (
        readStorage(preferenceKey(userId, channel)) === (enabled ? 'on' : 'off')
    );
}

let pingSound: HTMLAudioElement | undefined;
let pingedAt = 0;

/**
 * MSN's ping sound. Threads showing the same ping at once, like the page and the dock, play it once.
 */
export function playPing(userId: string): void {
    if (
        !remoteAlertsEnabled(userId, 'sound') ||
        Date.now() - pingedAt < 2_000
    ) {
        return;
    }

    pingedAt = Date.now();
    pingSound ??= new Audio('/sounds/ping.mp3');
    pingSound.currentTime = 0;
    void pingSound.play().catch(() => undefined);
}

export function isRemoteMuted(until: string | null, now = Date.now()): boolean {
    return until !== null && Date.parse(until) > now;
}

/**
 * Only new arrivals, never the backlog on opening the site. Sound shares one cooldown across every remote and tab.
 * No message text leaves the page for the operating system's notification history.
 */
export function createRemoteAlerts(
    userId: string,
    urlOf: (conversation: ConversationSummary) => string,
    reading: () => string[],
    onIncoming: (conversation: ConversationSummary) => void,
) {
    void syncRemotePush(userId).catch(() => undefined);
    let latest: Record<string, number> | null = null;
    let stopped = false;
    let lastSoundAt = 0;
    const sound = new Audio('/sounds/remote.mp3');
    const notifications = new Set<Notification>();
    const cooldownKey = `remote-alert-cooldown:${userId}`;
    const readingKey = `remote-alert-reading:${userId}`;
    const tabId = crypto.randomUUID();

    const releaseReading = () => {
        if (readStorage(readingKey).split('\n')[0] === tabId) {
            writeStorage(readingKey, '');
        }
    };

    const updateReading = () => {
        const urls = reading();

        if (urls.length === 0) {
            releaseReading();
        } else {
            writeStorage(
                readingKey,
                [tabId, Date.now() + 15_000, ...urls].join('\n'),
            );
        }
    };

    updateReading();
    const readingTimer = window.setInterval(updateReading, 5_000);
    window.addEventListener('focus', updateReading);
    window.addEventListener('blur', updateReading);
    window.addEventListener('pageshow', updateReading);
    window.addEventListener('pagehide', releaseReading);
    document.addEventListener('visibilitychange', updateReading);

    const unlock = () => {
        sound.muted = true;
        void sound
            .play()
            .then(() => {
                sound.pause();
                sound.currentTime = 0;
                sound.muted = false;
                window.removeEventListener('pointerdown', unlock);
                window.removeEventListener('keydown', unlock);
            })
            .catch(() => {
                sound.muted = false;
            });
    };

    window.addEventListener('pointerdown', unlock);
    window.addEventListener('keydown', unlock);

    return {
        updateReading,
        needsBaseline: () => latest === null,
        async receive(
            conversations: ConversationSummary[],
            isSuppressed: (conversation: ConversationSummary) => boolean,
            initialBaseline: Record<string, number> = {},
        ): Promise<void> {
            const initializing = latest === null;
            const previous = latest ?? initialBaseline;
            const arrivals = conversations.filter(
                (item) =>
                    (item.last_incoming_message_id ?? 0) >
                        (previous[item.id] ?? 0) &&
                    item.ahead > 0 &&
                    !isRemoteMuted(item.muted_until_at),
            );
            latest = { ...previous };
            for (const item of conversations) {
                latest[item.id] = Math.max(
                    latest[item.id] ?? 0,
                    item.last_incoming_message_id ?? 0,
                );
            }

            if (initializing || stopped || arrivals.length === 0) {
                return;
            }

            const notify = async () => {
                if (stopped) return;

                const now = Date.now();
                const [storedAt, ...storedArrivals] =
                    readStorage(cooldownKey).split('\n');
                const consumed = new Map(
                    storedArrivals.map((entry) => {
                        const [id, message] = entry.split(':');
                        return [id, Number(message) || 0];
                    }),
                );
                const [, readingUntil, ...readingUrls] =
                    readStorage(readingKey).split('\n');
                const incoming = arrivals.find(
                    (item) =>
                        (item.last_incoming_message_id ?? 0) >
                            (consumed.get(item.id) ?? 0) &&
                        !isSuppressed(item) &&
                        !(
                            Number(readingUntil) > now &&
                            readingUrls.includes(urlOf(item))
                        ),
                );
                for (const item of arrivals) {
                    consumed.set(
                        item.id,
                        Math.max(
                            consumed.get(item.id) ?? 0,
                            item.last_incoming_message_id ?? 0,
                        ),
                    );
                }
                const consumedState = [...consumed]
                    .map(([id, message]) => `${id}:${message}`)
                    .join('\n');
                const previousSoundAt = Math.max(
                    lastSoundAt,
                    Number(storedAt) || 0,
                );

                writeStorage(
                    cooldownKey,
                    `${previousSoundAt}\n${consumedState}`,
                );
                const showBanner =
                    remoteAlertsEnabled(userId, 'offsite') &&
                    remoteNotificationsAllowed();
                const playSound = remoteAlertsEnabled(userId, 'sound');

                if (incoming === undefined) {
                    return;
                }

                if (remoteAlertsEnabled(userId)) {
                    onIncoming(incoming);
                }

                if (
                    incoming.last_message?.event === 'ping' &&
                    incoming.last_message.user_id !== userId
                ) {
                    playPing(userId);
                } else if (playSound && now - previousSoundAt >= 10_000) {
                    lastSoundAt = now;
                    writeStorage(cooldownKey, `${now}\n${consumedState}`);
                    sound.currentTime = 0;
                    void sound.play().catch(() => undefined);
                }

                if (!showBanner || remotePushEnabled()) return;

                const options: NotificationOptions = {
                    body: 'You have a new message in Remotes.',
                    tag: 'devnull-remotes',
                    silent: true,
                    data: { url: urlOf(incoming) },
                };

                try {
                    const notification = new Notification(
                        '/dev/null · Remotes',
                        options,
                    );
                    notifications.add(notification);
                    notification.onclose = () =>
                        notifications.delete(notification);
                    notification.onclick = () => {
                        notification.close();
                        if (!stopped) {
                            window.focus();
                            window.location.assign(urlOf(incoming));
                        }
                    };
                } catch {
                    // Some mobile browsers only support notifications through the installed service worker.
                    const registration =
                        await navigator.serviceWorker?.getRegistration();
                    if (!registration || stopped) return;
                    await registration.showNotification(
                        '/dev/null · Remotes',
                        options,
                    );
                }
            };

            try {
                if (navigator.locks) {
                    await navigator.locks.request(cooldownKey, notify);
                } else {
                    await notify();
                }
            } catch {
                // Permission, audio playback, or storage may be restricted; messages still arrive normally.
            }
        },
        stop() {
            stopped = true;
            sound.pause();
            notifications.forEach((notification) => notification.close());
            releaseReading();
            window.clearInterval(readingTimer);
            window.removeEventListener('focus', updateReading);
            window.removeEventListener('blur', updateReading);
            window.removeEventListener('pageshow', updateReading);
            window.removeEventListener('pagehide', releaseReading);
            document.removeEventListener('visibilitychange', updateReading);
            window.removeEventListener('pointerdown', unlock);
            window.removeEventListener('keydown', unlock);
        },
    };
}
