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
        snapshot: () => number | null,
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

test('pauses the image loading animation offscreen and resumes on return', async () => {
    vi.useFakeTimers();
    vi.stubGlobal('window', {
        matchMedia: () => ({ matches: false }),
    });
    const events = new Map<string, () => void>();
    const document = {
        visibilityState: 'visible',
        addEventListener: (event: string, handler: () => void) => events.set(event, handler),
        removeEventListener: (event: string) => events.delete(event),
    };
    vi.stubGlobal('document', document);
    const { useScrambledHash } = await import(
        '../../resources/js/hooks/use-scrambled-hash'
    );

    assert.equal(useScrambledHash('abcd').length, 4);
    vi.advanceTimersByTime(140);
    assert.equal(notify.mock.calls.length, 2);

    document.visibilityState = 'hidden';
    events.get('visibilitychange')?.();
    vi.advanceTimersByTime(700);
    assert.equal(notify.mock.calls.length, 2);

    document.visibilityState = 'visible';
    events.get('visibilitychange')?.();
    vi.advanceTimersByTime(70);
    assert.equal(notify.mock.calls.length, 3);

    unsubscribe();
    assert.equal(events.has('visibilitychange'), false);
});
