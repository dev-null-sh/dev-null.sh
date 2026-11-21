import assert from 'node:assert/strict';
import type { Page, SharedPageProps } from '@inertiajs/core';
import { afterEach, test, vi } from 'vite-plus/test';
import { chatSnapshot, watchInbox } from '../../resources/js/lib/chat';
import { pageTitle } from '../../resources/js/lib/page-title';

afterEach(() => {
    vi.useRealTimers();
    vi.unstubAllGlobals();
});

test('a hidden tab counts messages from the shared props once the chat stops polling', async () => {
    vi.useFakeTimers();
    vi.stubGlobal('window', { setTimeout, clearTimeout });
    vi.stubGlobal('document', { hidden: false, cookie: '', addEventListener() {}, removeEventListener() {} });
    vi.stubGlobal('fetch', async () => Response.json({ conversations: [], has_more: false, unread: 2, requests: 0 }));
    const page = { props: { auth: { user: { id: 'me' }, unreadNotificationsCount: 0, unreadMessagesCount: 5 } } } as unknown as Page<SharedPageProps>;
    const stop = watchInbox();

    try {
        await vi.advanceTimersByTimeAsync(0);
        assert.equal(pageTitle('Log', page), '(2) Log - /dev/null');

        Object.assign(document, { hidden: true });
        await vi.advanceTimersByTimeAsync(15_000);
        assert.equal(chatSnapshot().unread, null);
        assert.equal(pageTitle('Log', page), '(5) Log - /dev/null');
    } finally {
        stop();
    }
});
