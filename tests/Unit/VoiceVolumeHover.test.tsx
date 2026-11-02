/// <reference types="node" />
import assert from 'node:assert/strict';
import * as React from 'react';
import type { ReactElement, ReactNode } from 'react';
import { afterEach, test, vi } from 'vite-plus/test';

let state: unknown[] = [];
let cursor = 0;

afterEach(() => {
    vi.useRealTimers();
    vi.unstubAllGlobals();
});

vi.doMock('react', () => ({
    ...React,
    useEffect: () => undefined,
    useId: () => 'voice-volume',
    useRef: (initial: unknown) => {
        const index = cursor++;
        if (!(index in state)) state[index] = { current: initial };
        return state[index];
    },
    useSyncExternalStore: () => 1,
}));

const { VoiceVolume } =
    await import('../../resources/js/components/chat/voice-volume');

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

function render(isAdjusting: boolean, onAdjustingChange: (to: boolean) => void) {
    cursor = 0;
    const tree = elements(
        VoiceVolume({ isAdjusting, onAdjustingChange }) as ReactNode,
    );
    const find = (name: string) =>
        tree.find((element) => element.props['data-test'] === name)!;

    return { speaker: find('voice-volume'), fader: find('voice-volume-fader') };
}

test('a hover still waiting does not reopen the volume the keyboard just closed', () => {
    vi.useFakeTimers();
    vi.stubGlobal('window', globalThis);
    vi.stubGlobal('document', { activeElement: null });
    state = [];
    const changes: boolean[] = [];
    const { speaker, fader } = render(true, (to) => changes.push(to));

    (speaker.props.onPointerEnter as (event: object) => void)({
        pointerType: 'mouse',
    });
    (fader.props.onKeyDown as (event: object) => void)({
        key: 'Escape',
        preventDefault: () => undefined,
    });
    vi.advanceTimersByTime(500);

    assert.deepEqual(changes, [false]);
});
