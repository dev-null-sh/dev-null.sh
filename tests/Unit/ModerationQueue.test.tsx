/// <reference types="node" />
import assert from 'node:assert/strict';
import * as React from 'react';
import { afterEach, beforeEach, test, vi } from 'vite-plus/test';
import type { Commit } from '../../resources/js/types';

type Handlers = {
    onEvent: (event: string, payload: unknown) => void;
    onSync: (isFirst: boolean) => void;
};

let handlers: Handlers | null = null;
const reloads: { only?: string[]; reset?: string[] }[] = [];
const offers: unknown[] = [];

vi.doMock('react', () => ({
    ...React,
    useState: (initial: unknown) => [
        typeof initial === 'function' ? (initial as () => unknown)() : initial,
        (value: unknown) => offers.push(value),
    ],
    useEffect: (effect: () => void) => effect(),
    useRef: (current: unknown) => ({ current }),
    useEffectEvent: (callback: unknown) => callback,
    useSyncExternalStore: (_subscribe: unknown, read: () => unknown) => read(),
}));
vi.doMock('../../resources/js/hooks/use-realtime', async () => ({
    ...(await vi.importActual('../../resources/js/hooks/use-realtime')),
    useRealtime: (
        _channel: unknown,
        _events: unknown,
        onEvent: Handlers['onEvent'],
        onSync: Handlers['onSync'],
    ) => {
        handlers = { onEvent, onSync };
    },
}));
vi.doMock('@inertiajs/react', async () => ({
    ...(await vi.importActual('@inertiajs/react')),
    usePage: () => ({
        component: 'moderation/show',
        props: { auth: { user: { id: 'mod' } }, now: Date.now() },
    }),
    router: {
        reload: (options: { only?: string[]; reset?: string[] }) => {
            reloads.push(options);
        },
        replaceProp: () => {},
    },
}));

const { default: Show } =
    await import('../../resources/js/pages/moderation/show');

function open(count: number, nextCursor: string | null): Handlers {
    Show({
        commits: {
            data: Array.from(
                { length: count },
                (_, index) => ({ id: `commit-${index}` }) as Commit,
            ),
            next_cursor: nextCursor,
            prev_cursor: null,
        },
    });
    assert.ok(handlers);

    return handlers;
}

beforeEach(() => {
    vi.useFakeTimers();
    handlers = null;
    reloads.length = 0;
    offers.length = 0;
});

afterEach(() => {
    vi.useRealTimers();
});

test('a commit submitted to an empty queue shows up without asking for a refresh', () => {
    const queue = open(0, null);

    queue.onEvent('moderation', { commits: ['new'] });
    queue.onEvent('moderation', { commits: ['another'] });
    vi.runAllTimers();

    assert.equal(reloads.length, 1);
    assert.deepEqual(reloads[0].only, ['commits', 'hasUnmoderatedCommits']);
    assert.deepEqual(offers, []);
});

test('a queue that fits on one page joins what was submitted before it listened', () => {
    open(3, null).onSync(true);
    vi.runAllTimers();

    assert.equal(reloads.length, 1);
    assert.deepEqual(offers, []);
});

test('a queue longer than a page offers a refresh instead of dropping the pages read', () => {
    const queue = open(20, 'next');

    queue.onEvent('moderation', { commits: ['new'] });
    vi.runAllTimers();

    assert.equal(reloads.length, 0);
    assert.deepEqual(offers, [true]);
});
