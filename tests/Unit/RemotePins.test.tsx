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

function orgTarget(summary: ConversationSummary) {
    const target = targetOf(summary);
    assert.ok(target);
    return target;
}

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
    const target = orgTarget(older);
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
    await assert.rejects(setConversationPinned(orgTarget(older), true));
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
    await setConversationPinned(orgTarget(older), true);
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

function pagedInbox(prefix: string) {
    const pins = Array.from({ length: 30 }, (_, index) => ({
        ...summary(`${prefix}-pin-${index}`, '00', at),
        last_message_at: `2026-10-01T00:${String(59 - index).padStart(2, '0')}:00Z`,
    }));
    const newer = Array.from({ length: 35 }, (_, index) => ({
        ...summary(`${prefix}-new-${index}`, '00'),
        last_message_at: `2026-10-23T00:${String(59 - index).padStart(2, '0')}:00Z`,
    }));
    const moved = pins.at(-1)!;
    const original = [...pins, ...newer];
    let rows = original;
    const unpin = () => {
        rows = [...pins.slice(0, -1), ...newer, { ...moved, pinned_at: null }];
    };
    const fetch = async (input: string, init?: RequestInit) => {
        if (init?.method === 'PUT') {
            rows = [
                ...pins.slice(0, -1),
                ...newer,
                { ...moved, pinned_at: null },
            ];
            return Response.json({ pinned_at: null });
        }
        const before = new URL(input, 'http://localhost').searchParams.get(
            'before',
        );
        const start =
            before === null ? 0 : rows.findIndex(({ id }) => id === before) + 1;
        return Response.json({
            conversations: rows.slice(start, start + 30),
            pins: Object.fromEntries(
                rows
                    .filter((item) => item.pinned_at !== null)
                    .map((item) => [item.id, item.pinned_at]),
            ),
            has_more: start + 30 < rows.length,
        });
    };
    return { fetch, moved, original, rows: () => rows, unpin };
}

test.each(['open', 'fetched', 'stashed'] as const)(
    'unpinning backfills the loaded %s prefix before continuing pagination',
    async (tab) => {
        const inbox = pagedInbox(`gap-${tab}`);
        vi.stubGlobal('fetch', inbox.fetch);
        await loadTab(tab);

        await setConversationPinned(orgTarget(inbox.moved), false);
        await loadMoreConversations(tab);
        await loadMoreConversations(tab);

        const list = {
            open: chatSnapshot().conversations,
            fetched: chatSnapshot().fetched,
            stashed: chatSnapshot().archived,
        }[tab];
        assert.deepEqual(
            list?.map(({ id }) => id),
            inbox.rows().map(({ id }) => id),
        );
        assert.equal(chatSnapshot().hasMore[tab], false);
    },
);

test('pin reconciliation preserves the number of pages already loaded', async () => {
    const inbox = pagedInbox('loaded-depth');
    vi.stubGlobal('fetch', inbox.fetch);
    await loadTab('open');
    await loadMoreConversations('open');

    await setConversationPinned(orgTarget(inbox.moved), false);

    assert.equal(chatSnapshot().conversations?.length, 60);
    assert.deepEqual(
        chatSnapshot().conversations?.map(({ id }) => id),
        inbox
            .rows()
            .slice(0, 60)
            .map(({ id }) => id),
    );
    assert.equal(chatSnapshot().hasMore.open, true);
    await loadMoreConversations('open');
    assert.deepEqual(
        chatSnapshot().conversations?.map(({ id }) => id),
        inbox.rows().map(({ id }) => id),
    );
});

test('scrolling retries a failed reconciliation without losing the saved pin or skipping the gap', async () => {
    const inbox = pagedInbox('retry-prefix');
    let fail = false;
    vi.stubGlobal('fetch', (url: string, init?: RequestInit) => {
        if (fail && init?.method !== 'PUT') {
            fail = false;
            return Promise.reject(new Error('offline'));
        }
        return inbox.fetch(url, init);
    });
    await loadTab('open');
    fail = true;

    await setConversationPinned(orgTarget(inbox.moved), false);
    assert.equal(
        chatSnapshot().pinPreferences[`org:${inbox.moved.id}`].at,
        null,
    );
    await loadMoreConversations('open');
    await loadMoreConversations('open');

    assert.deepEqual(
        chatSnapshot().conversations?.map(({ id }) => id),
        inbox.rows().map(({ id }) => id),
    );
    assert.equal(chatSnapshot().hasMore.open, false);
});

