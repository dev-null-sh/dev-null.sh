/// <reference types="node" />
import assert from 'node:assert/strict';
import * as React from 'react';
import type { EffectCallback, ReactElement, ReactNode } from 'react';
import { test, vi } from 'vite-plus/test';

let state: unknown[] = [];
let cursor = 0;
let effects: EffectCallback[] = [];

vi.doMock('react', () => ({
    ...React,
    useCallback: (callback: unknown) => callback,
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
                state[index] =
                    typeof value === 'function'
                        ? (value as (current: unknown) => unknown)(state[index])
                        : value;
            },
        ];
    },
    useSyncExternalStore: () => 1,
}));

class FakeAudio {
    static last: FakeAudio;
    currentTime = 0;
    volume = 1;
    playbackRate = 1;
    defaultPlaybackRate = 1;
    src = '';
    onpause: (() => void) | null = null;
    onended: (() => void) | null = null;

    constructor() {
        FakeAudio.last = this;
    }

    play() {
        this.playbackRate = this.defaultPlaybackRate;

        return Promise.resolve();
    }

    pause() {
        this.onpause?.();
    }
}

Object.assign(globalThis, {
    window: {
        addEventListener: () => undefined,
        matchMedia: () => ({ matches: true }),
        localStorage: { getItem: () => null },
    },
    Audio: FakeAudio,
    requestAnimationFrame: () => 0,
    cancelAnimationFrame: () => undefined,
});

const { VoiceNote } =
    await import('../../resources/js/components/chat/voice-note');

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

const fakeElement = () => ({
    textContent: '',
    style: { setProperty: () => undefined },
    setAttribute: () => undefined,
    addEventListener: () => undefined,
    removeEventListener: () => undefined,
});

function render() {
    cursor = 0;
    effects = [];
    const tree = elements(
        VoiceNote({
            voice: {
                file: new File([], 'note.webm'),
                duration_ms: 4900,
                waveform: [],
            },
        }) as ReactNode,
    );

    tree.forEach((element) => {
        const ref = element.props.ref as { current: unknown } | undefined;
        if (ref !== undefined && ref !== null && ref.current === null) {
            ref.current = fakeElement();
        }
    });
    effects.forEach((effect) => effect());

    return tree;
}

const clockOf = (tree: Element[]) =>
    (
        tree.find((element) => element.props['data-test'] === 'voice-note-clock')!
            .props.ref as { current: { textContent: string } }
    ).current.textContent;

test('a voice note sped up while playing still shows its sped-up length once it ends', async () => {
    state = [];
    let tree = render();

    (
        tree.find((element) => element.props['data-test'] === 'voice-note-toggle')!
            .props.onClick as () => void
    )();
    await new Promise((resolve) => setTimeout(resolve));
    tree = render();

    (
        tree.find(
            (element) =>
                (element.props as { onSpeedChange?: unknown }).onSpeedChange !==
                undefined,
        )!.props.onSpeedChange as (speed: number) => void
    )(2);
    tree = render();

    FakeAudio.last.currentTime = 4.9;
    FakeAudio.last.onended?.();

    assert.equal(FakeAudio.last.playbackRate, 2);
    assert.equal(clockOf(tree), '0:02');
});
