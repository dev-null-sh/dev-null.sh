import assert from 'node:assert/strict';
import { afterEach, test } from 'node:test';
import type { MouseEvent } from 'react';
import { animateScrollToTop, scrollLogToTop } from '../../resources/js/lib/log-scroll.ts';

const originals = new Map(['window', 'requestAnimationFrame', 'cancelAnimationFrame'].map((name) => [name, Object.getOwnPropertyDescriptor(globalThis, name)]));
afterEach(() => {
    for (const [name, descriptor] of originals) {
        if (descriptor) Object.defineProperty(globalThis, name, descriptor);
        else Reflect.deleteProperty(globalThis, name);
    }
});

function setup(top = 1000, reduced = false) {
    const target = new EventTarget();
    const positions: number[] = [];
    const frames = new Map<number, FrameRequestCallback>();
    let nextId = 0;
    Object.assign(target, {
        scrollY: top,
        matchMedia: () => ({ matches: reduced }),
        scrollTo: ({ top: position, behavior }: ScrollToOptions) => {
            assert.equal(behavior, 'instant');
            positions.push(position!);
            Object.assign(target, { scrollY: position });
        },
    });
    Object.defineProperty(globalThis, 'window', { configurable: true, value: target });
    globalThis.requestAnimationFrame = (callback) => {
        frames.set(++nextId, callback);
        return nextId;
    };
    globalThis.cancelAnimationFrame = (id) => { frames.delete(id); };
    const started = performance.now();
    return {
        target, positions, frames,
        tick: (elapsed: number) => {
            const callbacks = [...frames.values()];
            frames.clear();
            for (const callback of callbacks) callback(started + elapsed);
        },
    };
}

void test('scrolls quickly from depth and progressively slows to exactly zero', () => {
    const deep = setup(10_000);
    animateScrollToTop();
    for (const elapsed of [100, 200, 300, 400, 500, 600, 700, 800, 900]) deep.tick(elapsed);
    assert.equal(deep.positions.at(-1), 0);
    const distances = deep.positions.map((position, index) => (index ? deep.positions[index - 1] : 10_000) - position);
    for (let i = 1; i < distances.length; i++) assert.ok(distances[i] <= distances[i - 1]);
    assert.equal(deep.frames.size, 0);
    const shallow = setup(400);
    animateScrollToTop();
    shallow.tick(100);
    assert.ok(distances[0] > 400 - shallow.positions[0]);
    shallow.tick(400);
    assert.equal(shallow.positions.at(-1), 0);
});

void test('reduced motion and an already reached top do not animate', () => {
    for (const [top, reduced] of [[1000, true], [0, false]] as const) {
        const state = setup(top, reduced);
        animateScrollToTop()();
        assert.deepEqual(state.positions, [0]);
        assert.equal(state.frames.size, 0);
    }
});

for (const name of ['wheel', 'touchstart', 'pointerdown', 'keydown', 'cleanup']) {
    void test(`stops scrolling on ${name}`, () => {
        const state = setup();
        const cancel = animateScrollToTop();
        state.tick(100);
        if (name === 'cleanup') cancel();
        else {
            const event = new Event(name);
            if (name === 'keydown') Object.defineProperty(event, 'key', { value: 'PageDown' });
            state.target.dispatchEvent(event);
        }
        const previous = [...state.positions];
        state.tick(1000);
        assert.deepEqual(state.positions, previous);
        assert.equal(state.frames.size, 0);
    });
}

void test('unrelated typing does not interrupt the return to top', () => {
    const state = setup();
    animateScrollToTop();
    const event = new Event('keydown');
    Object.defineProperty(event, 'key', { value: 'a' });
    state.target.dispatchEvent(event);
    state.tick(1000);
    assert.equal(state.positions.at(-1), 0);
});

void test('ordinary activation requests a local scroll and cancels navigation', () => {
    const state = setup();
    let requests = 0;
    state.target.addEventListener('log:scroll-top', () => requests++);
    let prevented = false;
    scrollLogToTop({ button: 0, preventDefault: () => { prevented = true; } } as unknown as MouseEvent);
    assert.equal(prevented, true);
    assert.equal(requests, 1);
    scrollLogToTop();
    assert.equal(requests, 2);
});

void test('modified, secondary and already prevented activations keep native link behavior', () => {
    const state = setup();
    let requests = 0;
    state.target.addEventListener('log:scroll-top', () => requests++);
    for (const extra of [{ metaKey: true }, { ctrlKey: true }, { shiftKey: true }, { altKey: true }, { button: 1 }, { defaultPrevented: true }]) {
        scrollLogToTop({ button: 0, preventDefault: () => assert.fail('must not intercept'), ...extra } as unknown as MouseEvent);
    }
    assert.equal(requests, 0);
});
