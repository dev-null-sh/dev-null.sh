import assert from 'node:assert/strict';
import * as React from 'react';
import type { ReactElement, ReactNode } from 'react';
import { afterEach, beforeEach, test, vi } from 'vite-plus/test';
import type { ConversationSummary, OrgThread, User } from '../../resources/js/types';

const cleanups: (() => void)[] = [];
vi.doMock('react', () => ({
    ...React,
    useRef: (value: unknown) => ({ current: value }),
    useState: (value: unknown) => [value, () => {}],
    useSyncExternalStore: (_subscribe: unknown, snapshot: () => unknown) => snapshot(),
    useEffect: (effect: () => (() => void) | undefined) => {
        const cleanup = effect();
        if (cleanup) cleanups.push(cleanup);
    },
}));

const { chatSnapshot, loadTab, searchConversations, seedInbox, seedThread, setConversationMute, watchThread } = await import('../../resources/js/lib/chat');
const { useRemoteAlerts } = await import('../../resources/js/hooks/use-chat');
const { ConversationActions } = await import('../../resources/js/components/chat/conversation-actions');
const { remoteNotificationsAllowed, setRemoteAlerts, subscribeRemoteAlerts } = await import('../../resources/js/lib/remote-notifications');

const user = { id: 'viewer', username: 'viewer' } as User;
const partner = { id: 'search-partner', username: 'search-partner' } as User;
const forever = '9999-12-31T00:00:00Z';
const org = { id: 'stashed-org', name: 'Stashed org' };
const target = { kind: 'org' as const, org };
const orgSummary: ConversationSummary = {
    id: org.id, kind: 'org', name: org.name, partner: null, members: [],
    last_message: null, last_message_at: null, last_incoming_message_id: null,
    last_incoming_sender: null,
    ahead: 0, partner_read_through_id: 0, partner_read_at: null, is_archived: true, pinned_at: null, muted_until_at: null,
};
const thread: OrgThread & { org: typeof org } = {
    org, messages: [], has_older: false, reactions_version: 0, reaction_changes: [],
    read_through_id: 0, members: [], typing: [], is_archived: true, pinned_at: null, muted_until_at: null,
    role: 'contributor', access: 'open', pinned: [],
};

function elements(node: ReactNode): ReactElement<Record<string, unknown>>[] {
    if (!React.isValidElement(node)) return [];
    const element = node as ReactElement<Record<string, unknown>>;
    return [element, ...React.Children.toArray(element.props.children as ReactNode).flatMap(elements)];
}

function muteItem(props: Parameters<typeof ConversationActions>[0]) {
    const item = elements(ConversationActions(props)).find((element) =>
        React.Children.toArray(element.props.children as ReactNode).some((child) => child === 'Mute' || child === 'Unmute'),
    );
    assert.ok(item);
    return { disabled: item.props.disabled, label: React.Children.toArray(item.props.children as ReactNode).at(-1) };
}

beforeEach(() => {
    vi.useFakeTimers();
    let clock = 0;
    vi.spyOn(performance, 'now').mockImplementation(() => ++clock);
    const storage = new Map<string, string>();
    const events = new EventTarget();
    vi.stubGlobal('window', {
        setTimeout, clearTimeout, setInterval, clearInterval, isSecureContext: true,
        Notification: {},
        addEventListener: events.addEventListener.bind(events),
        removeEventListener: events.removeEventListener.bind(events),
        dispatchEvent: events.dispatchEvent.bind(events),
        localStorage: { getItem: (key: string) => storage.get(key) ?? null, setItem: (key: string, value: string) => storage.set(key, value) },
    });
    vi.stubGlobal('document', { hidden: false, cookie: '', hasFocus: () => false, addEventListener() {}, removeEventListener() {} });
    vi.stubGlobal('navigator', {});
    vi.stubGlobal('Notification', { permission: 'default', requestPermission: async () => Notification.permission });
    vi.stubGlobal('Audio', class { play = async () => {}; pause() {} });
});

afterEach(() => {
    cleanups.splice(0).forEach((cleanup) => cleanup());
    vi.useRealTimers();
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
});

test.each([false, true])('dismissal releases menu presence before saving a pin change from %s', async (pinned) => {
    const events: string[] = [];
    const conversation = { ...orgSummary, id: `dismissal-${pinned}`, is_archived: false, pinned_at: pinned ? forever : null };
    seedInbox({ conversations: [conversation], has_more: false });
    const fetch = vi.fn(async (_input: string, options?: RequestInit) => {
        events.push('save');
        assert.equal(options?.method, 'PUT');
        assert.deepEqual(JSON.parse(options.body as string), { pinned: !pinned });
        return Response.json({ pinned_at: pinned ? null : forever });
    });
    vi.stubGlobal('fetch', fetch);
    const tree = elements(ConversationActions({
        target: { kind: 'org', org: { id: conversation.id, name: org.name } },
        title: org.name, user, isArchived: false, conversation,
        onPresenceChange: (present) => events.push(present ? 'present' : 'dismissed'),
    }));
    const changeOpen = tree.find((element) => element.props.modal === false)!.props.onOpenChange as (open: boolean) => void;
    const close = tree.find((element) => typeof element.props.onCloseAutoFocus === 'function')!.props.onCloseAutoFocus as () => void;
    const item = tree.find((element) => React.Children.toArray(element.props.children as ReactNode).includes(pinned ? 'Unpin' : 'Pin'))!;

    changeOpen(true);
    changeOpen(false);
    assert.deepEqual(events, ['present']);
    close();
    assert.deepEqual(events, ['present', 'dismissed']);
    assert.equal(fetch.mock.calls.length, 0);
    changeOpen(true);
    (item.props.onSelect as () => void)();
    changeOpen(false);
    assert.equal(fetch.mock.calls.length, 0);
    close();
    await vi.advanceTimersByTimeAsync(0);

    assert.deepEqual(events, ['present', 'dismissed', 'present', 'dismissed', 'save']);
    assert.equal(fetch.mock.calls.length, 1);
});

