/// <reference types="node" />
import assert from 'node:assert/strict';
import * as React from 'react';
import type { ReactElement, ReactNode } from 'react';
import { test, vi } from 'vite-plus/test';

let state: unknown[] = [];
let cursor = 0;
let effects: Array<() => unknown> | null = null;
const visit = vi.fn();
let userId: string | null = null;
const storage = new Map<string, string>();
let hotTopics = [
    { id: 'topic-one', name: 'Database indexes', summary: null },
    { id: 'topic-two', name: 'Small language models', summary: null },
];

vi.doMock('../../resources/js/lib/storage', () => ({
    readStorage: (key: string) => storage.get(key) ?? '',
    writeStorage: (key: string, value: string) => {
        if (value === '') {
            storage.delete(key);
        } else {
            storage.set(key, value);
        }
    },
}));

vi.doMock('../../resources/js/hooks/use-media-query', () => ({
    useMediaQuery: () => false,
}));

vi.doMock('react', () => ({
    ...React,
    useEffect: (effect: () => unknown) => {
        effects?.push(effect);
    },
    useRef: (initial: unknown) => {
        const index = cursor++;
        if (!(index in state)) {
            state[index] = { current: initial };
        }
        return state[index];
    },
    useSyncExternalStore: (_subscribe: unknown, snapshot: () => unknown) =>
        snapshot(),
    useState: (initial: unknown) => {
        const index = cursor++;
        if (!(index in state)) {
            state[index] = typeof initial === 'function' ? initial() : initial;
        }
        return [
            state[index],
            (value: unknown) => {
                state[index] =
                    typeof value === 'function' ? value(state[index]) : value;
            },
        ];
    },
}));
vi.doMock('@inertiajs/react', async () => ({
    ...(await vi.importActual('@inertiajs/react')),
    router: { visit },
    usePage: () => ({
        props: {
            auth: { user: userId ? { id: userId } : null },
            ...(userId ? { hotTopics } : {}),
        },
    }),
}));

const { SearchDialog } =
    await import('../../resources/js/components/search-dialog');
const { rememberSearch, clearRecentSearches, useRecentSearches } =
    await import('../../resources/js/hooks/use-recent-searches');

type Element = ReactElement<Record<string, unknown>>;

function elements(node: ReactNode): Element[] {
    if (!React.isValidElement(node)) {
        return [];
    }
    const element = node as Element;
    return [
        element,
        ...React.Children.toArray(element.props.children as ReactNode).flatMap(
            elements,
        ),
    ];
}

function input() {
    cursor = 0;
    const dialog = SearchDialog({ open: true, onOpenChange: () => {} });
    return elements(dialog).find(
        (element) => element.props['data-test'] === 'search-input',
    )!;
}

function keyDown(key: string, isComposing: boolean) {
    const event = {
        key,
        nativeEvent: { isComposing },
        preventDefault: vi.fn(),
    };
    (input().props.onKeyDown as (event: unknown) => void)(event);
    return event;
}

test('leaves Enter and the arrows to the IME while composing', () => {
    state = [];
    storage.clear();
    visit.mockClear();
    userId = 'author';
    assert.equal(input().props.value, '');
    assert.equal(input().props['aria-activedescendant'], 'search-topic-one');

    const composedEnter = keyDown('Enter', true);
    assert.equal(composedEnter.preventDefault.mock.calls.length, 0);
    assert.equal(input().props.value, '');

    const composedArrow = keyDown('ArrowDown', true);
    assert.equal(composedArrow.preventDefault.mock.calls.length, 0);
    assert.equal(input().props['aria-activedescendant'], 'search-topic-one');

    keyDown('ArrowDown', false);
    assert.equal(input().props['aria-activedescendant'], 'search-topic-two');
    const enter = keyDown('Enter', false);
    assert.equal(enter.preventDefault.mock.calls.length, 1);
    assert.deepEqual(visit.mock.calls[0], [
        { url: '/headlines/topic-two', method: 'get' },
    ]);
});

test('has no topic options for guests or when there are no hot topics', () => {
    state = [];
    storage.clear();
    userId = null;
    assert.equal(input().props['aria-expanded'], false);
    assert.equal(input().props['aria-activedescendant'], undefined);
    assert.equal(keyDown('Enter', false).preventDefault.mock.calls.length, 0);

    const topics = hotTopics;
    hotTopics = [];
    userId = 'author';
    assert.equal(input().props['aria-expanded'], false);
    hotTopics = topics;
});

