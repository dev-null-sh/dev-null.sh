/// <reference types="node" />
import assert from 'node:assert/strict';
import * as React from 'react';
import type { EffectCallback, ReactElement, ReactNode, RefObject } from 'react';
import { afterEach, test, vi } from 'vite-plus/test';

let state: unknown[] = [];
let cursor = 0;
let effects: EffectCallback[] = [];

afterEach(() => {
    vi.useRealTimers();
    vi.unstubAllGlobals();
});

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

const { ReactionTray, ReactionChips, EmojiPicker, useTouchReactions } =
    await import('../../resources/js/components/chat/message-reactions');

type Element = ReactElement<Record<string, unknown>>;

function elements(node: ReactNode): Element[] {
    if (!React.isValidElement(node)) return [];
    const element = node as Element;
    if (
        typeof element.type === 'function' &&
        element.type.name === 'ReactionChip'
    ) {
        return elements(
            (element.type as (props: unknown) => ReactNode)(element.props),
        );
    }
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

function renderChips(
    reactions: { user_id: string; emoji: string }[],
    onToggle: ((emoji: string) => void) | null = vi.fn(),
) {
    const props = {
        reactions,
        userId: 'me',
        isMine: false,
        nameOf: (id: string) => (id === 'me' ? 'You' : 'Alex'),
        onToggle,
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

function touchReactions() {
    state = [];
    cursor = 0;
    vi.useFakeTimers();
    vi.stubGlobal('window', globalThis);
    vi.stubGlobal('navigator', { vibrate: vi.fn() });
    const onLongPress = vi.fn();
    const onDoubleTap = vi.fn();
    const handlers = useTouchReactions({ onLongPress, onDoubleTap });
    const message = { closest: () => null };
    const viewer = { closest: () => null };
    const row = { contains: (target: unknown) => target === message };
    const pointer = (
        overrides: Partial<React.PointerEvent> = {},
    ): React.PointerEvent =>
        ({
            pointerType: 'touch',
            isPrimary: true,
            clientX: 10,
            clientY: 20,
            timeStamp: 100,
            currentTarget: row,
            target: message,
            ...overrides,
        }) as unknown as React.PointerEvent;

    return { handlers, pointer, viewer, onLongPress, onDoubleTap };
}

test('opens reactions when a single finger holds the message', () => {
    const touch = touchReactions();

    touch.handlers.onPointerDown?.(touch.pointer());
    vi.advanceTimersByTime(500);

    assert.equal(touch.onLongPress.mock.calls.length, 1);
});

test('ignores a hold bubbling through the message from a portaled image viewer', () => {
    const touch = touchReactions();

    touch.handlers.onPointerDown?.(
        touch.pointer({ target: touch.viewer as unknown as EventTarget }),
    );
    vi.advanceTimersByTime(500);

    assert.equal(touch.onLongPress.mock.calls.length, 0);
});

test('a second finger cancels the pending message hold before a pinch', () => {
    const touch = touchReactions();

    touch.handlers.onPointerDown?.(touch.pointer());
    vi.advanceTimersByTime(200);
    touch.handlers.onPointerDown?.(touch.pointer({ isPrimary: false }));
    vi.advanceTimersByTime(500);

    assert.equal(touch.onLongPress.mock.calls.length, 0);
});

test('a touch in the image viewer cancels a pending hold on its message', () => {
    const touch = touchReactions();

    touch.handlers.onPointerDown?.(touch.pointer());
    vi.advanceTimersByTime(200);
    touch.handlers.onPointerDown?.(
        touch.pointer({ target: touch.viewer as unknown as EventTarget }),
    );
    vi.advanceTimersByTime(500);

    assert.equal(touch.onLongPress.mock.calls.length, 0);
});

test('double tapping the message still reacts', () => {
    const touch = touchReactions();

    for (const timeStamp of [100, 300]) {
        const event = touch.pointer({ timeStamp });
        touch.handlers.onPointerDown?.(event);
        touch.handlers.onPointerUp?.(event);
    }

    assert.equal(touch.onDoubleTap.mock.calls.length, 1);
});

test('double tapping a portaled viewer never reacts to its message', () => {
    const touch = touchReactions();

    for (const timeStamp of [100, 300]) {
        const event = touch.pointer({
            timeStamp,
            target: touch.viewer as unknown as EventTarget,
        });
        touch.handlers.onPointerDown?.(event);
        touch.handlers.onPointerUp?.(event);
    }

    assert.equal(touch.onDoubleTap.mock.calls.length, 0);
});

function touchChips(onToggle: ((emoji: string) => void) | null = vi.fn()) {
    state = [];
    vi.useFakeTimers();
    vi.stubGlobal('window', globalThis);
    vi.stubGlobal('navigator', { vibrate: vi.fn() });
    const render = () =>
        renderChips([{ user_id: 'other', emoji: '❤️' }], onToggle);
    const chip = () =>
        render().find(
            (element) => element.props['data-test'] === 'reaction-chip',
        )!;
    const target = { closest: () => target };
    const pointer = (overrides: Partial<React.PointerEvent> = {}) =>
        ({
            pointerType: 'touch',
            isPrimary: true,
            clientX: 10,
            clientY: 20,
            currentTarget: { contains: () => true },
            target,
            stopPropagation: vi.fn(),
            ...overrides,
        }) as unknown as React.PointerEvent;
    const fire = (handler: string, event: unknown = pointer()) =>
        (chip().props[handler] as ((event: unknown) => void) | undefined)?.(
            event,
        );
    const isOpen = () =>
        render().some((element) => element.props.open === true);
    return { render, chip, pointer, fire, isOpen };
}

test('holding a reaction chip reveals its authors without toggling the reaction on release', () => {
    const onToggle = vi.fn();
    const touch = touchChips(onToggle);
    const pointer = touch.pointer();
    touch.fire('onPointerDown', pointer);
    vi.advanceTimersByTime(419);
    assert.equal(touch.isOpen(), false);
    vi.advanceTimersByTime(1);
    assert.equal(touch.isOpen(), true);
    assert.ok(
        touch
            .render()
            .some(
                (element) =>
                    element.type === 'li' && element.props.children === 'Alex',
            ),
    );

    touch.fire('onPointerUp', pointer);
    const click = { preventDefault: vi.fn(), stopPropagation: vi.fn() };
    touch.fire('onClickCapture', click);
    if (click.preventDefault.mock.calls.length === 0) touch.fire('onClick');
    assert.equal(click.preventDefault.mock.calls.length, 1);
    assert.equal(onToggle.mock.calls.length, 0);
    assert.equal(touch.isOpen(), true);
});

test('a short tap still toggles the reaction instead of opening its authors', () => {
    const onToggle = vi.fn();
    const touch = touchChips(onToggle);
    touch.fire('onPointerDown');
    vi.advanceTimersByTime(100);
    touch.fire('onPointerUp');
    const click = { preventDefault: vi.fn(), stopPropagation: vi.fn() };
    touch.fire('onClickCapture', click);
    if (click.preventDefault.mock.calls.length === 0) touch.fire('onClick');
    vi.advanceTimersByTime(500);
    assert.equal(touch.isOpen(), false);
    assert.deepEqual(onToggle.mock.calls, [['❤️']]);
});

for (const interruption of ['movement', 'cancel', 'second finger', 'mouse']) {
    test(`${interruption} does not open reaction authors`, () => {
        const touch = touchChips();
        touch.fire(
            'onPointerDown',
            touch.pointer({
                pointerType: interruption === 'mouse' ? 'mouse' : 'touch',
            }),
        );
        vi.advanceTimersByTime(100);
        if (interruption === 'movement')
            touch.fire('onPointerMove', touch.pointer({ clientY: 40 }));
        if (interruption === 'cancel') touch.fire('onPointerCancel');
        if (interruption === 'second finger')
            touch.fire('onPointerDown', touch.pointer({ isPrimary: false }));
        vi.advanceTimersByTime(500);
        assert.equal(touch.isOpen(), false);
    });
}

test('reaction authors remain available when reacting is unavailable', () => {
    const touch = touchChips(null);
    assert.equal(touch.chip().props.disabled, false);
    touch.fire('onPointerDown');
    vi.advanceTimersByTime(500);
    assert.equal(touch.isOpen(), true);
});

test('the keyboard context-menu shortcut opens authors and dismissal closes them', () => {
    const touch = touchChips();
    touch.fire('onKeyDown', {
        key: 'F10',
        shiftKey: true,
        preventDefault: vi.fn(),
    });
    assert.equal(touch.isOpen(), true);
    const popover = touch
        .render()
        .find((element) => element.props.open === true)!;
    (popover.props.onOpenChange as (open: boolean) => void)(false);
    assert.equal(touch.isOpen(), false);
});

test('focusing the chip as a hold ends keeps its authors open, while other focus may dismiss it', () => {
    const touch = touchChips();
    const chip = touch.chip();
    const anchor = { contains: (target: unknown) => target === anchor };
    (chip.props.ref as RefObject<unknown>).current = anchor;
    const content = touch
        .render()
        .find((element) => element.props['data-test'] === 'reaction-authors')!;
    const onFocusOutside = content.props.onFocusOutside as (
        event: unknown,
    ) => void;
    const release = { target: anchor, preventDefault: vi.fn() };
    onFocusOutside(release);
    assert.equal(release.preventDefault.mock.calls.length, 1);
    const elsewhere = { target: {}, preventDefault: vi.fn() };
    onFocusOutside(elsewhere);
    assert.equal(elsewhere.preventDefault.mock.calls.length, 0);
});
