/// <reference types="node" />
import assert from 'node:assert/strict';
import * as React from 'react';
import { afterAll, afterEach, test, vi } from 'vite-plus/test';
import type { CursorPaginated, ThreadCommit } from '../../resources/js/types';

type Page = CursorPaginated<ThreadCommit>;

const states: { value: unknown }[] = [];
const counts: number[] = [];
const effects: (() => (() => void) | void)[] = [];

vi.doMock('react', () => ({
    ...React,
    useEffect: (effect: () => (() => void) | void) => effects.push(effect),
    useEffectEvent: (callback: unknown) => callback,
    useRef: (initial: unknown) => ({ current: initial }),
    useState: (initial: unknown) => {
        const state = {
            value: typeof initial === 'function' ? initial() : initial,
        };
        states.push(state);

        return [
            state.value,
            (next: unknown) => {
                state.value =
                    typeof next === 'function' ? next(state.value) : next;
            },
        ];
    },
}));
vi.doMock('@inertiajs/react', async () => ({
    ...(await vi.importActual('@inertiajs/react')),
    usePage: () => ({ props: { repliesPerPage: 1 } }),
}));
vi.doMock('../../resources/js/hooks/use-shared-state', () => ({
    useSharedState: (_key: string, initial: number) => [
        initial,
        (next: number | ((current: number) => number)) =>
            counts.push(typeof next === 'function' ? next(initial) : next),
    ],
}));

const { useThread } =
    await import('../../resources/js/components/commit-thread');

const responses: ((page: Page) => void)[] = [];
vi.stubGlobal('fetch', () =>
    new Promise<Page>((resolve) => responses.push(resolve)).then((page) => ({
        ok: true,
        json: async () => page,
    })),
);
afterAll(() => vi.unstubAllGlobals());
afterEach(() => {
    states.length = 0;
    counts.length = 0;
    effects.length = 0;
    responses.length = 0;
});

const reply = (id: string) => ({ id, parent_id: 'parent' }) as ThreadCommit;
const page = (ids: string[], next_cursor: string | null): Page => ({
    data: ids.map(reply),
    next_cursor,
    prev_cursor: null,
});
const settle = () => new Promise((resolve) => setTimeout(resolve));

function openThread() {
    const initial = page(['first'], 'second-page');
    const thread = useThread(
        { id: 'parent', replies_count: 2 } as ThreadCommit,
        initial,
    );
    const replies = states.find((state) => state.value === initial.data);
    assert.ok(replies);

    return { thread, replies };
}

test('keeps the reply just posted when an older page arrives after the reload', async () => {
    const { thread, replies } = openThread();

    thread.loadMore();
    thread.onReplied({
        id: 'parent',
        replies_count: 3,
        continuation_id: null,
    });
    responses[1](page(['posted', 'first'], 'second-page'));
    await settle();
    responses[0](page(['second'], null));
    await settle();

    assert.deepEqual(
        (replies.value as ThreadCommit[]).map(({ id }) => id),
        ['posted', 'first'],
    );
    assert.deepEqual(counts, [3]);
});

test('applies only the newest of two overlapping reloads', async () => {
    const { thread, replies } = openThread();

    thread.onDeleted();
    thread.onDeleted();
    responses[1](page(['first'], 'second-page'));
    await settle();
    responses[0](page(['deleted', 'first'], 'second-page'));
    await settle();

    assert.deepEqual(
        (replies.value as ThreadCommit[]).map(({ id }) => id),
        ['first'],
    );
});

test('clears loading when the first-load refetch replaces a page still loading', async () => {
    const initial = page(['first'], 'second-page');
    const thread = useThread(
        { id: 'parent', replies_count: 2 } as ThreadCommit,
        initial,
        true,
    );
    const before = states.map((state) => state.value);

    thread.loadMore();
    const isLoading = states.find(
        (state, index) => before[index] === false && state.value === true,
    );
    assert.ok(isLoading);

    effects[0]();
    responses[0](page(['second'], null));
    responses[1](page(['first'], 'second-page'));
    await settle();

    assert.equal(isLoading.value, false);
});
