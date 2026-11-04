import assert from 'node:assert/strict';
import { test } from 'node:test';

void test('retries transient open failures, shares attempts, and reuses success', async () => {
    const originalWindow = Object.getOwnPropertyDescriptor(
        globalThis,
        'window',
    );
    let attempts = 0;
    let transactions = 0;

    const connection = {
        transaction: () => {
            transactions++;
            const cursor = {
                result: null,
                onsuccess: null as (() => void) | null,
            };
            const transaction = {
                oncomplete: null as (() => void) | null,
                onabort: null as (() => void) | null,
                objectStore: () => ({ openCursor: () => cursor }),
            };
            queueMicrotask(() => {
                cursor.onsuccess?.();
                transaction.oncomplete?.();
            });

            return transaction;
        },
    } as unknown as IDBDatabase;

    try {
        Object.defineProperty(globalThis, 'window', {
            configurable: true,
            value: {
                indexedDB: {
                    open: () => {
                        attempts++;
                        if (attempts === 1) {
                            throw new Error('Temporarily unavailable');
                        }

                        if (attempts === 2) {
                            const request = {
                                error: new DOMException(
                                    'Try again',
                                    'UnknownError',
                                ),
                                result: null,
                                onsuccess: null as (() => void) | null,
                                onerror: null as (() => void) | null,
                                onupgradeneeded: null as (() => void) | null,
                            };
                            queueMicrotask(() => request.onerror?.());

                            return request;
                        }

                        const request = {
                            result: connection,
                            onsuccess: null as (() => void) | null,
                            onerror: null as (() => void) | null,
                            onupgradeneeded: null as (() => void) | null,
                        };
                        queueMicrotask(() => request.onsuccess?.());

                        return request;
                    },
                },
            },
        });

        const transientModule = await import(
            new URL('../../resources/js/lib/image-storage.ts?transient', import.meta.url).href,
        );
        const { readStoredImages } = transientModule;
        const failed = await Promise.all([
            readStoredImages(),
            readStoredImages(),
        ]);
        assert.deepEqual(failed, [null, null]);
        assert.equal(attempts, 1);

        const asynchronouslyFailed = await Promise.all([
            readStoredImages(),
            readStoredImages(),
        ]);
        assert.deepEqual(asynchronouslyFailed, [null, null]);
        assert.equal(attempts, 2);

        const recovered = await Promise.all([
            readStoredImages(),
            readStoredImages(),
        ]);
        assert.deepEqual(
            recovered.map((images) => images.size),
            [0, 0],
        );
        assert.equal(attempts, 3);
        assert.equal(transactions, 2);
    } finally {
        if (originalWindow === undefined) {
            Reflect.deleteProperty(globalThis, 'window');
        } else {
            Object.defineProperty(globalThis, 'window', originalWindow);
        }
    }
});

void test('does not retry a synchronous IndexedDB permission denial', async () => {
    const originalWindow = Object.getOwnPropertyDescriptor(
        globalThis,
        'window',
    );
    let attempts = 0;

    try {
        Object.defineProperty(globalThis, 'window', {
            configurable: true,
            value: {
                indexedDB: {
                    open: () => {
                        attempts++;
                        throw new DOMException('Blocked', 'SecurityError');
                    },
                },
            },
        });

        const deniedModule = await import(
            new URL('../../resources/js/lib/image-storage.ts?sync-denied', import.meta.url).href,
        );
        const { readStoredImages } = deniedModule;
        assert.equal(await readStoredImages(), null);
        assert.equal(await readStoredImages(), null);
        assert.equal(attempts, 1);
    } finally {
        if (originalWindow === undefined) {
            Reflect.deleteProperty(globalThis, 'window');
        } else {
            Object.defineProperty(globalThis, 'window', originalWindow);
        }
    }
});

void test('does not retry an IndexedDB open after permission is denied', async () => {
    const originalWindow = Object.getOwnPropertyDescriptor(
        globalThis,
        'window',
    );
    let attempts = 0;

    try {
        Object.defineProperty(globalThis, 'window', {
            configurable: true,
            value: {
                indexedDB: {
                    open: () => {
                        attempts++;
                        const request = {
                            error: new DOMException('Blocked', 'SecurityError'),
                            result: null,
                            onsuccess: null as (() => void) | null,
                            onerror: null as (() => void) | null,
                            onupgradeneeded: null as (() => void) | null,
                        };
                        queueMicrotask(() => request.onerror?.());

                        return request;
                    },
                },
            },
        });

        const deniedModule = await import(
            new URL('../../resources/js/lib/image-storage.ts?denied', import.meta.url).href,
        );
        const { readStoredImages } = deniedModule;
        assert.equal(await readStoredImages(), null);
        assert.equal(await readStoredImages(), null);
        assert.equal(attempts, 1);
    } finally {
        if (originalWindow === undefined) {
            Reflect.deleteProperty(globalThis, 'window');
        } else {
            Object.defineProperty(globalThis, 'window', originalWindow);
        }
    }
});
