/// <reference types="node" />
import assert from 'node:assert/strict';
import * as React from 'react';
import { test, vi } from 'vite-plus/test';

const bodies = new Map<string, string>([
    ['stash:author:commit', 'First part'],
    ['stash:author:commit:later', JSON.stringify(['Second part'])],
]);
const image = new File(['image'], 'draft.png', { type: 'image/png' });
const storedImages = new Map<string, File[]>([
    ['stash:author:commit', [image]],
    ['stash:author:reply-parent', [image]],
    ['stash:another:commit', [image]],
]);

let state: unknown[] = [];
let cursor = 0;

vi.doMock('react', () => ({
    ...React,
    useState: (initial: unknown) => {
        const index = cursor++;

        if (!(index in state)) {
            state[index] = typeof initial === 'function' ? initial() : initial;
        }

        const current = state;

        return [state[index], (value: unknown) => (current[index] = value)];
    },
    useSyncExternalStore: (
        subscribe: (callback: () => void) => () => void,
        snapshot: () => unknown,
    ) => {
        const unsubscribe = subscribe(() => {});
        unsubscribe();

        return snapshot();
    },
}));

vi.doMock('../../resources/js/lib/storage', () => ({
    readStorage: (key: string) => bodies.get(key) ?? '',
    writeStorage: (key: string, body: string) => {
        if (body === '') {
            bodies.delete(key);
        } else {
            bodies.set(key, body);
        }
    },
    storedKeys: () => [...bodies.keys()],
}));

vi.doMock('../../resources/js/lib/image-storage', () => ({
    readStoredImages: async () => new Map(storedImages),
    writeStoredImages: (key: string, images: File[]) => {
        if (images.length === 0) {
            storedImages.delete(key);
        } else {
            storedImages.set(key, images);
        }
    },
    deleteStoredImages: (prefix: string) => {
        for (const key of storedImages.keys()) {
            if (key.startsWith(prefix)) {
                storedImages.delete(key);
            }
        }
    },
}));

const { clearStashes, useHasStash, useSeriesStash, useStash } =
    await import('../../resources/js/hooks/use-stash');

test('restores series images, preserves them through part edits, and drops the complete draft', async () => {
    const windowBefore = globalThis.window;
    globalThis.window = { addEventListener() {} } as unknown as Window &
        typeof globalThis;

    const render = () => {
        cursor = 0;

        return useSeriesStash('author', 'commit');
    };

    try {
        render();
        await Promise.resolve();

        let draft = render();
        assert.deepEqual(draft.bodies, ['First part', 'Second part']);
        assert.deepEqual(draft.images, [image]);
        assert.equal(draft.isRestored, true);

        draft.insertPart(1);
        draft = render();
        draft.updateBody(1, 'Inserted part');
        draft = render();
        draft.removePart(0);
        draft = render();

        assert.deepEqual(draft.bodies, ['Inserted part', 'Second part']);
        assert.deepEqual(draft.images, [image]);

        state = [];
        draft = render();
        assert.deepEqual(draft.bodies, ['Inserted part', 'Second part']);
        assert.deepEqual(draft.images, [image]);

        draft.updateBody(0, '');
        draft = render();
        draft.removePart(1);
        draft = render();
        assert.equal(draft.isRestored, true);

        draft.updateImages([]);
        draft = render();
        assert.equal(draft.isRestored, false);
        assert.deepEqual(draft.images, []);

        draft.updateImages([image]);
        assert.deepEqual(storedImages.get('stash:author:commit'), [image]);
        draft = render();
        draft.drop();
        draft = render();

        assert.deepEqual(draft.bodies, ['']);
        assert.deepEqual(draft.images, []);
        assert.equal(draft.isRestored, false);
        assert.equal(storedImages.has('stash:author:commit'), false);
        assert.equal(useHasStash('author', 'commit'), false);

        state = [];
        cursor = 0;
        const reply = useStash('author', 'reply-parent');
        assert.deepEqual(reply.images, [image]);
        assert.equal(reply.isRestored, true);
        assert.equal(useHasStash('author', 'reply-parent'), true);
        assert.equal(useHasStash('another', 'commit'), true);
        assert.equal(useHasStash(null, 'reply-parent'), false);

        clearStashes('author');
        assert.equal(useHasStash('author', 'reply-parent'), false);
        assert.equal(storedImages.has('stash:author:reply-parent'), false);
        assert.equal(useHasStash('another', 'commit'), true);
    } finally {
        clearStashes('author');
        clearStashes('another');
        globalThis.window = windowBefore;
    }
});
