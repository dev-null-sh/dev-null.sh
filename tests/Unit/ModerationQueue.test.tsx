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
type Reload = {
    only?: string[];
    reset?: string[];
    data?: Record<string, unknown>;
    onSuccess?: (page: { props: Record<string, unknown> }) => void;
    onFinish?: () => void;
};

const reloads: Reload[] = [];
let arrived: string[] = [];
const offers: unknown[] = [];

// Hook state kept across renders by call order, as React does, so a page rendered again keeps what it held.
let slots: unknown[] = [];
let slot = 0;
const kept = (initial: () => unknown) => {
    const at = slot++;

    if (!(at in slots)) {
        slots[at] = initial();
    }

    return slots[at];
};

vi.doMock('react', () => ({
    ...React,
    useState: (initial: unknown) => [
        kept(() =>
            typeof initial === 'function'
                ? (initial as () => unknown)()
                : initial,
        ),
        (value: unknown) => offers.push(value),
    ],
    useEffect: (effect: () => void) => effect(),
    useRef: (current: unknown) => kept(() => ({ current })),
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
        reload: (options: Reload) => {
            reloads.push(options);

            if (options.only?.includes('arrivedCommitIds')) {
                options.onSuccess?.({ props: { arrivedCommitIds: arrived } });
            }

            options.onFinish?.();
        },
        replaceProp: () => {},
        optimistic: () => ({ visit: () => {} }),
    },
}));

const { default: Show } =
    await import('../../resources/js/pages/moderation/show');

let page: ReturnType<typeof Show> | null = null;

function open(
    count: number,
    nextCursor: string | null,
    without: string[] = [],
): Handlers {
    slot = 0;
    page = Show({
        commits: {
            data: Array.from(
                { length: count },
                (_, index) => ({ id: `commit-${index}` }) as Commit,
            ).filter((commit) => !without.includes(commit.id)),
            next_cursor: nextCursor,
            prev_cursor: null,
        },
    });
    assert.ok(handlers);

    return handlers;
}

beforeEach(() => {
    vi.useFakeTimers();
    vi.stubGlobal('fetch', async (url: string) =>
        Response.json({
            changes: Object.fromEntries(
                new URL(url, 'http://localhost').searchParams
                    .getAll('ids[]')
                    .map((id) => [
                        id,
                        { changed: false, replies: [], truncated: false },
                    ]),
            ),
        }),
    );
    handlers = null;
    slots = [];
    vi.stubGlobal('requestAnimationFrame', () => 0);
    reloads.length = 0;
    offers.length = 0;
    arrived = [];
});

afterEach(() => {
    vi.unstubAllGlobals();
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

test('a queue that started listening late offers what was submitted before, without moving what it shows', async () => {
    arrived = ['commit-0', 'late'];
    open(3, null).onSync(true);
    await vi.runAllTimersAsync();

    assert.deepEqual(
        reloads.map((reload) => reload.only),
        [['arrivedCommitIds']],
    );
    assert.equal(typeof reloads[0].data?.since, 'number');
    assert.deepEqual(offers, [true]);
});

test('an empty queue that started listening late shows what was submitted before', async () => {
    arrived = ['late'];
    open(0, null).onSync(true);
    await vi.runAllTimersAsync();

    assert.deepEqual(
        reloads.map((reload) => reload.only),
        [['arrivedCommitIds'], ['commits', 'hasUnmoderatedCommits']],
    );
    assert.deepEqual(offers, []);
});

test('a queue that started listening with nothing submitted since stays as it is', async () => {
    open(3, null).onSync(true);
    await vi.runAllTimersAsync();

    assert.equal(reloads.length, 1);
    assert.deepEqual(offers, []);
});

test('a commit submitted to a queue showing commits is offered as a refresh, so nothing it shows moves', () => {
    open(3, null).onEvent('moderation', { commits: ['new'] });
    vi.runAllTimers();

    assert.equal(reloads.length, 0);
    assert.deepEqual(offers, [true]);
});

test('a queue longer than a page offers a refresh, keeping the pages read', () => {
    const queue = open(20, 'next');

    queue.onEvent('moderation', { commits: ['new'] });
    vi.runAllTimers();

    assert.equal(reloads.length, 0);
    assert.deepEqual(offers, [true]);
});

function find(
    node: React.ReactNode,
    matches: (props: Record<string, unknown>) => boolean,
): React.ReactElement<Record<string, unknown>> | null {
    if (Array.isArray(node)) {
        for (const child of node) {
            const found = find(child, matches);

            if (found) {
                return found;
            }
        }

        return null;
    }

    if (!React.isValidElement<Record<string, unknown>>(node)) {
        return null;
    }

    if (matches(node.props)) {
        return node;
    }

    return find(node.props.children as React.ReactNode, matches);
}

test('a commit approved here is not offered back as new when its approval is heard', () => {
    open(3, 'next');
    const feed = find(page, (props) => typeof props.actions === 'function');
    assert.ok(feed);
    const actions = (feed.props.actions as (commit: Commit) => React.ReactNode)(
        { id: 'commit-0' } as Commit,
    );
    const moderation = find(
        actions,
        (props) => typeof props.onModerate === 'function',
    );
    assert.ok(moderation);
    (
        moderation.props.onModerate as (
            route: (commit: Commit) => unknown,
            reason: string,
        ) => void
    )(() => '/moderation/commit-0/approval', '');

    open(3, 'next', ['commit-0']).onEvent('moderation', {
        commits: ['commit-0'],
    });
    assert.deepEqual(offers, []);

    open(3, 'next', ['commit-0']).onEvent('moderation', { commits: ['new'] });
    assert.deepEqual(offers, [true]);
});
