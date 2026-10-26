/// <reference types="node" />
import assert from 'node:assert/strict';
import * as React from 'react';
import { afterAll, afterEach, test, vi } from 'vite-plus/test';
import type { Embed } from '../../resources/js/types/commit';

let states: unknown[] = [];
let stateIndex = 0;
let didUpdate = false;
let effects: (() => void | (() => void))[] = [];
let reducedMotion = false;
let setVisible: (visible: boolean) => void;

vi.doMock('react', () => ({
    ...React,
    useEffect: (effect: () => void | (() => void)) => effects.push(effect),
    useState: (initial: unknown) => {
        const index = stateIndex++;
        states[index] ??= typeof initial === 'function' ? initial() : initial;

        return [
            states[index],
            (update: unknown) => {
                const next =
                    typeof update === 'function'
                        ? update(states[index])
                        : update;
                didUpdate ||= next !== states[index];
                states[index] = next;
            },
        ];
    },
}));

vi.doMock('../../resources/js/hooks/use-media-query', () => ({
    useMediaQuery: () => reducedMotion,
}));

vi.stubGlobal(
    'IntersectionObserver',
    class {
        constructor(callback: IntersectionObserverCallback) {
            setVisible = (visible) =>
                callback(
                    [
                        {
                            isIntersecting: visible,
                            intersectionRatio: visible ? 1 : 0,
                        } as IntersectionObserverEntry,
                    ],
                    this as unknown as IntersectionObserver,
                );
        }

        observe() {}
        disconnect() {}
    },
);

const { useGifPlayback } =
    await import('../../resources/js/hooks/use-gif-playback');

afterAll(() => vi.unstubAllGlobals());
afterEach(() => {
    states = [];
    stateIndex = 0;
    effects = [];
    reducedMotion = false;
});

const embed: Embed = {
    provider: 'GIF',
    kind: 'gif',
    src: 'https://example.com/cat.gif',
    poster: null,
    aspect_ratio: null,
    height: null,
};
const container = { current: {} as HTMLDivElement };

function render(current: Embed = embed) {
    let result: ReturnType<typeof useGifPlayback>;
    let renders = 0;

    do {
        assert.ok(
            renders++ < 10,
            'source state must settle without a render loop',
        );
        didUpdate = false;
        stateIndex = 0;
        result = useGifPlayback({ embed: current, isStatic: false, container });
    } while (didUpdate);

    return result;
}

function mount() {
    render();
    effects.shift()?.();
    setVisible(true);

    return render();
}

test('only manual retry clears a failure and the hook enforces two extra attempts', () => {
    mount().onError();
    let playback = render();

    assert.equal(playback.hasFailed, true);
    assert.equal(playback.isPlaying, false);
    assert.equal(playback.canRetry, true);

    setVisible(false);
    render();
    setVisible(true);
    playback = render();
    assert.equal(playback.hasFailed, true);
    assert.equal(playback.isPlaying, false);

    for (let attempt = 1; attempt <= 2; attempt++) {
        playback.onRetry();
        playback = render();
        assert.equal(playback.hasFailed, false);
        assert.equal(playback.isPlaying, true);
        assert.equal(playback.retryCount, attempt);
        playback.onError();
        playback = render();
    }

    assert.equal(playback.canRetry, false);
    playback.onRetry();
    playback = render();
    assert.equal(playback.hasFailed, true);
    assert.equal(playback.isPlaying, false);
    assert.equal(playback.retryCount, 2);
});

test('changing sources clears failure and the retry budget without stale error leakage', () => {
    const first = mount();
    first.onError();
    render().onRetry();
    render().onError();
    const failedFirst = render();
    const next = { ...embed, src: 'https://example.com/dog.gif' };
    let playback = render(next);

    assert.equal(playback.hasFailed, false);
    assert.equal(playback.isPlaying, true);
    assert.equal(playback.retryCount, 0);

    first.onError();
    playback = render(next);
    assert.equal(playback.hasFailed, false);
    playback.onToggle();
    failedFirst.onRetry();
    playback = render(next);
    assert.equal(playback.isPlaying, false);
    assert.equal(playback.retryCount, 0);
    playback.onError();
    assert.equal(render(next).hasFailed, true);
    playback = render();
    assert.equal(playback.hasFailed, false);
    assert.equal(playback.retryCount, 0);
});

test('retry is explicit playback for reduced motion and source changes retain Stop', () => {
    reducedMotion = true;
    let playback = mount();
    assert.equal(playback.isPlaying, false);
    playback.onToggle();
    playback = render();
    assert.equal(playback.isPlaying, true);
    playback.onError();
    render().onRetry();
    playback = render();
    assert.equal(playback.isPlaying, true);
    playback.onToggle();
    playback = render({ ...embed, src: 'https://example.com/dog.gif' });
    assert.equal(playback.isPlaying, false);
});
