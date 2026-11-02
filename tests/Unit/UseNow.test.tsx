/// <reference types="node" />
import assert from 'node:assert/strict';
import * as React from 'react';
import { afterAll, test, vi } from 'vite-plus/test';

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
