/// <reference types="node" />
import assert from 'node:assert/strict';
import * as React from 'react';
import type { ReactElement, ReactNode } from 'react';
import { test, vi } from 'vite-plus/test';

let state: unknown[] = [];
let cursor = 0;
const visit = vi.fn();

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
}));

const { SearchDialog } =
    await import('../../resources/js/components/search-dialog');

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
    assert.equal(input().props.value, '');
    assert.equal(
        input().props['aria-activedescendant'],
        'search-suggestion-0',
    );

    const composedEnter = keyDown('Enter', true);
    assert.equal(composedEnter.preventDefault.mock.calls.length, 0);
    assert.equal(input().props.value, '');

    const composedArrow = keyDown('ArrowDown', true);
    assert.equal(composedArrow.preventDefault.mock.calls.length, 0);
    assert.equal(
        input().props['aria-activedescendant'],
        'search-suggestion-0',
    );

    keyDown('ArrowDown', false);
    assert.equal(
        input().props['aria-activedescendant'],
        'search-suggestion-1',
    );
    const enter = keyDown('Enter', false);
    assert.equal(enter.preventDefault.mock.calls.length, 1);
    assert.equal(input().props.value, 'debugging in production');
    assert.equal(visit.mock.calls.length, 0);
});
