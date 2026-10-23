import assert from 'node:assert/strict';
import { afterEach, beforeEach, test, vi } from 'vite-plus/test';
import {
    chatSnapshot,
    loadMoreConversations,
    loadTab,
    seedInbox,
    seedThread,
    setConversationPinned,
    targetOf,
    watchInbox,
} from '../../resources/js/lib/chat';
import type { ConversationSummary, Thread } from '../../resources/js/types';

const at = '2026-10-23T12:00:00Z';
const summary = (
    id: string,
    minute: string,
    pinned_at: string | null = null,
): ConversationSummary => ({
    id,
    kind: 'org',
    name: id,
    partner: null,
    members: [],
    last_message: null,
    last_message_at: `2026-10-23T11:${minute}:00Z`,
    last_incoming_message_id: null,
    last_incoming_sender: null,
    ahead: 0,
    partner_read_through_id: 0,
    partner_read_at: null,
    is_archived: false,
    pinned_at,
    muted_until_at: null,
});
const older = summary('older', '01');
const recent = summary('recent', '30');

beforeEach(() => {
    vi.useFakeTimers();
    vi.stubGlobal('document', {
        cookie: '',
        hidden: false,
        hasFocus: () => false,
        addEventListener: () => {},
        removeEventListener: () => {},
    });
    vi.stubGlobal('window', { setTimeout, clearTimeout });
    seedInbox({ conversations: [recent, older], has_more: false });
});
afterEach(() => {
    vi.useRealTimers();
    vi.unstubAllGlobals();
});

test('pins and unpins only after persistence succeeds, updating the inbox order and thread', async () => {
    const target = targetOf(older);
    seedThread(target, {
        messages: [],
        has_older: false,
        reactions_version: 0,
        reaction_changes: [],
        read_through_id: 0,
        partner_read_through_id: 0,
        partner_read_at: null,
        partner_typing: false,
        is_archived: false,
        pinned_at: null,
        muted_until_at: null,
        access: 'open',
    } as Thread);
    let finish!: (response: Response) => void;
    const fetch = vi.fn(
        (_url: RequestInfo | URL, _init?: RequestInit) =>
            new Promise<Response>((resolve) => {
                finish = resolve;
            }),
    );
    vi.stubGlobal('fetch', fetch);
    const saving = setConversationPinned(target, true);
    assert.deepEqual(
        chatSnapshot().conversations?.map(({ id }) => id),
        ['recent', 'older'],
    );
    finish(Response.json({ pinned_at: at }));
    await saving;
    assert.deepEqual(
        chatSnapshot().conversations?.map(({ id }) => id),
        ['older', 'recent'],
    );
    assert.equal(chatSnapshot().threads['org:older'].pinned_at, at);
    assert.equal(fetch.mock.calls[0]?.[1]?.method, 'PUT');
    assert.equal(
        fetch.mock.calls[0]?.[1]?.body,
        JSON.stringify({ pinned: true }),
    );
    const removing = setConversationPinned(target, false);
    finish(Response.json({ pinned_at: null }));
    await removing;
    assert.deepEqual(
        chatSnapshot().conversations?.map(({ id }) => id),
        ['recent', 'older'],
    );
});

test('failed persistence keeps the previous pin state and list order', async () => {
    vi.stubGlobal('fetch', async () => Response.json({}, { status: 500 }));
    await assert.rejects(setConversationPinned(targetOf(older), true));
    assert.deepEqual(
        chatSnapshot().conversations?.map(({ id }) => id),
        ['recent', 'older'],
    );
    assert.equal(chatSnapshot().conversations?.[1].pinned_at, null);
});

test('an inbox response started before a save cannot undo that pin', async () => {
    let finish!: (response: Response) => void;
    vi.stubGlobal(
        'fetch',
        () =>
            new Promise<Response>((resolve) => {
                finish = resolve;
            }),
    );
    const loading = loadTab('open');
    vi.stubGlobal('fetch', async () => Response.json({ pinned_at: at }));
    await setConversationPinned(targetOf(older), true);
    finish(Response.json({ conversations: [recent, older], has_more: false }));
    await loading;
    assert.equal(chatSnapshot().conversations?.[0].id, 'older');
    assert.equal(chatSnapshot().conversations?.[0].pinned_at, at);
});

test('retains older loaded pages when a pinned first page has an older message date', async () => {
    const pinned = summary('pinned', '01', at);
    const middle = summary('middle', '10');
    seedInbox({ conversations: [pinned], has_more: true });
    vi.stubGlobal('fetch', async () =>
        Response.json({ conversations: [recent, middle], has_more: false }),
    );
    await loadMoreConversations('open');
    vi.stubGlobal('fetch', async () =>
        Response.json({ conversations: [pinned], has_more: true }),
    );
    const stop = watchInbox();
    await vi.advanceTimersByTimeAsync(0);
    stop();
    assert.deepEqual(
        chatSnapshot().conversations?.map(({ id }) => id),
        ['pinned', 'recent', 'middle'],
    );
});