test('a page requested before unpinning cannot restore the obsolete continuation state', async () => {
    const inbox = pagedInbox('late-page');
    let hold = true;
    let finish!: (response: Response) => void;
    vi.stubGlobal('fetch', (url: string, init?: RequestInit) => {
        if (url.includes('before=') && hold) {
            hold = false;
            return new Promise<Response>((resolve) => {
                finish = resolve;
            });
        }
        return inbox.fetch(url, init);
    });
    await loadTab('open');
    const loading = loadMoreConversations('open');

    await setConversationPinned(orgTarget(inbox.moved), false);
    finish(
        Response.json({
            conversations: inbox.original.slice(30, 60),
            has_more: true,
        }),
    );
    await loading;

    assert.deepEqual(
        chatSnapshot().conversations?.map(({ id }) => id),
        inbox
            .rows()
            .slice(0, 30)
            .map(({ id }) => id),
    );
    assert.equal(chatSnapshot().isLoadingMore.open, false);
    await loadMoreConversations('open');
    await loadMoreConversations('open');
    assert.deepEqual(
        chatSnapshot().conversations?.map(({ id }) => id),
        inbox.rows().map(({ id }) => id),
    );
});

test.each(['poll', 'scroll'] as const)(
    'a pin removed on another device reconciles the prefix through %s, even when it falls outside the page',
    async (source) => {
        const inbox = pagedInbox(`other-device-${source}`);
        vi.stubGlobal('fetch', inbox.fetch);
        await loadTab('open');
        inbox.unpin();

        if (source === 'poll') {
            const stop = watchInbox();
            await vi.advanceTimersByTimeAsync(0);
            stop();
        }
        await loadMoreConversations('open');
        await loadMoreConversations('open');

        assert.equal(
            chatSnapshot().pinPreferences[`org:${inbox.moved.id}`].at,
            null,
        );
        assert.deepEqual(
            chatSnapshot().conversations?.map(({ id }) => id),
            inbox.rows().map(({ id }) => id),
        );
        assert.equal(chatSnapshot().hasMore.open, false);
    },
);

test.each(['pin', 'unpin'] as const)(
    'restarts reconciliation when another device changes a %s between pages',
    async (change) => {
        const inbox = pagedInbox(`mid-refresh-${change}`);
        let rows = inbox.original;
        let reconciling = false;
        vi.stubGlobal('fetch', async (input: string, init?: RequestInit) => {
            if (init?.method === 'PUT') {
                rows = [
                    ...rows.slice(1, 30),
                    ...rows.slice(30),
                    { ...rows[0], pinned_at: null },
                ];
                reconciling = true;
                return Response.json({ pinned_at: null });
            }
            const before = new URL(input, 'http://localhost').searchParams.get(
                'before',
            );
            if (reconciling && before !== null) {
                reconciling = false;
                rows =
                    change === 'unpin'
                        ? [
                              ...rows.slice(0, 28),
                              ...rows.slice(29),
                              { ...inbox.moved, pinned_at: null },
                          ]
                        : [
                              { ...rows[63], pinned_at: at },
                              ...rows.slice(0, 63),
                              ...rows.slice(64),
                          ];
            }
            const start =
                before === null
                    ? 0
                    : rows.findIndex(({ id }) => id === before) + 1;
            return Response.json({
                conversations: rows.slice(start, start + 30),
                has_more: start + 30 < rows.length,
                pins: Object.fromEntries(
                    rows
                        .filter((item) => item.pinned_at !== null)
                        .map((item) => [item.id, item.pinned_at]),
                ),
            });
        });
        await loadTab('open');
        await loadMoreConversations('open');

        await setConversationPinned(orgTarget(inbox.original[0]), false);

        assert.equal(chatSnapshot().conversations?.length, 60);
        assert.deepEqual(
            chatSnapshot().conversations?.map(({ id }) => id),
            rows.slice(0, 60).map(({ id }) => id),
        );
        await loadMoreConversations('open');
        assert.deepEqual(
            chatSnapshot().conversations?.map(({ id }) => id),
            rows.map(({ id }) => id),
        );
        assert.equal(chatSnapshot().hasMore.open, false);
    },
);
