import assert from 'node:assert/strict';
import * as React from 'react';
import { renderToString } from 'react-dom/server';
import { afterEach, test, vi } from 'vite-plus/test';
import type { ThreadCommit } from '../../resources/js/types';

const effects: (() => void | VoidFunction)[] = [];
type Saved = {
    replies: ThreadCommit[] | null;
    nextCursor: string | null;
    isOpen: boolean;
};
let saved: Saved;
vi.doMock('react', () => ({
    ...React,
    useSyncExternalStore: (_subscribe: unknown, read: () => unknown) => read(),
    useEffectEvent: (event: unknown) => event,
    useEffect: (effect: () => void | VoidFunction) => {
        effects.push(effect);
    },
}));
vi.doMock('@inertiajs/react', async () => ({
    ...(await vi.importActual('@inertiajs/react')),
    usePage: () => ({ props: { repliesPerPage: 3, auth: { user: null } } }),
    router: { restore: () => saved, on: () => () => {} },
    useRemember: () => [
        saved,
        (update: Saved | ((state: Saved) => Saved)) => {
            saved = typeof update === 'function' ? update(saved) : update;
        },
    ],
}));
const { useThread } =
    await import('../../resources/js/components/commit-thread');
const reply = (id: string) =>
    ({ id, replies_count: 0, deleted_at: null }) as ThreadCommit;
function mount(id: string) {
    let thread: ReturnType<typeof useThread> | undefined;
    function Probe() {
        thread = useThread(
            { ...reply(id), replies_count: 5 },
            { data: [reply('one')], next_cursor: 'next', prev_cursor: null },
        );
        return null;
    }
    renderToString(<Probe />);
    assert.ok(thread);
    return thread;
}
afterEach(() => {
    effects.length = 0;
    vi.unstubAllGlobals();
});
for (const isOpen of [true, false]) {
    test(`revalidates an older ${isOpen ? 'expanded' : 'collapsed'} history entry after deletion without resetting its loaded depth`, async () => {
        const original = [
            reply('one'),
            reply('deleted'),
            reply('three'),
            reply('four'),
            reply('five'),
        ];
        saved = { replies: original, nextCursor: null, isOpen };
        const remaining = original.filter((row) => row.id !== 'deleted');
        const fetch = vi.fn().mockImplementation(async (url: string) => {
            const isLastPage = new URL(
                url,
                'http://localhost',
            ).searchParams.has('cursor');
            return {
                ok: true,
                json: async () => ({
                    data: isLastPage
                        ? remaining.slice(3)
                        : remaining.slice(0, 3),
                    next_cursor: isLastPage ? null : 'last-page',
                    prev_cursor: null,
                }),
            };
        });
        vi.stubGlobal('fetch', fetch);
        const thread = mount(`mutation-${isOpen}`);
        effects.length = 0;
        thread.onDeleted();
        await vi.waitFor(() => assert.equal(saved.replies?.length, 4));

        // The earlier history entry still has the pre-deletion snapshot.
        saved = { replies: original, nextCursor: null, isOpen };
        fetch.mockClear();
        mount(`mutation-${isOpen}`);
        for (const effect of effects.splice(0)) effect();
        await vi.waitFor(() => assert.equal(fetch.mock.calls.length, 2));
        await vi.waitFor(() => assert.equal(saved.replies?.length, 4));
        const restored = mount(`mutation-${isOpen}`);
        assert.deepEqual(
            restored.replies?.map((row) => row.id),
            ['one', 'three', 'four', 'five'],
        );
        assert.equal(restored.isOpen, isOpen);
        assert.equal(restored.hasMore, false);
    });
}

test('revalidates restored document history even when both cached props and replies have the old count', async () => {
    const original = [
        reply('one'),
        reply('deleted'),
        reply('three'),
        reply('four'),
        reply('five'),
    ];
    const remaining = original.filter((row) => row.id !== 'deleted');
    saved = { replies: original, nextCursor: null, isOpen: true };
    vi.stubGlobal('performance', {
        getEntriesByType: () => [{ type: 'back_forward' }],
    });
    const fetch = vi.fn().mockImplementation(async (url: string) => {
        const isLastPage = new URL(url, 'http://localhost').searchParams.has(
            'cursor',
        );
        return {
            ok: true,
            json: async () => ({
                data: isLastPage ? remaining.slice(3) : remaining.slice(0, 3),
                next_cursor: isLastPage ? null : 'last-page',
                prev_cursor: null,
            }),
        };
    });
    vi.stubGlobal('fetch', fetch);
    mount('restored-document');
    for (const effect of effects.splice(0)) effect();
    await vi.waitFor(() => assert.equal(fetch.mock.calls.length, 2));
    await vi.waitFor(() => assert.equal(saved.replies?.length, 4));
    const restored = mount('restored-document');
    assert.deepEqual(
        restored.replies?.map((row) => row.id),
        ['one', 'three', 'four', 'five'],
    );
    assert.equal(restored.isOpen, true);
    assert.equal(restored.count, 4);
});

