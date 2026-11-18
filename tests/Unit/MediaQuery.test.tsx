/// <reference types="node" />
import assert from 'node:assert/strict';
import * as React from 'react';
import { afterEach, test, vi } from 'vite-plus/test';

type Subscribe = (listener: () => void) => () => void;
const calls: { subscribe: Subscribe; snapshot: () => boolean }[] = [];

vi.doMock('react', () => ({
    ...React,
    useSyncExternalStore: (subscribe: Subscribe, snapshot: () => boolean) => {
        calls.push({ subscribe, snapshot });
        return snapshot();
    },
}));

afterEach(() => {
    vi.unstubAllGlobals();
    calls.length = 0;
});

test('uses one media query and stable subscriptions across renders', async () => {
    const listeners = new Set<() => void>();
    const matchMedia = vi.fn(() => ({
        matches: false,
        addEventListener: (_type: string, listener: () => void) =>
            listeners.add(listener),
        removeEventListener: (_type: string, listener: () => void) =>
            listeners.delete(listener),
    }));
    vi.stubGlobal('window', { matchMedia });
    const { useMediaQuery } = await import(
        '../../resources/js/hooks/use-media-query'
    );

    assert.equal(useMediaQuery('(min-width: 800px)'), false);
    assert.equal(useMediaQuery('(min-width: 800px)'), false);
    assert.equal(matchMedia.mock.calls.length, 1);
    assert.equal(calls[0].subscribe, calls[1].subscribe);
    assert.equal(calls[0].snapshot, calls[1].snapshot);

    const onChange = vi.fn();
    const unsubscribe = calls[0].subscribe(onChange);
    assert.equal(listeners.has(onChange), true);
    unsubscribe();
    assert.equal(listeners.has(onChange), false);
});
