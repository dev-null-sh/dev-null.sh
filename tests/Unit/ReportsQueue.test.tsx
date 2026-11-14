/// <reference types="node" />
import assert from 'node:assert/strict';
import * as React from 'react';
import { afterEach, beforeEach, test, vi } from 'vite-plus/test';

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
let changes = { filed: [] as string[], dismissed: [] as string[] };
const replaced: string[][] = [];
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
vi.doMock('@/hooks/use-date-time', async () => {
    const { createDateTime } = await import('@/lib/date-time');

    return { useDateTime: () => createDateTime() };
});
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
        component: 'moderation/reports',
        props: { auth: { user: { id: 'mod' } }, now: Date.now() },
    }),
    router: {
        reload: (options: Reload) => {
            reloads.push(options);

            if (options.only?.includes('reportChanges')) {
                options.onSuccess?.({ props: { reportChanges: changes } });
            }

            options.onFinish?.();
        },
        replaceProp: (
            _prop: string,
            update: (value: { data: { id: string }[] }) => {
                data: { id: string }[];
            },
        ) => {
            replaced.push(update({ data: loaded }).data.map(({ id }) => id));
        },
    },
}));

const { default: Reports } =
    await import('../../resources/js/pages/moderation/reports');

let loaded: { id: string; commit: { id: string } }[] = [];

function open(count: number): Handlers {
    loaded = Array.from({ length: count }, (_, index) => ({
        id: `report-${index}`,
        created_at: '2026-11-04T12:00:00Z',
        commit: { id: `commit-${index}` },
    }));
    Reports({
        reports: { data: loaded, next_cursor: null, prev_cursor: null },
        reasons: { spam: 'Spam' },
    } as unknown as Parameters<typeof Reports>[0]);
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
    reloads.length = 0;
    offers.length = 0;
    replaced.length = 0;
    changes = { filed: [], dismissed: [] };
});

afterEach(() => {
    vi.unstubAllGlobals();
    vi.useRealTimers();
});

test('a reports queue that started listening late takes off what was dismissed before, where it is', async () => {
    changes = { filed: [], dismissed: ['report-1', 'elsewhere'] };
    open(3).onSync(true);
    await vi.runAllTimersAsync();

    assert.deepEqual(
        reloads.map((reload) => reload.only),
        [['reportChanges']],
    );
    assert.equal(typeof reloads[0].data?.since, 'number');
    assert.deepEqual(replaced, [['report-0', 'report-2']]);
    assert.deepEqual(offers, []);
});

test('a reports queue that started listening late offers what was filed before as a refresh', async () => {
    changes = { filed: ['report-0', 'new'], dismissed: [] };
    open(3).onSync(true);
    await vi.runAllTimersAsync();

    assert.deepEqual(replaced, []);
    assert.deepEqual(offers, [true]);
});

test('a reports queue that started listening with nothing changed since stays as it is', async () => {
    open(3).onSync(true);
    await vi.runAllTimersAsync();

    assert.deepEqual(replaced, []);
    assert.deepEqual(offers, []);
});
