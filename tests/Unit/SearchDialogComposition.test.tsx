/// <reference types="node" />
import assert from 'node:assert/strict';
import * as React from 'react';
import type { ReactElement, ReactNode } from 'react';
import { test, vi } from 'vite-plus/test';

let state: unknown[] = [];
let cursor = 0;
const visit = vi.fn();
let userId: string | null = null;
const storage = new Map<string, string>();

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

vi.doMock('react', () => ({
    ...React,
    useEffect: () => {},
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
        props: { auth: { user: userId ? { id: userId } : null } },
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
    userId = null;
    assert.equal(input().props.value, '');
    assert.equal(input().props['aria-activedescendant'], 'search-suggestion-0');

    const composedEnter = keyDown('Enter', true);
    assert.equal(composedEnter.preventDefault.mock.calls.length, 0);
    assert.equal(input().props.value, '');

    const composedArrow = keyDown('ArrowDown', true);
    assert.equal(composedArrow.preventDefault.mock.calls.length, 0);
    assert.equal(input().props['aria-activedescendant'], 'search-suggestion-0');

    keyDown('ArrowDown', false);
    assert.equal(input().props['aria-activedescendant'], 'search-suggestion-1');
    const enter = keyDown('Enter', false);
    assert.equal(enter.preventDefault.mock.calls.length, 1);
    assert.equal(input().props.value, 'debugging in production');
    assert.equal(visit.mock.calls.length, 0);
});

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

test('offers recent searches before suggestions and selects them with the keyboard', () => {
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
    assert.equal(input().props['aria-activedescendant'], 'search-suggestion-0');
});
