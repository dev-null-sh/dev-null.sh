import assert from 'node:assert/strict';
import { afterEach, test } from 'node:test';
import { readPickedImages } from '../../resources/js/lib/read-picked-images.ts';

function stubImageCodecs({
    width = 4032,
    height = 3024,
    encodes = 'image/webp',
} = {}) {
    const decoded: number[] = [];
    const canvases: { width: number; height: number }[] = [];

    Object.assign(globalThis, {
        createImageBitmap: (image: Blob) => {
            decoded.push(image.size);

            return Promise.resolve({ width, height, close: () => {} });
        },
        OffscreenCanvas: class {
            width: number;
            height: number;

            constructor(canvasWidth: number, canvasHeight: number) {
                this.width = canvasWidth;
                this.height = canvasHeight;
                canvases.push({ width: canvasWidth, height: canvasHeight });
            }

            getContext() {
                return { drawImage: () => {} };
            }

            convertToBlob() {
                return Promise.resolve(
                    new Blob(['encoded'], { type: encodes }),
                );
            }
        },
    });

    return { decoded, canvases };
}

afterEach(() => {
    Reflect.deleteProperty(globalThis, 'createImageBitmap');
    Reflect.deleteProperty(globalThis, 'OffscreenCanvas');
});

void test('copies provider bytes without reading unstable metadata', async () => {
    const source = new File(['image bytes'], 'photo.jpg', {
        type: 'image/jpeg',
    });
    const provider = {
        name: source.name,
        type: source.type,
        get size() {
            throw new Error('Metadata must not be read');
        },
        get lastModified() {
            throw new Error('Metadata must not be read');
        },
        stream: () => source.stream(),
    } as unknown as File;
    const {
        images: [image],
        errors,
    } = await readPickedImages([provider]);
    assert.deepEqual(errors, []);
    assert.notEqual(image, provider);
    assert.equal(image.name, source.name);
    assert.equal(image.type, source.type);
    assert.equal(await image.text(), 'image bytes');
    const form = new FormData();
    form.append('images[]', image);
    assert.equal(await (form.get('images[]') as File).text(), 'image bytes');
});

void test('rejects an unreadable provider instead of attaching a broken file', async () => {
    const provider = {
        type: 'image/png',
        stream: () =>
            new ReadableStream({
                start(controller) {
                    controller.error(
                        new DOMException('Unreadable', 'NotReadableError'),
                    );
                },
            }),
    } as unknown as File;
    const { images, errors } = await readPickedImages([provider]);
    assert.deepEqual(images, []);
    assert.equal((errors[0] as Error).name, 'NotReadableError');
});

void test('bounds oversized reads and cancels the provider stream', async () => {
    let cancelled = false;
    const provider = {
        type: 'image/png',
        stream: () =>
            new ReadableStream({
                pull(controller) {
                    controller.enqueue(new Uint8Array(1024 * 1024));
                },
                cancel() {
                    cancelled = true;
                },
            }),
    } as unknown as File;
    const { images, errors } = await readPickedImages([provider]);
    assert.deepEqual(images, []);
    assert.match((errors[0] as Error).message, /50 MB/);
    assert.equal(cancelled, true);
});

void test('rejects empty and unsupported images', async () => {
    const { images, errors } = await readPickedImages([
        new File([], 'empty.png', { type: 'image/png' }),
        new File(['gif'], 'photo.gif', { type: 'image/gif' }),
    ]);
    assert.deepEqual(images, []);
    assert.match((errors[0] as Error).message, /empty/);
    assert.match((errors[1] as Error).message, /JPEG/);
});

void test('stops reading providers once successful copies fill capacity', async () => {
    const valid = new File(['valid'], 'valid.png', { type: 'image/png' });
    const skipped = {
        type: 'image/png',
        stream() {
            assert.fail('Files beyond capacity must not be read');
        },
    } as unknown as File;
    const { images, errors } = await readPickedImages([valid, skipped], 1);
    assert.equal(images.length, 1);
    assert.equal(await images[0].text(), 'valid');
    assert.match((errors[0] as Error).message, /Up to 3/);
});

void test('converts HEIC photos and images over 10 MB to the size the server keeps', async () => {
    const { decoded, canvases } = stubImageCodecs();
    const heic = new File(['heic'], 'IMG_0001.HEIC', { type: 'image/heic' });
    const large = new File(
        [new Uint8Array(10 * 1024 * 1024 + 1)],
        'screenshot.png',
        { type: 'image/png' },
    );
    const small = new File(['small'], 'small.jpg', { type: 'image/jpeg' });

    const { images, errors } = await readPickedImages([heic, large, small]);

    assert.deepEqual(errors, []);
    assert.deepEqual(
        images.map(({ name, type }) => [name, type]),
        [
            ['IMG_0001.webp', 'image/webp'],
            ['screenshot.webp', 'image/webp'],
            ['small.jpg', 'image/jpeg'],
        ],
    );
    assert.deepEqual(decoded, [4, 10 * 1024 * 1024 + 1]);
    assert.deepEqual(canvases, [
        { width: 1600, height: 1200 },
        { width: 1600, height: 1200 },
    ]);
    assert.equal(await images[2].text(), 'small');
});

void test('recognizes a HEIC photo by its extension when it has no type', async () => {
    stubImageCodecs();

    const {
        images: [image],
        errors,
    } = await readPickedImages([new File(['heic'], 'IMG_0002.heic')]);

    assert.deepEqual(errors, []);
    assert.equal(image.name, 'IMG_0002.webp');
});

void test('keeps the size of a smaller image and the PNG of a browser without WebP', async () => {
    const { canvases } = stubImageCodecs({
        width: 1200,
        height: 900,
        encodes: 'image/png',
    });

    const {
        images: [image],
    } = await readPickedImages([
        new File(['heic'], 'IMG_0003.HEIC', { type: 'image/heic' }),
    ]);

    assert.equal(image.name, 'IMG_0003.png');
    assert.equal(image.type, 'image/png');
    assert.deepEqual(canvases, [{ width: 1200, height: 900 }]);
});

void test('rejects a large image the browser cannot decode', async () => {
    stubImageCodecs();
    Object.assign(globalThis, {
        createImageBitmap: () =>
            Promise.reject(
                new DOMException('Undecodable', 'InvalidStateError'),
            ),
    });

    const { images, errors } = await readPickedImages([
        new File([new Uint8Array(10 * 1024 * 1024 + 1)], 'broken.png', {
            type: 'image/png',
        }),
    ]);

    assert.deepEqual(images, []);
    assert.equal((errors[0] as Error).name, 'InvalidStateError');
});