test('waits for history revalidation before loading another reply page', async () => {
    saved = {
        replies: [reply('one'), reply('two'), reply('three')],
        nextCursor: 'next',
        isOpen: true,
    };
    vi.stubGlobal('performance', {
        getEntriesByType: () => [{ type: 'back_forward' }],
    });
    let finish!: (value: unknown) => void;
    const fetch = vi.fn(
        () =>
            new Promise((resolve) => {
                finish = resolve;
            }),
    );
    vi.stubGlobal('fetch', fetch);
    const thread = mount('pagination-during-refresh');
    for (const effect of effects.splice(0)) effect();
    assert.equal(thread.isLoading, true);
    thread.loadMore();
    thread.loadAll();
    assert.equal(fetch.mock.calls.length, 1);
    finish({
        ok: true,
        json: async () => ({
            data: [reply('fresh')],
            next_cursor: null,
            prev_cursor: null,
        }),
    });
    await vi.waitFor(() =>
        assert.deepEqual(
            saved.replies?.map((row) => row.id),
            ['fresh'],
        ),
    );
});

test('does not let a delayed history refresh overwrite a newer deletion refresh', async () => {
    saved = {
        replies: [reply('one'), reply('deleted')],
        nextCursor: null,
        isOpen: true,
    };
    vi.stubGlobal('performance', {
        getEntriesByType: () => [{ type: 'back_forward' }],
    });
    let finish!: (value: unknown) => void;
    const fetch = vi
        .fn()
        .mockImplementationOnce(
            () =>
                new Promise((resolve) => {
                    finish = resolve;
                }),
        )
        .mockResolvedValue({
            ok: true,
            json: async () => ({
                data: [reply('one')],
                next_cursor: null,
                prev_cursor: null,
            }),
        });
    vi.stubGlobal('fetch', fetch);
    const thread = mount('mutation-during-refresh');
    for (const effect of effects.splice(0)) effect();
    thread.onDeleted();
    await vi.waitFor(() => assert.equal(saved.replies?.length, 1));
    finish({
        ok: true,
        json: async () => ({
            data: [reply('one'), reply('deleted')],
            next_cursor: null,
            prev_cursor: null,
        }),
    });
    await new Promise((resolve) => setTimeout(resolve, 0));
    assert.deepEqual(
        saved.replies?.map((row) => row.id),
        ['one'],
    );
});

for (const isOpen of [true, false]) {
    for (const hasReplies of [true, false]) {
        test(`applies a posted quote to an ${isOpen ? 'expanded' : 'collapsed'} thread with ${hasReplies ? 'existing' : 'no'} replies`, () => {
            const original = hasReplies ? [reply('existing')] : [];
            saved = { replies: original, nextCursor: null, isOpen };
            const initial = {
                data: original,
                next_cursor: null,
                prev_cursor: null,
            };
            const refreshed = {
                data: [...original, reply('posted-quote')],
                next_cursor: null,
                prev_cursor: null,
            };
            let thread: ReturnType<typeof useThread> | undefined;
            function Probe() {
                const [posted, setPosted] = React.useState(false);
                thread = useThread(
                    {
                        ...reply(`quoted-${isOpen}-${hasReplies}`),
                        replies_count: refreshed.data.length,
                    },
                    posted ? refreshed : initial,
                );
                if (!posted) {
                    setPosted(true);
                }
                return null;
            }
            renderToString(<Probe />);
            assert.ok(thread);
            assert.deepEqual(
                thread.replies?.map((row) => row.id),
                refreshed.data.map((row) => row.id),
            );
            assert.equal(thread.isOpen, isOpen);
            assert.equal(thread.hasMore, false);
        });
    }
}

