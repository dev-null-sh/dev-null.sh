import assert from 'node:assert/strict';
import { afterEach, beforeEach, test } from 'node:test';
import type { GifAttachment } from '../../resources/js/lib/gif-providers.ts';
import {
    clearGifProviderKey,
    readGifProviderKey,
    readStoredGif,
    subscribeGifProviderKey,
    writeGifProviderKey,
    writeStoredGif,
} from '../../resources/js/lib/gif-provider-key.ts';

const gif: GifAttachment = {
    provider: 'giphy',
    id: 'xT9IgzoKnwFNmISR8I',
    url: 'https://media2.giphy.com/media/xT9IgzoKnwFNmISR8I/giphy-downsized-medium.gif?cid=abc123',
    pageUrl: 'https://giphy.com/gifs/deal-with-it-xT9IgzoKnwFNmISR8I',
    title: 'Deal With It GIF',
    username: 'giphy',
    width: 480,
    height: 270,
    previewUrl:
        'https://media2.giphy.com/media/xT9IgzoKnwFNmISR8I/100.gif?cid=abc123',
};

let store: Map<string, string>;
let storageHandlers: Array<(event: StorageEvent) => void>;
const originalWindow = globalThis.window;

function installWindow(): void {
    store = new Map();
    storageHandlers = [];

    globalThis.window = {
        localStorage: {
            getItem: (key: string) => store.get(key) ?? null,
            setItem: (key: string, value: string) => store.set(key, value),
            removeItem: (key: string) => store.delete(key),
            key: (index: number) => [...store.keys()][index] ?? null,
            get length() {
                return store.size;
            },
        },
        addEventListener: (_type: string, handler: (event: StorageEvent) => void) => {
            storageHandlers.push(handler);
        },
        removeEventListener: (_type: string, handler: (event: StorageEvent) => void) => {
            storageHandlers = storageHandlers.filter((candidate) => candidate !== handler);
        },
    } as unknown as Window & typeof globalThis;
}

beforeEach(installWindow);

afterEach(() => {
    globalThis.window = originalWindow;
});

test('round-trips a key and keeps a stable reference while unchanged', () => {
    writeGifProviderKey('user-1', { provider: 'klipy', key: 'k-123' });

    const first = readGifProviderKey('user-1');
    const second = readGifProviderKey('user-1');

    assert.deepEqual(first, { provider: 'klipy', key: 'k-123' });
    assert.equal(first, second);
});

test('namespaces keys per user', () => {
    writeGifProviderKey('user-1', { provider: 'giphy', key: 'one' });
    writeGifProviderKey('user-2', { provider: 'klipy', key: 'two' });

    assert.deepEqual(readGifProviderKey('user-1'), { provider: 'giphy', key: 'one' });
    assert.deepEqual(readGifProviderKey('user-2'), { provider: 'klipy', key: 'two' });
    assert.equal(readGifProviderKey('user-3'), null);
});

test('treats malformed values as absent', () => {
    store.set('gif-provider:user-1', '{not json');
    assert.equal(readGifProviderKey('user-1'), null);

    store.set('gif-provider:user-1', JSON.stringify({ provider: 'unknown', key: 'x' }));
    assert.equal(readGifProviderKey('user-1'), null);

    store.set('gif-provider:user-1', JSON.stringify({ provider: 'toString', key: 'x' }));
    assert.equal(readGifProviderKey('user-1'), null);

    store.set('gif-provider:user-1', JSON.stringify({ provider: '__proto__', key: 'x' }));
    assert.equal(readGifProviderKey('user-1'), null);

    store.set('gif-provider:user-1', JSON.stringify({ provider: 'giphy', key: '   ' }));
    assert.equal(readGifProviderKey('user-1'), null);

    store.set('gif-provider:user-1', JSON.stringify({ provider: 'giphy', key: 'a'.repeat(256) }));
    assert.equal(readGifProviderKey('user-1'), null);

    store.set('gif-provider:user-1', JSON.stringify({ provider: 'giphy', key: 42 }));
    assert.equal(readGifProviderKey('user-1'), null);
});