test('a search-only direct remote can be muted and unmuted without opening its thread', async () => {
    const summary = { ...orgSummary, id: 'search-only', kind: 'direct' as const, partner, is_archived: false };
    const direct = { kind: 'direct' as const, partner };
    const props = { target: direct, title: partner.username, user, isArchived: false };
    assert.equal(muteItem(props).disabled, true);
    vi.stubGlobal('fetch', async (_url: string, options?: RequestInit) => Response.json(
        options?.method === 'PUT'
            ? { muted_until_at: JSON.parse(options.body as string).duration === 'off' ? null : forever }
            : { conversations: [summary] },
    ));

    const [found] = await searchConversations('search-partner', 'open', new AbortController().signal);
    assert.equal(chatSnapshot().threads[partner.username], undefined);
    assert.deepEqual(muteItem({ ...props, conversation: found }), { disabled: false, label: 'Mute' });
    await setConversationMute(direct, 'forever');
    assert.deepEqual(muteItem({ ...props, conversation: found }), { disabled: false, label: 'Unmute' });
    await setConversationMute(direct, 'off');
    assert.deepEqual(muteItem({ ...props, conversation: found }), { disabled: false, label: 'Mute' });
});

test('fresh thread mute changes beat a stashed summary and older in-flight inbox reads, but not newer reads', async () => {
    const props = { target, title: org.name!, user, isArchived: true };
    vi.stubGlobal('fetch', async () => Response.json({ conversations: [orgSummary], has_more: false, unread: 0, requests: 0 }));
    await loadTab('stashed');
    seedThread(target, thread);
    let resolveInbox!: (response: Response) => void;
    let muted: string | null = forever;
    vi.stubGlobal('fetch', async (url: string) => url.includes('/inbox')
        ? new Promise<Response>((resolve) => { resolveInbox = resolve; })
        : Response.json({ ...thread, muted_until_at: muted }));
    const staleInbox = loadTab('stashed');
    const stop = watchThread(target);
    cleanups.push(stop);
    await vi.advanceTimersByTimeAsync(0);
    assert.equal(muteItem(props).label, 'Unmute');
    resolveInbox(Response.json({ conversations: [orgSummary], has_more: false, unread: 0, requests: 0 }));
    await staleInbox;
    assert.equal(muteItem(props).label, 'Unmute');

    muted = null;
    await vi.advanceTimersByTimeAsync(4000);
    assert.equal(muteItem(props).label, 'Mute');
    vi.stubGlobal('fetch', async () => Response.json({ conversations: [{ ...orgSummary, muted_until_at: forever }], has_more: false, unread: 0, requests: 0 }));
    await loadTab('stashed');
    assert.equal(muteItem(props).label, 'Unmute');
});

test.each(['default', 'denied', 'unsupported', 'granted'] as const)('tab-title notifications keep polling independently of banner permission: %s', async (permission) => {
    if (permission === 'unsupported') Reflect.deleteProperty(window, 'Notification');
    else vi.stubGlobal('Notification', { permission });
    await setRemoteAlerts('viewer', false, 'sound');
    const fetch = vi.fn(async () => Response.json({ conversations: [], has_more: false, unread: 0, requests: 0 }));
    vi.stubGlobal('fetch', fetch);

    useRemoteAlerts('viewer');
    await vi.advanceTimersByTimeAsync(15_000);
    assert.equal(fetch.mock.calls.length, 2);
});

test('sound-only polling works without notification permission', async () => {
    await setRemoteAlerts('viewer', false);
    const fetch = vi.fn(async () => Response.json({ conversations: [], has_more: false, unread: 0, requests: 0 }));
    vi.stubGlobal('fetch', fetch);
    useRemoteAlerts('viewer');
    await vi.advanceTimersByTimeAsync(15_000);
    assert.equal(fetch.mock.calls.length, 2);
});

test('permission changes and returning to the tab refresh permission subscribers without a reload', async () => {
    const status = new EventTarget();
    vi.stubGlobal('navigator', { permissions: { query: async () => status } });
    const permissions: boolean[] = [];
    const unsubscribe = subscribeRemoteAlerts(() => permissions.push(remoteNotificationsAllowed()));
    await Promise.resolve();
    vi.stubGlobal('Notification', { permission: 'granted' });
    status.dispatchEvent(new Event('change'));
    vi.stubGlobal('Notification', { permission: 'denied' });
    window.dispatchEvent(new Event('focus'));
    unsubscribe();
    status.dispatchEvent(new Event('change'));
    assert.deepEqual(permissions, [false, true, false]);
});
