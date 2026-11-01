/// <reference types="node" />
import assert from 'node:assert/strict';
import * as React from 'react';
import { afterEach, beforeEach, test, vi } from 'vite-plus/test';

type Row = { dataset: Record<string, string | undefined> } & {
    removeAttribute: (name: string) => void;
};

let observerCallback: (
    entries: { target: Row; isIntersecting: boolean }[],
) => void = () => undefined;
const listeners = new Map<string, () => void>();

vi.doMock('react', () => ({
    ...React,
    useLayoutEffect: (effect: () => void) => effect(),
}));

const { useFreshRows } =
    await import('../../resources/js/hooks/use-fresh-rows');

function row(id: string): Row {
    const dataset: Record<string, string | undefined> = { group: id };

    return {
        dataset,
        removeAttribute: () => {
            delete dataset.fresh;
        },
    };
}

function light(rows: Row[], ids: string[]): { current: Set<string> } {
    const freshIds = { current: new Set(ids) };
    useFreshRows(
        { current: { querySelectorAll: () => rows } as unknown as HTMLElement },
        'group',
        freshIds,
        rows,
    );

    return freshIds;
}

function see(...rows: Row[]): void {
    observerCallback(rows.map((target) => ({ target, isIntersecting: true })));
}

beforeEach(() => {
    vi.useFakeTimers();
    vi.stubGlobal(
        'IntersectionObserver',
        class {
            constructor(callback: typeof observerCallback) {
                observerCallback = callback;
            }
            observe() {}
            disconnect() {}
        },
    );
    vi.stubGlobal('window', {
        addEventListener: (name: string, listener: () => void) =>
            listeners.set(name, listener),
        removeEventListener: (name: string) => listeners.delete(name),
    });
});

afterEach(() => {
    listeners.clear();
    vi.useRealTimers();
    vi.unstubAllGlobals();
});

test('lights the fresh rows only', () => {
    const fresh = row('new');
    const seen = row('old');
    light([fresh, seen], ['new']);

    assert.equal(fresh.dataset.fresh, '');
    assert.equal(seen.dataset.fresh, undefined);
});

test('keeps a row further down lit after the rows on screen fade', () => {
    const top = row('top');
    const below = row('below');
    const freshIds = light([top, below], ['top', 'below']);

    see(top);
    vi.advanceTimersByTime(7000);

    assert.equal(top.dataset.fresh, 'fading');
    assert.equal(below.dataset.fresh, '');
    assert.deepEqual([...freshIds.current], ['below']);

    see(below);
    vi.advanceTimersByTime(7000);
    vi.advanceTimersByTime(1400);

    assert.equal(top.dataset.fresh, undefined);
    assert.equal(below.dataset.fresh, undefined);
    assert.equal(freshIds.current.size, 0);
    assert.equal(listeners.size, 0);
});

test('moving the pointer only hurries the rows already seen', () => {
    const top = row('top');
    const below = row('below');
    light([top, below], ['top', 'below']);

    see(top);
    vi.advanceTimersByTime(700);
    listeners.get('pointermove')!();
    vi.advanceTimersByTime(1800);

    assert.equal(top.dataset.fresh, 'fading');
    assert.equal(below.dataset.fresh, '');
});

test('a row stays lit for a moment after it is seen, however the pointer moves', () => {
    const top = row('top');
    light([top], ['top']);

    see(top);
    listeners.get('pointermove')!();
    vi.advanceTimersByTime(6999);

    assert.equal(top.dataset.fresh, '');
});

test('input after a row faded on its own still waits for the rows below', () => {
    const top = row('top');
    const below = row('below');
    light([top, below], ['top', 'below']);

    see(top);
    vi.advanceTimersByTime(7000);
    listeners.get('pointermove')!();
    vi.advanceTimersByTime(1800);

    assert.equal(below.dataset.fresh, '');
    assert.equal(listeners.size, 3);

    see(below);
    vi.advanceTimersByTime(7000);

    assert.equal(below.dataset.fresh, 'fading');
    assert.equal(listeners.size, 0);
});