test('writes the trimmed key and notifies same-tab subscribers', () => {
    const calls: string[] = [];
    const unsubscribe = subscribeGifProviderKey('user-1', () => calls.push('hit'));

    writeGifProviderKey('user-1', { provider: 'giphy', key: '  k-123  ' });

    assert.equal(store.get('gif-provider:user-1'), JSON.stringify({ provider: 'giphy', key: 'k-123' }));
    assert.deepEqual(calls, ['hit']);

    unsubscribe();
    writeGifProviderKey('user-1', { provider: 'giphy', key: 'k-456' });
    assert.deepEqual(calls, ['hit']);
});

test('clears the entry and notifies', () => {
    writeGifProviderKey('user-1', { provider: 'giphy', key: 'k-123' });

    const calls: string[] = [];
    const unsubscribe = subscribeGifProviderKey('user-1', () => calls.push('hit'));

    clearGifProviderKey('user-1');

    assert.equal(store.has('gif-provider:user-1'), false);
    assert.deepEqual(calls, ['hit']);
    assert.equal(readGifProviderKey('user-1'), null);

    unsubscribe();
});

test('refuses invalid keys and reports nothing was written', () => {
    assert.equal(
        writeGifProviderKey('user-1', { provider: 'giphy', key: '   ' }),
        false,
    );
    assert.equal(
        writeGifProviderKey('user-1', {
            provider: 'giphy',
            key: 'a'.repeat(256),
        }),
        false,
    );
    assert.equal(store.has('gif-provider:user-1'), false);
    assert.equal(readGifProviderKey('user-1'), null);
});

test('reports failure when storage swallows the write', () => {
    globalThis.window.localStorage.setItem = () => undefined;

    assert.equal(
        writeGifProviderKey('user-1', { provider: 'giphy', key: 'k-123' }),
        false,
    );
    assert.equal(readGifProviderKey('user-1'), null);
});

test('reacts to storage events for its own key only', () => {
    const calls: string[] = [];
    const unsubscribe = subscribeGifProviderKey('user-1', () => calls.push('hit'));

    assert.equal(storageHandlers.length, 1);

    storageHandlers[0]!({ key: 'gif-provider:user-2' } as StorageEvent);
    assert.deepEqual(calls, []);

    store.set('gif-provider:user-1', JSON.stringify({ provider: 'giphy', key: 'other-tab' }));
    storageHandlers[0]!({ key: 'gif-provider:user-1' } as StorageEvent);

    assert.deepEqual(calls, ['hit']);
    assert.deepEqual(readGifProviderKey('user-1'), { provider: 'giphy', key: 'other-tab' });

    unsubscribe();
    assert.equal(storageHandlers.length, 0);
});

test('reacts to a full storage clear from another tab', () => {
    writeGifProviderKey('user-1', { provider: 'giphy', key: 'k-123' });

    const calls: string[] = [];
    const unsubscribe = subscribeGifProviderKey('user-1', () => calls.push('hit'));

    store.clear();
    storageHandlers[0]!({ key: null } as unknown as StorageEvent);

    assert.deepEqual(calls, ['hit']);
    assert.equal(readGifProviderKey('user-1'), null);

    unsubscribe();
});

test('round-trips a stashed gif', () => {
    writeStoredGif('stash:u1:commit', gif);
    assert.deepEqual(readStoredGif('stash:u1:commit'), gif);
});

await test('clears a stashed gif', () => {
    writeStoredGif('stash:u1:commit', gif);
    writeStoredGif('stash:u1:commit', null);
    assert.equal(readStoredGif('stash:u1:commit'), null);
});

await test('ignores unreadable stashed values', () => {
    store.set('stash:u1:reply-1:gif', 'not json');
    assert.equal(readStoredGif('stash:u1:reply-1'), null);
    store.set('stash:u1:reply-2:gif', JSON.stringify({ nope: true }));
    assert.equal(readStoredGif('stash:u1:reply-2'), null);
});

await test('rejects a stashed gif with an unknown provider', () => {
    store.set(
        'stash:u1:reply-3:gif',
        JSON.stringify({ ...gif, provider: 'nope' }),
    );
    assert.equal(readStoredGif('stash:u1:reply-3'), null);
});
