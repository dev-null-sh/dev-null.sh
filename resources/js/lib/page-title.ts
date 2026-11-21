import type { Page } from '@inertiajs/core';
import { chatSnapshot } from '@/lib/chat';
import type { Auth } from '@/types';

const appName = import.meta.env.VITE_APP_NAME || '/dev/null';

export function pageTitle(title: string, page: Page): string {
    const auth = page.props.auth as Auth;
    const { unread, isPollingInbox } = chatSnapshot();
    const messages =
        isPollingInbox && unread !== null ? unread : auth.unreadMessagesCount;
    const count =
        auth.user === null ? 0 : auth.unreadNotificationsCount + messages;
    const name = title ? `${title} - ${appName}` : appName;

    return count > 0 ? `(${count > 99 ? '99+' : count}) ${name}` : name;
}
