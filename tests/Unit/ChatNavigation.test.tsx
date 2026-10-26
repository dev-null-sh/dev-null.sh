import assert from 'node:assert/strict';
import { afterEach, beforeEach, test, vi } from 'vite-plus/test';
import { chatSnapshot, loadMessage, loadOlderMessages, seedThread, targetKey } from '../../resources/js/lib/chat';
import type { ChatTarget } from '../../resources/js/lib/chat';
import type { Message, Thread, User } from '../../resources/js/types';

let sequence = 0;
const message = (id: number) => ({ id, user_id: 'partner', client_id: `message-${id}` }) as Message;
const page = (ids: number[], hasOlder = true): Thread => ({
    messages: ids.map(message), has_older: hasOlder, reactions_version: 0, reaction_changes: [],
    read_through_id: 0, partner_read_through_id: 0, partner_read_at: null,
    partner_typing: false, is_archived: false, pinned_at: null, muted_until_at: null, access: 'open',
});
function thread(kind: 'direct' | 'org' = 'direct'): ChatTarget {
    const id = `navigation-${++sequence}`;
    const target: ChatTarget = kind === 'direct'
        ? { kind, partner: { id, username: id } as User }
        : { kind, org: { id, name: 'Navigation' } };
    seedThread(target, kind === 'direct' ? page([101, 102]) : {
        ...page([101, 102]), members: [], typing: [], role: 'contributor', pinned: [],
    });
    return target;
}

beforeEach(() => vi.stubGlobal('document', { cookie: '' }));
afterEach(() => vi.unstubAllGlobals());

test.each(['direct', 'org'] as const)('loads an old reply through multiple pages without gaps in a %s chat', async (kind) => {
    const target = thread(kind);
    const before: string[] = [];
    vi.stubGlobal('fetch', async (url: string) => {
        const cursor = new URL(url, 'http://localhost').searchParams.get('before')!;
        before.push(cursor);
        return Response.json(cursor === '101' ? page([51, 52]) : page([1, 2], false));
    });

    assert.equal(await loadMessage(target, 2, new AbortController().signal), true);
    assert.deepEqual(before, ['101', '51']);
    assert.deepEqual(chatSnapshot().threads[targetKey(target)].messages.map(({ id }) => id), [1, 2, 51, 52, 101, 102]);
});

test('an already loaded original needs no request', async () => {
    const target = thread();
    const fetch = vi.fn();
    vi.stubGlobal('fetch', fetch);

    assert.equal(await loadMessage(target, 101, new AbortController().signal), true);
    assert.equal(fetch.mock.calls.length, 0);
});

test('joins history already loading instead of failing or requesting the same page twice', async () => {
    const target = thread();
    let finish!: (response: Response) => void;
    const fetch = vi.fn(() => new Promise<Response>((resolve) => { finish = resolve; }));
    vi.stubGlobal('fetch', fetch);
    const scrolling = loadOlderMessages(target);
    const jumping = loadMessage(target, 51, new AbortController().signal);
    finish(Response.json(page([51, 52])));

    await scrolling;
    assert.equal(await jumping, true);
    assert.equal(fetch.mock.calls.length, 1);
});

test('stops at unavailable history without looping', async () => {
    const target = thread();
    const fetch = vi.fn(async () => Response.json(page([50, 51], false)));
    vi.stubGlobal('fetch', fetch);

    assert.equal(await loadMessage(target, 2, new AbortController().signal), false);
    assert.equal(fetch.mock.calls.length, 1);
});

test('a failed page stops the jump and remains retryable', async () => {
    const target = thread();
    vi.stubGlobal('fetch', async () => Response.json({}, { status: 500 }));
    assert.equal(await loadMessage(target, 51, new AbortController().signal), false);
    assert.equal(chatSnapshot().threads[targetKey(target)].isLoadingOlder, false);
    vi.stubGlobal('fetch', async () => Response.json(page([51, 52])));

    assert.equal(await loadMessage(target, 51, new AbortController().signal), true);
});

test('returning to present stops further pages after an in-flight request', async () => {
    const target = thread();
    const controller = new AbortController();
    let finish!: (response: Response) => void;
    const fetch = vi.fn(() => new Promise<Response>((resolve) => { finish = resolve; }));
    vi.stubGlobal('fetch', fetch);
    const jumping = loadMessage(target, 2, controller.signal);
    controller.abort();
    finish(Response.json(page([51, 52])));

    assert.equal(await jumping, false);
    assert.equal(fetch.mock.calls.length, 1);
});

test('a non-advancing page cannot create a request loop', async () => {
    const target = thread();
    const fetch = vi.fn(async () => Response.json(page([101, 102])));
    vi.stubGlobal('fetch', fetch);

    assert.equal(await loadMessage(target, 2, new AbortController().signal), false);
    assert.equal(fetch.mock.calls.length, 1);
});

test('messages arriving during a history request are retained', async () => {
    const target = thread();
    let finish!: (response: Response) => void;
    vi.stubGlobal('fetch', () => new Promise<Response>((resolve) => { finish = resolve; }));
    const jumping = loadMessage(target, 51, new AbortController().signal);
    seedThread(target, page([101, 102, 103]));
    finish(Response.json(page([51, 52])));

    assert.equal(await jumping, true);
    assert.deepEqual(chatSnapshot().threads[targetKey(target)].messages.map(({ id }) => id), [51, 52, 101, 102, 103]);
});

test('a missing message inside loaded history does not fetch unrelated older pages', async () => {
    const target = thread();
    seedThread(target, page([99, 101, 102]));
    const fetch = vi.fn();
    vi.stubGlobal('fetch', fetch);

    assert.equal(await loadMessage(target, 100, new AbortController().signal), false);
    assert.equal(fetch.mock.calls.length, 0);
});
