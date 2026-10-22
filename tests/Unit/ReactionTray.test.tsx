/// <reference types="node" />
import assert from 'node:assert/strict';
import * as React from 'react';
import type { EffectCallback, ReactElement, ReactNode, RefObject } from 'react';
import { afterEach, test, vi } from 'vite-plus/test';

let state: unknown[] = [];
let cursor = 0;
let effects: EffectCallback[] = [];

afterEach(() => vi.unstubAllGlobals());

vi.doMock('react', () => ({
    ...React,
    useEffect: (effect: EffectCallback) => {
        effects.push(effect);
    },
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

const { ReactionTray, ReactionChips, EmojiPicker } =
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

function setup(animations: { finished: Promise<unknown> }[] = []) {
    vi.stubGlobal('document', {
        addEventListener: vi.fn(),
        removeEventListener: vi.fn(),
    });
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
        effects = [];
        const result = elements(
            ReactionTray({
                id: 'reactions',
                isOpen,
                onOpenChange,
                trigger,
                usage: [],
                reacted: new Set(),
                hasReactions: false,
                isMine: false,
                onReact,
            }),
        );
        (result[0].props.ref as RefObject<unknown>).current = {
            getAnimations: () => animations,
            querySelector: () => ({ focus: vi.fn() }),
        };
        for (const effect of effects) effect();
        return result;
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

test('waits for the full picker and then the tray to exit before applying a reaction', async () => {
    const fold = Promise.withResolvers<void>();
    const tray = setup([{ finished: fold.promise }]);
    tray.setPickerOpen(true);
    tray.pick('🦄');

    assert.equal(tray.popover().props.open, false);
    assert.equal(tray.onReact.mock.calls.length, 0);
    assert.equal(tray.onOpenChange.mock.calls.length, 0);
    assert.equal(tray.focus.mock.calls.length, 0);

    const event = tray.finishClose();
    tray.render();
    assert.equal(tray.onReact.mock.calls.length, 0);

    fold.resolve();
    await new Promise(setImmediate);
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

test('quick reactions close the tray before changing its message or presets', async () => {
    const fold = Promise.withResolvers<void>();
    const margin = Promise.withResolvers<void>();
    const tray = setup([
        { finished: fold.promise },
        { finished: margin.promise },
    ]);
    const button = tray
        .render()
        .find((e) => e.props['data-test'] === 'quick-reaction')!;

    (button.props.onClick as () => void)();
    tray.render();

    assert.equal(tray.onReact.mock.calls.length, 0);
    assert.deepEqual(tray.onOpenChange.mock.calls, [[false]]);
    assert.equal(tray.focus.mock.calls.length, 1);

    fold.resolve();
    await new Promise(setImmediate);
    assert.equal(tray.onReact.mock.calls.length, 0);

    margin.resolve();
    await new Promise(setImmediate);
    assert.deepEqual(tray.onReact.mock.calls, [['😂']]);

    tray.render();
    assert.equal(tray.onReact.mock.calls.length, 1);
});

test('quick reactions apply without waiting when reduced motion disables the fold', async () => {
    const tray = setup();
    const button = tray
        .render()
        .find((e) => e.props['data-test'] === 'quick-reaction')!;

    (button.props.onClick as () => void)();
    tray.render();
    await new Promise(setImmediate);

    assert.deepEqual(tray.onReact.mock.calls, [['😂']]);
});

test('an interrupted fold still applies its selected reaction once', async () => {
    const fold = Promise.withResolvers<void>();
    const tray = setup([{ finished: fold.promise }]);
    const button = tray
        .render()
        .find((e) => e.props['data-test'] === 'quick-reaction')!;

    (button.props.onClick as () => void)();
    tray.render();
    fold.reject(new Error('Transition cancelled'));
    await new Promise(setImmediate);
    tray.render();

    assert.deepEqual(tray.onReact.mock.calls, [['😂']]);
});

function renderChips(reactions: { user_id: string; emoji: string }[]) {
    const props = {
        reactions,
        userId: 'me',
        isMine: false,
        nameOf: (id: string) => (id === 'me' ? 'You' : 'Alex'),
        onToggle: vi.fn(),
    };
    cursor = 0;
    ReactionChips(props);
    cursor = 0;
    return elements(ReactionChips(props));
}

test('retains the final reaction for an inert exit instead of removing its row immediately', () => {
    state = [];
    renderChips([{ user_id: 'me', emoji: '😂' }]);

    const leaving = renderChips([]);

    assert.ok(
        leaving.some(
            (element) => element.props['data-test'] === 'message-reactions',
        ),
    );
    assert.ok(leaving.some((element) => element.props.inert === true));
    assert.ok(leaving.some((element) => element.props['aria-hidden'] === true));
    assert.equal(
        leaving.find(
            (element) => element.props['data-test'] === 'reaction-chip',
        )!.props.disabled,
        true,
    );
});

test('restores the current reactions when an exit is interrupted by a rollback or new selection', () => {
    state = [];
    renderChips([{ user_id: 'me', emoji: '😂' }]);
    renderChips([]);

    const restored = renderChips([{ user_id: 'me', emoji: '❤️' }]);
    const chip = restored.find(
        (element) => element.props['data-test'] === 'reaction-chip',
    )!;

    assert.equal(
        restored.some((element) => element.props.inert === true),
        false,
    );
    assert.equal(chip.props.disabled, false);
    assert.equal(chip.props['aria-pressed'], true);
    assert.ok((chip.props['aria-label'] as string).includes('from You'));
    assert.equal(
        restored.find(
            (element) =>
                element.type === 'span' && element.props.children === '❤️',
        )?.props.children,
        '❤️',
    );
});

test('updates a remaining reaction instead of retaining a chip that was removed', () => {
    state = [];
    renderChips([
        { user_id: 'me', emoji: '😂' },
        { user_id: 'other', emoji: '❤️' },
    ]);

    const remaining = renderChips([{ user_id: 'other', emoji: '❤️' }]);
    const chips = remaining.filter(
        (element) => element.props['data-test'] === 'reaction-chip',
    );

    assert.equal(chips.length, 1);
    assert.equal(chips[0].props['aria-pressed'], false);
    assert.ok((chips[0].props['aria-label'] as string).includes('from Alex'));
});
