/// <reference types="node" />
import assert from 'node:assert/strict';
import { act, useState } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, test, vi } from 'vite-plus/test';
import { connectRealtime } from '../../resources/js/lib/realtime';
import type { Commit, ThreadContinuation } from '../../resources/js/types';
import { fakeConnection } from './FakeRealtime';

vi.stubGlobal('window', globalThis);
vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT', true);
vi.doMock('@inertiajs/react', () => ({
    router: { on: () => () => {} },
    usePage: () => ({ props: { now: Date.now() } }),
    useRemember: (initial: unknown) => useState(initial),
}));
vi.doMock('sonner', () => ({ toast: { error: () => {} } }));

const { useContinuation } =
    await import('../../resources/js/hooks/use-continuation');

type Request = {
    from: string;
    signal: AbortSignal | undefined;
    respond: (parts: string[], nextId: string | null) => Promise<void>;
};

let requests: Request[] = [];
let fake: ReturnType<typeof fakeConnection>;
let thread: ReturnType<typeof useContinuation>;

const part = (id: string, parent: string) =>
    ({ id, parent_id: parent }) as unknown as Commit;

/**
 * A chain of parts after `from`, each continuing the one before.
 */
function chain(from: string, ids: string[]): Commit[] {
    return ids.map((id, index) =>
        part(id, index === 0 ? from : ids[index - 1]),
    );
}

const ids = (from: number, to: number, prefix = 'p') =>
    Array.from({ length: to - from }, (_, index) => `${prefix}${from + index}`);

function container(): Element {
    const node = {
        nodeType: 1,
        nodeName: 'DIV',
        tagName: 'DIV',
        addEventListener: () => {},
        removeEventListener: () => {},
    };

    return {
        ...node,
        ownerDocument: {
            ...node,
            defaultView: {
                document: { activeElement: null },
                HTMLIFrameElement: class {},
            },
        },
    } as unknown as Element;
}

function Probe({ continuation }: { continuation: ThreadContinuation }) {
    thread = useContinuation(continuation);

    return null;
}

const shownIds = () => thread.shown.map((shown) => shown.id);

async function settle(ms = 400) {
    await act(async () => {
        await vi.advanceTimersByTimeAsync(ms);
    });
}

function changed(id: string) {
    act(() =>
        fake.joined.get(`commits.${id}`)?.emit('visibility', { ids: [id] }),
    );
}

beforeEach(async () => {
    vi.useFakeTimers();
    requests = [];
    fake = fakeConnection();
    connectRealtime(fake.connection);
    vi.stubGlobal('document', { querySelector: () => null });
    vi.stubGlobal('fetch', (url: string, init?: RequestInit) => {
        const from = url.match(/commits\/([^/]+)\/continuation/)?.[1] ?? '';

        return new Promise<Response>((resolve, reject) => {
            init?.signal?.addEventListener('abort', () =>
                reject(new DOMException('Aborted', 'AbortError')),
            );
            requests.push({
                from,
                signal: init?.signal ?? undefined,
                respond: async (parts, nextId) => {
                    resolve(
                        Response.json({
                            data: chain(from, parts),
                            next_id: nextId,
                            position: 0,
                            length: 40,
                            is_capped: false,
                        }),
                    );
                    await settle(0);
                },
            });
        });
    });

    act(() =>
        createRoot(container()).render(
            <Probe
                continuation={{
                    data: chain('root', ids(0, 10)),
                    next_id: 'p10',
                    position: 1,
                    length: 40,
                    is_capped: false,
                }}
            />,
        ),
    );
});

afterEach(() => {
    connectRealtime(null);
    vi.unstubAllGlobals();
    vi.stubGlobal('window', globalThis);
    vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT', true);
    vi.useRealTimers();
});

test('loading the next page while a change is being read keeps that page, and reads the change again over it', async () => {
    changed('p0');
    await settle();
    const held = requests.at(-1);
    assert.equal(held?.from, 'root');

    act(() => thread.loadMore());
    const more = requests.at(-1);
    assert.equal(more?.from, 'p9');
    await more!.respond(ids(10, 20), 'p20');
    assert.deepEqual(shownIds(), ids(0, 20));

    await held!.respond(ids(1, 11, 'n'), 'n11');
    assert.deepEqual(shownIds(), ids(0, 20));

    await settle();
    const again = requests.at(-1);
    assert.notEqual(again, held);
    assert.equal(again?.from, 'root');
    await again!.respond(ids(1, 21, 'n'), 'n21');
    assert.deepEqual(shownIds(), ids(1, 21, 'n'));
});

test('a change heard while the next page loads is read once it has', async () => {
    act(() => thread.loadMore());
    const more = requests.at(-1)!;
    changed('p5');
    await settle();
    assert.equal(requests.length, 1);

    await more.respond(ids(10, 20), null);
    await settle();
    const reconcile = requests.at(-1)!;
    assert.equal(reconcile.from, 'p4');
    await reconcile.respond(ids(6, 20), null);
    assert.deepEqual(shownIds(), ids(0, 5).concat(ids(6, 20)));
});

test('changes to different parts are read one after the other, from the earliest, never at once', async () => {
    changed('p7');
    await settle();
    const later = requests.at(-1)!;
    assert.equal(later.from, 'p6');

    changed('p2');
    await settle();
    assert.equal(requests.length, 1);

    await later.respond(['p8', 'p9', 'p10'], 'p11');
    assert.deepEqual(shownIds(), [...ids(0, 7), 'p8', 'p9', 'p10']);
    await settle();
    const earlier = requests.at(-1)!;
    assert.equal(earlier.from, 'p1');
    await earlier.respond(
        ['p3', 'p4', 'p5', 'p6', 'p8', 'p9', 'p10', 'p11'],
        'p12',
    );
    assert.deepEqual(shownIds(), [
        'p0',
        'p1',
        'p3',
        'p4',
        'p5',
        'p6',
        'p8',
        'p9',
        'p10',
        'p11',
    ]);
});
