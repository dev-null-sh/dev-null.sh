import assert from 'node:assert/strict';
import { router } from '@inertiajs/react';
import { renderToString } from 'react-dom/server';
import { afterEach, test, vi } from 'vite-plus/test';
import type { CursorPaginated, ThreadCommit } from '../../resources/js/types';

vi.doMock('@inertiajs/react', async () => ({
    ...(await vi.importActual('@inertiajs/react')),
    usePage: () => ({ props: { repliesPerPage: 3, auth: { user: null } } }),
}));
const { useContinuation } =
    await import('../../resources/js/hooks/use-continuation');
const { useThread } =
    await import('../../resources/js/components/commit-thread');
const reply = (id: string) =>
    ({ id, replies_count: 0, deleted_at: null }) as ThreadCommit;
const initial: CursorPaginated<ThreadCommit> = {
    data: [reply('first')],
    next_cursor: 'next',
    prev_cursor: null,
};
afterEach(() => vi.restoreAllMocks());
function restoredThread(saved: unknown, except?: string) {
    vi.spyOn(router, 'restore').mockImplementation((key) =>
        key === `thread:root:${except ?? ''}` ? (saved as never) : undefined,
    );
    let thread: ReturnType<typeof useThread> | undefined;
    function Probe() {
        thread = useThread(
            { ...reply('root'), replies_count: 18 },
            initial,
            false,
            except,
        );
        return null;
    }
    renderToString(<Probe />);
    assert.ok(thread);
    return thread;
}
test('restores all expanded replies before scroll restoration without refetching the first page', () => {
    const replies = Array.from({ length: 18 }, (_, i) => reply(String(i)));
    const thread = restoredThread({ replies, nextCursor: null, isOpen: true });
    assert.deepEqual(thread.replies, replies);
    assert.equal(thread.isOpen, true);
    assert.equal(thread.hasMore, false);
    assert.equal(thread.isLoading, false);
});
test('restores the next reply cursor and a deliberately collapsed thread', () => {
    const replies = [reply('first'), reply('second')];
    const thread = restoredThread(
        { replies, nextCursor: 'later', isOpen: false },
        'continuation',
    );
    assert.deepEqual(thread.replies, replies);
    assert.equal(thread.isOpen, false);
    assert.equal(thread.hasMore, true);
    assert.equal(thread.remaining, 16);
});
test('a fresh visit starts from the current server replies', () => {
    const thread = restoredThread(undefined);
    assert.deepEqual(thread.replies, initial.data);
    assert.equal(thread.isOpen, true);
    assert.equal(thread.hasMore, true);
});

test('restores loaded continuation pages and their next cursor', () => {
    const shown = [reply('next'), reply('last')];
    vi.spyOn(router, 'restore').mockImplementation((key) =>
        key === 'continuation'
            ? ({ shown, nextId: 'more', size: 42, isCapped: true } as never)
            : undefined,
    );
    let result: ReturnType<typeof useContinuation> | undefined;
    function Probe() {
        result = useContinuation({
            data: [],
            next_id: 'next',
            position: 1,
            length: 3,
            is_capped: false,
        });
        return null;
    }
    renderToString(<Probe />);
    assert.ok(result);
    assert.deepEqual(result.shown, shown);
    assert.equal(result.nextId, 'more');
    assert.equal(result.size, 42);
    assert.equal(result.isCapped, true);
    assert.equal(result.isLoading, false);
});
