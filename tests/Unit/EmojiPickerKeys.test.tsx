/// <reference types="node" />
import assert from 'node:assert/strict';
import * as React from 'react';
import type { ReactElement, ReactNode } from 'react';
import { afterAll, test, vi } from 'vite-plus/test';

// Renders the picker once, as a plain function, with the groups still loading.
vi.doMock('react', () => ({
    ...React,
    useEffect: () => {},
    useId: () => 'picker',
    useMemo: (factory: () => unknown) => factory(),
    useRef: (initial: unknown) => ({ current: initial }),
    useState: (initial: unknown) => [
        typeof initial === 'function' ? (initial as () => unknown)() : initial,
        vi.fn(),
    ],
}));

afterAll(() => {
    vi.doUnmock('react');
});

const { EmojiPicker } =
    await import('../../resources/js/components/chat/message-reactions');

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

function pressEnter(isComposing: boolean, keyCode = 13) {
    const onPick = vi.fn();
    const preventDefault = vi.fn();
    const search = elements(
        EmojiPicker({
            usage: [{ emoji: '🚀', count: 2 }],
            reacted: new Set(),
            onPick,
        }),
    ).find((element) => element.props['data-test'] === 'emoji-search')!;

    (search.props.onKeyDown as (event: unknown) => void)({
        key: 'Enter',
        keyCode,
        nativeEvent: { isComposing },
        preventDefault,
    });

    return { onPick, preventDefault };
}

test('Enter picks the highlighted emoji', () => {
    const { onPick, preventDefault } = pressEnter(false);

    assert.deepEqual(onPick.mock.calls, [['🚀']]);
    assert.equal(preventDefault.mock.calls.length, 1);
});

test('Enter that confirms an input method candidate picks nothing', () => {
    const { onPick, preventDefault } = pressEnter(true);

    assert.equal(onPick.mock.calls.length, 0);
    assert.equal(preventDefault.mock.calls.length, 0);
});

test("Safari's Enter that confirms an input method candidate picks nothing", () => {
    const { onPick, preventDefault } = pressEnter(false, 229);

    assert.equal(onPick.mock.calls.length, 0);
    assert.equal(preventDefault.mock.calls.length, 0);
});
