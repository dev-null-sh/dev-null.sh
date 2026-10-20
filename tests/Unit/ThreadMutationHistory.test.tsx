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