test('refreshes all loaded reply pages after posting a quote without collapsing the thread', async () => {
    const original = ['one', 'two', 'three', 'four'].map(reply);
    const refreshed = [...original, reply('posted-quote')];
    saved = { replies: original, nextCursor: null, isOpen: true };
    const initial = {
        data: original.slice(0, 3),
        next_cursor: 'next',
        prev_cursor: null,
    };
    const fetch = vi.fn().mockImplementation(async (url: string) => {
        const last = new URL(url, 'http://localhost').searchParams.has(
            'cursor',
        );
        return {
            ok: true,
            json: async () => ({
                data: last ? refreshed.slice(3) : refreshed.slice(0, 3),
                next_cursor: last ? null : 'next',
                prev_cursor: null,
            }),
        };
    });
    vi.stubGlobal('fetch', fetch);
    function Probe() {
        const [posted, setPosted] = React.useState(false);
        useThread(
            { ...reply('quoted-paginated'), replies_count: posted ? 5 : 4 },
            initial,
        );
        if (!posted) {
            setPosted(true);
        }
        return null;
    }
    renderToString(<Probe />);
    assert.deepEqual(saved.replies, original);
    for (const effect of effects.splice(0)) effect();
    await vi.waitFor(() =>
        assert.deepEqual(
            saved.replies?.map((row) => row.id),
            refreshed.map((row) => row.id),
        ),
    );
    assert.equal(saved.isOpen, true);
    assert.equal(saved.nextCursor, null);
    assert.equal(fetch.mock.calls.length, 2);
});

test('keeps the posted quote when an older pagination response finishes later', async () => {
    saved = { replies: [reply('existing')], nextCursor: 'next', isOpen: true };
    const initial = {
        data: [reply('existing')],
        next_cursor: 'next',
        prev_cursor: null,
    };
    const refreshed = {
        data: [reply('existing'), reply('posted-quote')],
        next_cursor: null,
        prev_cursor: null,
    };
    let finish!: (value: unknown) => void;
    const fetch = vi.fn(
        () =>
            new Promise((resolve) => {
                finish = resolve;
            }),
    );
    vi.stubGlobal('fetch', fetch);
    function Probe() {
        const [posted, setPosted] = React.useState(false);
        const thread = useThread(
            { ...reply('quote-during-pagination'), replies_count: 2 },
            posted ? refreshed : initial,
        );
        if (!posted) {
            thread.loadMore();
            setPosted(true);
        }
        return null;
    }

    renderToString(<Probe />);
    finish({
        ok: true,
        json: async () => ({
            data: [reply('stale')],
            next_cursor: null,
            prev_cursor: null,
        }),
    });
    await new Promise((resolve) => setTimeout(resolve, 0));

    assert.deepEqual(
        saved.replies?.map((row) => row.id),
        ['existing', 'posted-quote'],
    );
    assert.equal(saved.nextCursor, null);
});

for (const isOpen of [true, false]) {
    for (const size of [1, 4]) {
        test(`refreshes ${size} loaded replies of an ${isOpen ? 'expanded' : 'collapsed'} continuation thread after a quote`, async () => {
            const original = Array.from({ length: size }, (_, index) =>
                reply(String(index)),
            );
            const refreshed = [...original, reply('posted-quote')];
            saved = { replies: original, nextCursor: null, isOpen };
            const fetch = vi.fn().mockImplementation(async (url: string) => {
                const query = new URL(url, 'http://localhost').searchParams;
                assert.equal(query.get('except'), 'author-continuation');
                const last = query.has('cursor') || refreshed.length <= 3;
                return {
                    ok: true,
                    json: async () => ({
                        data: query.has('cursor')
                            ? refreshed.slice(3)
                            : refreshed.slice(0, 3),
                        next_cursor: last ? null : 'next',
                        prev_cursor: null,
                    }),
                };
            });
            vi.stubGlobal('fetch', fetch);
            function Probe() {
                const [posted, setPosted] = React.useState(false);
                useThread(
                    {
                        ...reply(`continuation-quote-${isOpen}-${size}`),
                        replies_count: size + (posted ? 2 : 1),
                    },
                    undefined,
                    false,
                    'author-continuation',
                );
                if (!posted) {
                    setPosted(true);
                }
                return null;
            }

            renderToString(<Probe />);
            for (const effect of effects.splice(0)) effect();
            await vi.waitFor(() =>
                assert.deepEqual(
                    saved.replies?.map((row) => row.id),
                    refreshed.map((row) => row.id),
                ),
            );

            assert.equal(saved.isOpen, isOpen);
            assert.equal(saved.nextCursor, null);
            assert.equal(fetch.mock.calls.length, size > 3 ? 2 : 1);
        });
    }
}
