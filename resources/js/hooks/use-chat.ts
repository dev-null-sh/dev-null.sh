import { usePage } from '@inertiajs/react';
import { useEffect, useSyncExternalStore } from 'react';
import {
    chatSnapshot,
    subscribeToChat,
    targetKey,
    watchInbox,
    watchRemoteAlerts,
    watchThread,
} from '@/lib/chat';
import type { ChatState, ChatTarget, ThreadState } from '@/lib/chat';
import type { RemoteAlertChannel } from '@/lib/remote-notifications';
import {
    remoteAlertsEnabled,
    remoteNotificationsAllowed,
    subscribeRemoteAlerts,
} from '@/lib/remote-notifications';

export function useRemoteAlertsEnabled(
    userId: string | null,
    channel: RemoteAlertChannel = 'notifications',
): boolean {
    return useSyncExternalStore(
        subscribeRemoteAlerts,
        () => userId !== null && remoteAlertsEnabled(userId, channel),
        () => false,
    );
}

export function useRemoteNotificationPermission(): boolean {
    return useSyncExternalStore(
        subscribeRemoteAlerts,
        remoteNotificationsAllowed,
        () => false,
    );
}

export function useRemoteAlerts(userId: string | null): void {
    const notifications = useRemoteAlertsEnabled(userId);
    const sound = useRemoteAlertsEnabled(userId, 'sound');
    const enabled = notifications || sound;
    useEffect(
        () =>
            enabled && userId !== null ? watchRemoteAlerts(userId) : undefined,
        [enabled, userId],
    );
}

export function useChat(): ChatState {
    return useSyncExternalStore(subscribeToChat, chatSnapshot, chatSnapshot);
}

export function useInbox(isWatching = true): ChatState['conversations'] {
    const { conversations } = useChat();

    useEffect(() => (isWatching ? watchInbox() : undefined), [isWatching]);

    return conversations;
}

export function useThread(target: ChatTarget | null): ThreadState | null {
    const { threads } = useChat();
    const key = target === null ? null : targetKey(target);

    useEffect(() => {
        if (target === null) {
            return;
        }

        return watchThread(target);
        // The target object changes identity on every poll; only a different conversation restarts the watch.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [key]);

    return key === null ? null : (threads[key] ?? null);
}

/**
 * The chat's own count while it is polling, otherwise the count shared with every page.
 */
export function useUnreadMessages(): number {
    const { auth } = usePage().props;
    const { unread, isPollingInbox } = useChat();

    return isPollingInbox && unread !== null
        ? unread
        : auth.unreadMessagesCount;
}
