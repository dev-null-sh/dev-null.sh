/// <reference types="node" />
import assert from 'node:assert/strict';
import * as React from 'react';
import { afterAll, afterEach, test, vi } from 'vite-plus/test';

type Subscribe = (listener: () => void) => () => void;

const unsubscribes: (() => void)[] = [];
const listener = vi.fn();
let getServerSnapshot: (() => number) | undefined;

vi.doMock('react', () => ({
    ...React,
    useSyncExternalStore: (
        subscribe: Subscribe,
        getSnapshot: () => number,
        serverSnapshot: () => number,
    ) => {
        unsubscribes.push(subscribe(listener));
        getServerSnapshot = serverSnapshot;

        return getSnapshot();
    },
}));

afterEach(() => {
    while (unsubscribes.length) unsubscribes.pop()?.();
    listener.mockClear();
    vi.unstubAllGlobals();
});

afterAll(() => {
    vi.useRealTimers();
    vi.doUnmock('react');
});

test('reads the clock again when a component subscribes after a pause', async () => {
    vi.useFakeTimers();
    const start = new Date('2026-10-18T12:00:00Z').getTime();
    vi.setSystemTime(start);
    const { useNow } = await import('../../resources/js/hooks/use-now');

    assert.equal(useNow().getTime(), start);

    unsubscribes.shift()?.();
    vi.setSystemTime(start + 10 * 60_000);

    assert.equal(useNow().getTime(), start + 10 * 60_000);

    vi.advanceTimersByTime(30_000);

    assert.equal(listener.mock.calls.length, 1);
    assert.equal(useNow().getTime(), start + 10 * 60_000 + 30_000);
});

test('counts from the moment the server rendered the page, on the server and while hydrating', async () => {
    const { useNow, setRenderedAt } =
        await import('../../resources/js/hooks/use-now');
    const renderedAt = new Date('2026-10-24T12:00:00Z').getTime();

    setRenderedAt(renderedAt);
    useNow();

    assert.equal(getServerSnapshot?.(), renderedAt);
});

test('does no timer work while hidden and refreshes immediately when visible', async () => {
    vi.useFakeTimers();
    const document = Object.assign(new EventTarget(), { hidden: false });
    vi.stubGlobal('document', document);
    const start = new Date('2026-10-27T12:00:00Z').getTime();
    vi.setSystemTime(start);
    const { useNow } = await import('../../resources/js/hooks/use-now');
    useNow();
    assert.equal(vi.getTimerCount(), 1);

    document.hidden = true;
    document.dispatchEvent(new Event('visibilitychange'));
    assert.equal(vi.getTimerCount(), 0);
    vi.advanceTimersByTime(60 * 60_000);
    assert.equal(listener.mock.calls.length, 0);

    document.hidden = false;
    document.dispatchEvent(new Event('visibilitychange'));
    assert.equal(listener.mock.calls.length, 1);
    assert.equal(useNow().getTime(), start + 60 * 60_000);
    assert.equal(vi.getTimerCount(), 1);
    vi.advanceTimersByTime(30_000);
    assert.equal(listener.mock.calls.length, 2);
});

test('subscribing in a hidden tab creates no timer and cleans up on the last unsubscribe', async () => {
    vi.useFakeTimers();
    const document = Object.assign(new EventTarget(), { hidden: true });
    vi.stubGlobal('document', document);
    const { useNow } = await import('../../resources/js/hooks/use-now');
    useNow();
    useNow();
    assert.equal(vi.getTimerCount(), 0);
    while (unsubscribes.length) unsubscribes.pop()?.();
    document.hidden = false;
    document.dispatchEvent(new Event('visibilitychange'));
    assert.equal(listener.mock.calls.length, 0);
    assert.equal(vi.getTimerCount(), 0);
});