test.each([200, 429])(
    'stops showing a pending search after an empty guest response (%i)',
    async (status) => {
        state = [];
        storage.clear();
        userId = null;
        effects = [];
        vi.useFakeTimers();
        const fetch = vi
            .spyOn(globalThis, 'fetch')
            .mockResolvedValue(
                new Response(JSON.stringify({ users: [], organizations: [] }), {
                    status,
                }),
            );

        try {
            (input().props.onChange as (event: unknown) => void)({
                target: { value: '@no-such-developer' },
            });
            cursor = 0;
            const pending = SearchDialog({
                open: true,
                onOpenChange: () => {},
            });
            assert.ok(
                elements(pending).some(
                    (element) => element.props.children === 'Searching…',
                ),
            );
            effects.forEach((effect) => effect());
            await vi.runAllTimersAsync();

            cursor = 0;
            const settled = SearchDialog({
                open: true,
                onOpenChange: () => {},
            });
            const statuses = elements(settled)
                .filter((element) => element.props.role === 'status')
                .map((element) =>
                    React.Children.toArray(
                        element.props.children as ReactNode,
                    ).join(''),
                );
            assert.equal(statuses.includes('Searching…'), false);
            assert.ok(
                statuses.some((text) =>
                    text.startsWith(
                        status === 429
                            ? 'error: too many searches'
                            : 'fatal: nothing matches',
                    ),
                ),
            );
        } finally {
            effects = null;
            fetch.mockRestore();
            vi.useRealTimers();
        }
    },
);

test('persists only three distinct searches, evicting the oldest and promoting repeats', () => {
    storage.clear();
    for (const query of [
        'first search',
        'second search',
        'third search',
        'fourth search',
    ]) {
        rememberSearch('author', query);
    }

    assert.deepEqual(JSON.parse(storage.get('recent-searches:author')!), [
        'fourth search',
        'third search',
        'second search',
    ]);

    rememberSearch('author', '  THIRD search  ');
    rememberSearch('author', '');
    rememberSearch('author', 'x'.repeat(101));
    assert.deepEqual(useRecentSearches('author'), [
        'THIRD search',
        'fourth search',
        'second search',
    ]);
    assert.deepEqual(
        JSON.parse(storage.get('recent-searches:author')!),
        useRecentSearches('author'),
    );
});

test('isolates accounts, skips guests, and removes stored history on cleanup', () => {
    storage.clear();
    rememberSearch('author', 'private query');
    rememberSearch('other', 'another query');
    rememberSearch(null, 'guest query');

    assert.deepEqual(useRecentSearches(null), []);
    assert.deepEqual(useRecentSearches('other'), ['another query']);
    assert.equal(storage.size, 2);

    clearRecentSearches('author');
    assert.deepEqual(useRecentSearches('author'), []);
    assert.equal(storage.has('recent-searches:author'), false);
    assert.deepEqual(useRecentSearches('other'), ['another query']);

    storage.set('recent-searches:author', 'invalid JSON');
    assert.deepEqual(useRecentSearches('author'), []);
    rememberSearch('author', 'new query');
    assert.deepEqual(useRecentSearches('author'), ['new query']);
});

test('offers recent searches before hot topics and selects them with the keyboard', () => {
    state = [];
    storage.clear();
    userId = 'author';
    rememberSearch(userId, 'older query');
    rememberSearch(userId, 'latest query');

    assert.equal(input().props['aria-activedescendant'], 'search-recent-0');
    keyDown('Enter', false);
    assert.equal(input().props.value, 'latest query');

    state = [];
    keyDown('ArrowDown', false);
    keyDown('Enter', false);
    assert.equal(input().props.value, 'older query');

    state = [];
    keyDown('ArrowDown', false);
    keyDown('ArrowDown', false);
    assert.equal(input().props['aria-activedescendant'], 'search-topic-one');
});

test('clears recent searches, keeps other accounts intact, and focuses the search input', () => {
    state = [];
    storage.clear();
    userId = 'author';
    rememberSearch('author', 'older query');
    rememberSearch('author', 'latest query');
    rememberSearch('other', 'another query');
    keyDown('ArrowDown', false);
    const field = input();
    const focus = vi.fn();
    (field.props.ref as { current: { focus: () => void } | null }).current = {
        focus,
    };

    cursor = 0;
    const dialog = SearchDialog({ open: true, onOpenChange: () => {} });
    const clear = elements(dialog)
        .flatMap((element) => elements(element.props.heading as ReactNode))
        .find(
            (element) =>
                element.props['aria-label'] === 'Clear recent searches',
        );
    assert.ok(clear);
    (clear.props.onClick as () => void)();

    assert.equal(storage.has('recent-searches:author'), false);
    assert.deepEqual(useRecentSearches('other'), ['another query']);
    assert.equal(focus.mock.calls.length, 1);
    assert.equal(input().props['aria-activedescendant'], 'search-topic-one');
});
