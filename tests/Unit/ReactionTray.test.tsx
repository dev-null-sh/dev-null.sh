/// <reference types="node" />
import assert from 'node:assert/strict';
import * as React from 'react';
import type { ReactElement, ReactNode, RefObject } from 'react';
import { test, vi } from 'vite-plus/test';

let state: unknown[] = [];
let cursor = 0;

vi.doMock('react', () => ({
    ...React,
    useEffect: () => {},
    useRef: (initial: unknown) => {
        const index = cursor++;
        if (!(index in state)) state[index] = { current: initial };
        return state[index];
    },
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

const { ReactionTray, EmojiPicker } =
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

function setup() {
    state = [];
    let isOpen = true;
    const focus = vi.fn();
    const onReact = vi.fn();
    const onOpenChange = vi.fn((open: boolean) => {
        isOpen = open;
    });
    const trigger = {
        current: { focus },
    } as unknown as RefObject<HTMLButtonElement | null>;
    const render = () => {
        cursor = 0;
        return elements(
            ReactionTray({
                id: 'reactions',
                isOpen,
                onOpenChange,
                trigger,
                usage: [],
                reacted: new Set(),
                isMine: false,
                onReact,
            }),
        );
    };
    const popover = () =>
        render().find((e) => typeof e.props.open === 'boolean')!;
    const setPickerOpen = (open: boolean) =>
        (popover().props.onOpenChange as (value: boolean) => void)(open);
    const pick = (emoji: string) =>
        (
            render().find((e) => e.type === EmojiPicker)!.props.onPick as (
                value: string,
            ) => void
        )(emoji);
    const finishClose = () => {
        const event = { preventDefault: vi.fn() };
        (
            render().find(
                (e) => typeof e.props.onCloseAutoFocus === 'function',
            )!.props.onCloseAutoFocus as (event: unknown) => void
        )(event);
        return event;
    };
    return {
        render,
        popover,
        setPickerOpen,
        pick,
        finishClose,
        onReact,
        onOpenChange,
        focus,
    };
}

test('keeps the reaction and tray unchanged until the selected emoji picker has exited', () => {
    const tray = setup();
    tray.setPickerOpen(true);
    tray.pick('🦄');

    assert.equal(tray.popover().props.open, false);
    assert.equal(tray.onReact.mock.calls.length, 0);
    assert.equal(tray.onOpenChange.mock.calls.length, 0);
    assert.equal(tray.focus.mock.calls.length, 0);

    const event = tray.finishClose();
    assert.deepEqual(tray.onReact.mock.calls, [['🦄']]);
    assert.deepEqual(tray.onOpenChange.mock.calls, [[false]]);
    assert.equal(tray.focus.mock.calls.length, 1);
    assert.equal(event.preventDefault.mock.calls.length, 1);

    tray.finishClose();
    assert.equal(tray.onReact.mock.calls.length, 1);
});

test('closing the popover without a selection does not dispatch or close the tray itself', () => {
    const tray = setup();
    tray.setPickerOpen(true);
    tray.setPickerOpen(false);
    const event = tray.finishClose();

    assert.equal(tray.onReact.mock.calls.length, 0);
    assert.equal(tray.onOpenChange.mock.calls.length, 0);
    assert.equal(event.preventDefault.mock.calls.length, 0);
});

test('quick reactions still apply and close the tray immediately', () => {
    const tray = setup();
    const button = tray
        .render()
        .find((e) => e.props['data-test'] === 'quick-reaction')!;
    (button.props.onClick as () => void)();

    assert.equal(tray.onReact.mock.calls.length, 1);
    assert.deepEqual(tray.onOpenChange.mock.calls, [[false]]);
    assert.equal(tray.focus.mock.calls.length, 1);
});
