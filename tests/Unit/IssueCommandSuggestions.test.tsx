/// <reference types="node" />
import assert from 'node:assert/strict';
import * as React from 'react';
import type { ReactElement, ReactNode } from 'react';
import { beforeEach, test, vi } from 'vite-plus/test';

let state: unknown[];
let cursor = 0;
let effects: (() => void | (() => void))[];
const position = { left: 10, top: 120, width: 288, maxHeight: 320 };
const positionAt = vi.fn(() => position);
const listeners = new Map<string, () => void>();
const removed: string[] = [];

vi.doMock('react', () => ({
    ...React,
    useLayoutEffect: (effect: () => void | (() => void)) =>
        effects.push(effect),
    useState: (initial: unknown) => {
        const index = cursor++;
        if (!(index in state)) state[index] = initial;
        return [
            state[index],
            (value: unknown) => {
                state[index] = value;
            },
        ];
    },
}));
vi.doMock('react-dom', () => ({
    createPortal: (children: unknown) => children,
}));
vi.doMock('@inertiajs/react', () => ({
    useHttp: () => ({}),
    usePage: () => ({ props: {} }),
}));
vi.doMock('../../resources/js/lib/suggestion-position', () => ({
    suggestionPositionAt: positionAt,
}));

const { IssueCommandSuggestions } =
    await import('../../resources/js/components/issue-command');
const { issueCommands } = await import('../../resources/js/lib/issue-commands');

type Element = ReactElement<Record<string, unknown>>;
function elements(node: ReactNode): Element[] {
    if (!React.isValidElement(node)) return [];
    const element = node as Element;
    return [
        element,
        ...React.Children.toArray(element.props.children as ReactNode).flatMap(
            elements,
        ),
    ];
}

function render(textarea: unknown) {
    cursor = 0;
    return IssueCommandSuggestions({
        suggestions: issueCommands,
        selected: 0,
        onPick: () => {},
        anchor: { current: textarea as HTMLTextAreaElement | null },
    });
}

beforeEach(() => {
    state = [];
    effects = [];
    listeners.clear();
    removed.length = 0;
    positionAt.mockClear();
    vi.stubGlobal('document', {
        body: {},
        addEventListener: (type: string, listener: () => void) =>
            listeners.set(`document:${type}`, listener),
        removeEventListener: (type: string) => removed.push(`document:${type}`),
    });
    vi.stubGlobal('window', {
        addEventListener: (type: string, listener: () => void) =>
            listeners.set(type, listener),
        removeEventListener: (type: string) => removed.push(type),
        visualViewport: null,
    });
});

test('floats over the composer under the slash, like the other suggestions, instead of pushing its actions down', () => {
    const textarea = { value: '/' };
    assert.equal(render(textarea), null);

    const cleanups = effects.map((effect) => effect());
    assert.deepEqual(positionAt.mock.calls, [[textarea, 0]]);

    const [menu, ...rest] = elements(render(textarea));
    assert.equal(menu.props['data-command-suggestions'], true);
    assert.equal(menu.props.role, 'listbox');
    assert.deepEqual(menu.props.style, position);
    assert.match(String(menu.props.className), /\bfixed\b/);
    assert.deepEqual(
        rest
            .filter((element) => element.props.role === 'option')
            .map((option) => option.props['aria-selected']),
        [true, false],
    );
    assert.deepEqual(
        rest
            .filter((element) => element.type === 'span')
            .map((label) => label.props.children)
            .filter((text) => typeof text === 'string' && text.startsWith('/')),
        ['/feature', '/bug'],
    );

    const preventDefault = vi.fn();
    (menu.props.onMouseDown as (event: unknown) => void)({ preventDefault });
    assert.equal(preventDefault.mock.calls.length, 1);

    listeners.get('scroll')!();
    listeners.get('resize')!();
    listeners.get('document:animationend')!();
    assert.equal(positionAt.mock.calls.length, 4);

    cleanups.forEach((cleanup) => cleanup?.());
    assert.deepEqual(removed.toSorted(), [
        'document:animationend',
        'resize',
        'scroll',
    ]);
});

test('stays closed until the field it belongs to is there', () => {
    assert.equal(render(null), null);
    effects.forEach((effect) => effect());
    assert.equal(render(null), null);
    assert.equal(positionAt.mock.calls.length, 0);
});
