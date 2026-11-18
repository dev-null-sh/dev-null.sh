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
    const byQuery = new Map<string, Set<() => void>>();
    const matchMedia = vi.fn((query: string) => {
        const listeners = new Set<() => void>();
        byQuery.set(query, listeners);

        return {
            matches: false,
            addEventListener: (type: string, listener: () => void) => {
                assert.equal(type, 'change');
                listeners.add(listener);
            },
            removeEventListener: (type: string, listener: () => void) => {
                assert.equal(type, 'change');
                listeners.delete(listener);
            },
        };
    });
    vi.stubGlobal('window', { matchMedia });
    const { useMediaQuery } = await import(
        '../../resources/js/hooks/use-media-query'
    );

    assert.equal(useMediaQuery('(min-width: 800px)'), false);
    assert.equal(useMediaQuery('(min-width: 800px)'), false);
    assert.equal(matchMedia.mock.calls.length, 1);
    assert.equal(calls[0].subscribe, calls[1].subscribe);
    assert.equal(calls[0].snapshot, calls[1].snapshot);

    assert.equal(useMediaQuery('(max-width: 600px)'), false);
    assert.equal(matchMedia.mock.calls.length, 2);
    assert.notEqual(calls[0].subscribe, calls[2].subscribe);

    const onChange = vi.fn();
    const onOtherChange = vi.fn();
    const unsubscribe = calls[0].subscribe(onChange);
    const unsubscribeOther = calls[2].subscribe(onOtherChange);
    assert.equal(byQuery.get('(min-width: 800px)')?.has(onChange), true);
    assert.equal(byQuery.get('(max-width: 600px)')?.has(onOtherChange), true);
    unsubscribe();
    assert.equal(byQuery.get('(min-width: 800px)')?.has(onChange), false);
    assert.equal(byQuery.get('(max-width: 600px)')?.has(onOtherChange), true);
    unsubscribeOther();
});
