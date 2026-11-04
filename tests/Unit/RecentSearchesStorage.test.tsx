/// <reference types="node" />
import assert from 'node:assert/strict';
import * as React from 'react';
import { afterEach, test, vi } from 'vite-plus/test';

const notifications: ReturnType<typeof vi.fn>[] = [];
const unsubscribers: (() => void)[] = [];
const subscriptions: ((listener: () => void) => () => void)[] = [];
let previousCallback: ((listener: () => void) => () => void) | undefined;
let previousUserId: string | null | undefined;
vi.doMock('react', () => ({
    ...React,
    useCallback: (
        callback: (listener: () => void) => () => void,
        dependencies: unknown[],
    ) => {
        const [userId] = dependencies as [string | null];
        if (previousCallback === undefined || previousUserId !== userId) {
            previousCallback = callback;
            previousUserId = userId;
        }

        return previousCallback;
    },
    useSyncExternalStore: (
        subscribe: (callback: () => void) => () => void,
        snapshot: () => string,
    ) => {
        const previousSubscription = subscriptions.at(-1);
        subscriptions.push(subscribe);
        if (previousSubscription !== subscribe) {
            const notify = vi.fn();
            notifications.push(notify);
            unsubscribers.push(subscribe(notify));
        }
        return snapshot();
    },
}));
afterEach(() => {
    unsubscribers.splice(0).forEach((unsubscribe) => unsubscribe());
    notifications.splice(0);
    subscriptions.splice(0);
    previousCallback = undefined;
    previousUserId = undefined;
    vi.unstubAllGlobals();
});

test('ignores unrelated and other-account changes but reacts to storage clears', async () => {
    const handlers = new Set<(event: { key: string | null }) => void>();
    const storage = new Map<string, string>();
    vi.stubGlobal('window', {
        localStorage: {
            getItem: (key: string) => storage.get(key) ?? null,
            setItem: (key: string, value: string) => storage.set(key, value),
            removeItem: (key: string) => storage.delete(key),
        },
        addEventListener: (
            _type: string,
            listener: (event: { key: string | null }) => void,
        ) => handlers.add(listener),
        removeEventListener: (
            _type: string,
            listener: (event: { key: string | null }) => void,
        ) => handlers.delete(listener),
    });
    const { rememberSearch, useRecentSearches } =
        await import('../../resources/js/hooks/use-recent-searches');
    assert.deepEqual(useRecentSearches('user-a'), []);
    assert.deepEqual(useRecentSearches('user-a'), []);
    assert.equal(subscriptions[0], subscriptions[1]);
    assert.deepEqual(useRecentSearches('user-b'), []);

    const dispatch = (key: string | null) =>
        handlers.forEach((listener) => listener({ key }));
    dispatch('chat-dock');
    dispatch('voice-note-volume');
    dispatch('recent-searches:other-user');
    assert.deepEqual(
        notifications.map(({ mock }) => mock.calls.length),
        [0, 0],
    );

    dispatch('recent-searches:user-a');
    assert.deepEqual(
        notifications.map(({ mock }) => mock.calls.length),
        [1, 0],
    );

    dispatch('recent-searches:user-b');
    assert.deepEqual(
        notifications.map(({ mock }) => mock.calls.length),
        [1, 1],
    );

    dispatch(null);
    assert.deepEqual(
        notifications.map(({ mock }) => mock.calls.length),
        [2, 2],
    );

    rememberSearch('user-a', 'term');
    assert.deepEqual(
        notifications.map(({ mock }) => mock.calls.length),
        [3, 2],
    );

    unsubscribers.splice(0).forEach((unsubscribe) => unsubscribe());
    assert.equal(handlers.size, 0);
});
