import assert from 'node:assert/strict';
import { test } from 'vite-plus/test';
import {
    cancelImageRead,
    cancelImageReads,
    isReadingImages,
    readImagesForDraft,
    subscribeImageReads,
} from '../../resources/js/lib/draft-image-reads';

function unexpectedError(error: unknown): never {
    throw error;
}

function deferred<T>() {
    let resolve!: (value: T) => void;
    let reject!: (reason?: unknown) => void;
    const promise = new Promise<T>((res, rej) => {
        resolve = res;
        reject = rej;
    });

    return { promise, resolve, reject };
}

function deferredImage() {
    const gate = deferred<void>();
    const started = deferred<void>();
    const file = {
        name: 'photo.png',
        type: 'image/png',
        stream: () =>
            new ReadableStream<Uint8Array>({
                async pull(controller) {
                    started.resolve();
                    await gate.promise;
                    controller.enqueue(new Uint8Array([1]));
                    controller.close();
                },
            }),
    } as unknown as File;

    return {
        file,
        started: started.promise,
        resolve: gate.resolve,
        reject: gate.reject,
    };
}

test('marks a draft pending synchronously and clears it after its success callback', async () => {
    const key = 'draft-image-read:success';
    const image = deferredImage();
    const changes: boolean[] = [];
    const unsubscribe = subscribeImageReads(() =>
        changes.push(isReadingImages(key)),
    );
    let pendingDuringSuccess = false;

    const read = readImagesForDraft(key, [image.file], {
        onReady: () => {
            pendingDuringSuccess = isReadingImages(key);
        },
        onError: unexpectedError,
    });

    assert.equal(isReadingImages(key), true);
    await image.started;
    image.resolve();
    await read;

    assert.equal(pendingDuringSuccess, true);
    assert.equal(isReadingImages(key), false);
    assert.deepEqual(changes, [true, false]);
    unsubscribe();
});

test('cancelling a read ignores late outcomes without cancelling a newer read', async () => {
    const key = 'draft-image-read:replace';
    const owner = Symbol('composer');
    const stale = deferredImage();
    let ready = 0;
    let errors = 0;

    const first = readImagesForDraft(key, [stale.file], {
        onReady: () => ready++,
        onError: () => errors++,
        owner: owner,
    });
    await stale.started;
    cancelImageRead(key, owner);

    const fresh = readImagesForDraft(
        key,
        [new File(['fresh'], 'fresh.png', { type: 'image/png' })],
        {
            onReady: () => ready++,
            onError: () => errors++,
            owner: owner,
        },
    );
    stale.resolve();

    await Promise.all([first, fresh]);
    assert.equal(ready, 1);
    assert.equal(errors, 0);
    assert.equal(isReadingImages(key), false);
});

test('only the matching owner can cancel a draft image read', async () => {
    const key = 'draft-image-read:owner';
    const owner = Symbol('composer');
    const image = deferredImage();
    let ready = 0;

    const read = readImagesForDraft(key, [image.file], {
        onReady: () => ready++,
        onError: unexpectedError,
        owner: owner,
    });
    await image.started;

    cancelImageRead(key, Symbol('other-composer'));
    assert.equal(isReadingImages(key), true);

    cancelImageRead(key, owner);
    assert.equal(isReadingImages(key), false);
    image.resolve();
    await read;
    assert.equal(ready, 0);
});

test('cancelling a read ignores a late provider error', async () => {
    const key = 'draft-image-read:late-error';
    const image = deferredImage();
    let errors = 0;

    const read = readImagesForDraft(key, [image.file], {
        onReady: () =>
            assert.fail('Cancelled reads must not finish successfully'),
        onError: () => errors++,
    });
    await image.started;
    cancelImageRead(key);
    image.reject(new Error('Provider disconnected'));
    await read;

    assert.equal(errors, 0);
    assert.equal(isReadingImages(key), false);
});

test('reads for independent drafts do not block one another', async () => {
    const firstKey = 'draft-image-read:independent:first';
    const secondKey = 'draft-image-read:independent:second';
    const first = deferredImage();
    let ready = 0;

    const firstRead = readImagesForDraft(firstKey, [first.file], {
        onReady: () => ready++,
        onError: unexpectedError,
    });
    const secondRead = readImagesForDraft(
        secondKey,
        [new File(['second'], 'second.png', { type: 'image/png' })],
        {
            onReady: () => ready++,
            onError: unexpectedError,
        },
    );

    await first.started;
    await secondRead;
    assert.equal(isReadingImages(firstKey), true);
    assert.equal(isReadingImages(secondKey), false);

    first.resolve();
    await firstRead;
    assert.equal(ready, 2);
});

test('a second read for the same draft is ignored while the first is pending', async () => {
    const key = 'draft-image-read:dedupe';
    const image = deferredImage();
    let ready = 0;

    const first = readImagesForDraft(key, [image.file], {
        onReady: () => ready++,
        onError: unexpectedError,
    });
    const second = readImagesForDraft(
        key,
        [new File(['ignored'], 'ignored.png', { type: 'image/png' })],
        {
            onReady: () => ready++,
            onError: unexpectedError,
        },
    );

    await image.started;
    await second;
    assert.equal(ready, 0);
    assert.equal(isReadingImages(key), true);

    image.resolve();
    await first;
    assert.equal(ready, 1);
});

test('cancelling a key prefix releases every matching draft', async () => {
    const prefix = 'draft-image-read:logout:';
    const first = deferredImage();
    const second = deferredImage();
    const other = deferredImage();
    let ready = 0;

    const reads = [
        readImagesForDraft(`${prefix}one`, [first.file], {
            onReady: () => ready++,
            onError: unexpectedError,
        }),
        readImagesForDraft(`${prefix}two`, [second.file], {
            onReady: () => ready++,
            onError: unexpectedError,
        }),
        readImagesForDraft('draft-image-read:other', [other.file], {
            onReady: () => ready++,
            onError: unexpectedError,
        }),
    ];
    await Promise.all([first.started, second.started, other.started]);

    cancelImageReads(prefix);
    assert.equal(isReadingImages(`${prefix}one`), false);
    assert.equal(isReadingImages(`${prefix}two`), false);
    assert.equal(isReadingImages('draft-image-read:other'), true);

    first.resolve();
    second.resolve();
    other.resolve();
    await Promise.all(reads);
    assert.equal(ready, 1);
});
