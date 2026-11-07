/// <reference types="node" />
import assert from 'node:assert/strict';
import * as React from 'react';
import { afterEach, test, vi } from 'vite-plus/test';

const notify = vi.fn();
let unsubscribe: () => void;
vi.doMock('react', () => ({
    ...React,
    useSyncExternalStore: (
        subscribe: (listener: () => void) => () => void,
        snapshot: () => unknown,
    ) => {
        unsubscribe = subscribe(notify);
        return snapshot();
    },
}));

afterEach(() => {
    unsubscribe?.();
    vi.unstubAllGlobals();
});

test('clears the dock when another tab wipes local storage', async () => {
    const values = new Map<string, string>();
    const listeners = new Map<
        string,
        (event: { key: string | null; storageArea: object | null }) => void
    >();
    const localStorage = {
        getItem: (key: string) => values.get(key) ?? null,
        setItem: (key: string, value: string) => values.set(key, value),
        removeItem: (key: string) => values.delete(key),
    };
    values.set('chat-dock', JSON.stringify({
        isDocked: true,
        isExpanded: true,
        conversation: 'alice',
    }));
    vi.stubGlobal('window', {
        localStorage,
        addEventListener: (event: string, callback: (event: { key: string | null; storageArea: object | null }) => void) =>
            listeners.set(event, callback),
        removeEventListener: (event: string) => listeners.delete(event),
    });
    const { useChatDock } = await import('../../resources/js/lib/chat-dock');

    assert.equal(useChatDock().isDocked, true);
    const onStorage = listeners.get('storage');
    onStorage?.({ key: null, storageArea: {} });
    assert.equal(notify.mock.calls.length, 0);
    assert.equal(useChatDock().isDocked, true);

    values.clear();
    onStorage?.({ key: null, storageArea: localStorage });

    assert.equal(notify.mock.calls.length, 1);
    assert.deepEqual(useChatDock(), {
        isDocked: false,
        isExpanded: false,
        conversation: null,
    });
    unsubscribe();
    assert.equal(listeners.has('storage'), false);
});
