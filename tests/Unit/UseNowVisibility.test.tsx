/// <reference types="node" />
import assert from 'node:assert/strict';
import * as React from 'react';
import { afterEach, test, vi } from 'vite-plus/test';

const notify = vi.fn();
let unsubscribe: () => void;
vi.doMock('react', () => ({
    ...React,
    useSyncExternalStore: (
        subscribe: (callback: () => void) => () => void,
        snapshot: () => number,
    ) => {
        unsubscribe = subscribe(notify);
        return snapshot();
    },
}));

afterEach(() => {
    unsubscribe?.();
    vi.useRealTimers();
    vi.unstubAllGlobals();
});

test('stops the shared clock in background tabs and refreshes on return', async () => {
    vi.useFakeTimers();
    const start = new Date('2026-10-25T12:00:00Z').getTime();
    vi.setSystemTime(start);
    const events = new Map<string, () => void>();
    const document = {
        visibilityState: 'visible',
        addEventListener: (event: string, handler: () => void) => events.set(event, handler),
        removeEventListener: (event: string) => events.delete(event),
    };
    vi.stubGlobal('document', document);

    const { useNow } = await import('../../resources/js/hooks/use-now');
    assert.equal(useNow().getTime(), start);
    assert.equal(events.has('visibilitychange'), true);

    vi.advanceTimersByTime(30_000);
    assert.equal(notify.mock.calls.length, 1);

    document.visibilityState = 'hidden';
    events.get('visibilitychange')?.();
    vi.advanceTimersByTime(90_000);
    assert.equal(notify.mock.calls.length, 1);

    document.visibilityState = 'visible';
    events.get('visibilitychange')?.();
    assert.equal(notify.mock.calls.length, 2);
    assert.equal(useNow().getTime(), start + 120_000);

    vi.advanceTimersByTime(30_000);
    assert.equal(notify.mock.calls.length, 3);

    unsubscribe();
    assert.equal(events.has('visibilitychange'), false);
});
