import assert from 'node:assert/strict';
import { afterEach, test } from 'node:test';
import {
    needsConversion,
    PickedImageError,
    readPickedImages,
} from '../../resources/js/lib/read-picked-images.ts';

function stubImageCodecs({
    width = 4032,
    height = 3024,
    encodes = 'image/webp',
} = {}) {
    const decoded: number[] = [];
    const sources: Blob[] = [];
    const canvases: { width: number; height: number }[] = [];
    const transforms: unknown[][] = [];
    const drawn: unknown[][] = [];
    const closed: number[] = [];

    Object.assign(globalThis, {
        createImageBitmap: (image: Blob) => {
            decoded.push(image.size);
            sources.push(image);

            return Promise.resolve({
                width,
                height,
                close: () => closed.push(image.size),
            });
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
                return {
                    transform: (...matrix: unknown[]) =>
                        transforms.push(matrix),
                    drawImage: (_image: unknown, ...target: unknown[]) =>
                        drawn.push(target),
                };
            }

            convertToBlob() {
                return Promise.resolve(
                    new Blob(['encoded'], { type: encodes }),
                );
            }
        },
    });

    return { decoded, sources, canvases, transforms, drawn, closed };
}

const BYTE = 1;
const ASCII = 2;
const SHORT = 3;
const LONG = 4;
const RATIONAL = 5;
const UNDEFINED = 7;
const SRATIONAL = 10;

/** The bytes one value of each TIFF field type takes. */
const typeSizes: Record<number, number> = {
    [BYTE]: 1,
    [ASCII]: 1,
    [SHORT]: 2,
    [LONG]: 4,
    [RATIONAL]: 8,
    [UNDEFINED]: 1,
    [SRATIONAL]: 8,
};

type Reference = `${'ifd' | 'data' | 'size'}:${string}`;
type Value = number | Reference;
type Field = [tag: number, type: number, value: Value | Value[] | Uint8Array];
type Ifd = { fields: Field[]; next?: Reference };

/**
 * Lays out a TIFF the way a DNG is laid out: the header, the IFDs with their
 * entries in the order of their tags, the values too long for their entry,
 * then the data the IFDs point to. A reference names the offset of an IFD or
 * of data, or the length of data. A rational is its numerator and its
 * denominator in turn.
 */
function tiff(
    little: boolean,
    ifds: Ifd[],
    data: Record<string, Uint8Array> = {},
): Uint8Array<ArrayBuffer> {
    const valuesOf = ([, , value]: Field): Value[] =>
        value instanceof Uint8Array
            ? Array.from(value)
            : Array.isArray(value)
              ? value
              : [value];
    const countOf = (field: Field): number =>
        valuesOf(field).length / (typeSizes[field[1]] === 8 ? 2 : 1);
    const byteLength = (field: Field): number =>
        countOf(field) * typeSizes[field[1]];

    const ifdOffsets: number[] = [];
    const outside = new Map<Field, number>();
    const dataOffsets: Record<string, number> = {};
    let end = 8;

    for (const { fields } of ifds) {
        ifdOffsets.push(end);
        end += 2 + fields.length * 12 + 4;
    }

    for (const field of ifds.flatMap(({ fields }) => fields)) {
        if (byteLength(field) > 4) {
            outside.set(field, end);
            end += byteLength(field) + (byteLength(field) % 2);
        }
    }

    for (const [name, bytes] of Object.entries(data)) {
        dataOffsets[name] = end;
        end += bytes.length;
    }

    const resolve = (value: Value): number => {
        if (typeof value === 'number') return value;

        const [kind, name] = value.split(':');

        if (kind === 'ifd') return ifdOffsets[Number(name)];

        return kind === 'data' ? dataOffsets[name] : data[name].length;
    };
    const bytes = new Uint8Array(end);
    const view = new DataView(bytes.buffer);
    const write = (at: number, field: Field) => {
        const step = Math.min(typeSizes[field[1]], 4);

        valuesOf(field).forEach((value, index) => {
            if (step === 1) view.setUint8(at + index, resolve(value));
            else if (step === 2)
                view.setUint16(at + index * 2, resolve(value), little);
            else view.setUint32(at + index * 4, resolve(value), little);
        });
    };

    bytes.set(little ? [0x49, 0x49] : [0x4d, 0x4d]);
    view.setUint16(2, 42, little);
    view.setUint32(4, ifdOffsets[0], little);

    ifds.forEach(({ fields, next }, index) => {
        let at = ifdOffsets[index];
        view.setUint16(at, fields.length, little);
        at += 2;

        for (const field of [...fields].sort((a, b) => a[0] - b[0])) {
            const offset = outside.get(field);

            view.setUint16(at, field[0], little);
            view.setUint16(at + 2, field[1], little);
            view.setUint32(at + 4, countOf(field), little);

            if (offset === undefined) {
                write(at + 8, field);
            } else {
                view.setUint32(at + 8, offset, little);
                write(offset, field);
            }

            at += 12;
        }

        view.setUint32(at, next === undefined ? 0 : resolve(next), little);
    });

    for (const [name, offset] of Object.entries(dataOffsets)) {
        bytes.set(data[name], offset);
    }

    return bytes;
}

const ascii = (text: string): number[] =>
    Array.from(text, (character) => character.charCodeAt(0));

const rawData = new TextEncoder().encode('raw sensor data');

/**
 * A JPEG cut down to its segments before the image data: the start, an EXIF
 * segment when asked for, a JFIF segment, then the start of scan with a label
 * standing in for the scan.
 */
function jpegPreview(label: string, exif: boolean): Uint8Array<ArrayBuffer> {
    return new Uint8Array([
        0xff,
        0xd8,
        ...(exif
            ? [0xff, 0xe1, 0x00, 0x0a, ...ascii('Exif\0\0'), 0x00, 0x06]
            : []),
        0xff,
        0xe0,
        0x00,
        0x07,
        ...ascii('JFIF\0'),
        0xff,
        0xda,
        ...ascii(label),
    ]);
}

/**
 * A DNG laid out as an iPhone writes a ProRAW photo: big-endian, IFD 0 the
 * JPEG preview at full size with the orientation and the fields of the camera,
 * the preview's own EXIF naming the orientation too, and the raw data as
 * linear raw in tiles in a SubIFD. Several fields, from the bits per sample to
 * the color matrix, are too long for their entry, as they are in such a file.
 */
function proRawDng(orientation = 6): Uint8Array<ArrayBuffer> {
    const tiles = 48;

    return tiff(
        false,
        [
            {
                fields: [
                    [254, LONG, 1],
                    [256, LONG, 8064],
                    [257, LONG, 6048],
                    [258, SHORT, [8, 8, 8]],
                    [259, SHORT, 7],
                    [262, SHORT, 6],
                    [271, ASCII, ascii('Apple\0')],
                    [272, ASCII, ascii('iPhone 16 Pro\0')],
                    [273, LONG, 'data:preview'],
                    [274, SHORT, orientation],
                    [277, SHORT, 3],
                    [278, LONG, 6048],
                    [279, LONG, 'size:preview'],
                    [282, RATIONAL, [72, 1]],
                    [283, RATIONAL, [72, 1]],
                    [296, SHORT, 2],
                    [305, ASCII, ascii('18.1\0')],
                    [306, ASCII, ascii('2026:11:13 10:00:00\0')],
                    [330, LONG, 'ifd:1'],
                    [700, BYTE, new Uint8Array(200).fill(0x20)],
                    [50706, BYTE, [1, 6, 0, 0]],
                    [50708, ASCII, ascii('iPhone 16 Pro Back Camera\0')],
                    [
                        50721,
                        SRATIONAL,
                        [1, 1, 0, 1, 0, 1, 0, 1, 1, 1, 0, 1, 0, 1, 0, 1, 1, 1],
                    ],
                    [50728, RATIONAL, [1, 2, 1, 1, 1, 3]],
                    [50730, SRATIONAL, [0, 1]],
                ],
            },
            {
                fields: [
                    [254, LONG, 0],
                    [256, LONG, 8064],
                    [257, LONG, 6048],
                    [258, SHORT, [16, 16, 16]],
                    [259, SHORT, 7],
                    [262, SHORT, 34892],
                    [277, SHORT, 3],
                    [322, LONG, 1024],
                    [323, LONG, 1024],
                    [324, LONG, Array<Value>(tiles).fill('data:raw')],
                    [325, LONG, Array<Value>(tiles).fill('size:raw')],
                    [50712, SHORT, Array<Value>(1024).fill(0)],
                    [50740, UNDEFINED, new Uint8Array(300)],
                ],
            },
        ],
        { preview: jpegPreview('the photo', true), raw: rawData },
    );
}

/**
 * A DNG of another layout, as a converter writes one: little-endian, a small
 * uncompressed thumbnail in IFD 0 without an orientation, two SubIFDs whose
 * offsets lie outside their entry, the raw data as lossless JPEG in four
 * strips, a large preview as an old-style JPEG stream and a small one, one of
 * them a SubIFD and the other chained after IFD 0, and a damaged IFD chained
 * after that.
 */
function otherDng(largeFirst = true): Uint8Array<ArrayBuffer> {
    const strips = (name: string): Value[] =>
        Array<Value>(4).fill(`data:${name}`);
    const large: Ifd = {
        fields: [
            [254, LONG, 1],
            [256, LONG, 4032],
            [257, LONG, 3024],
            [259, SHORT, 6],
            [262, SHORT, 6],
            [513, LONG, 'data:large'],
            [514, LONG, 'size:large'],
        ],
    };
    const small: Ifd = {
        fields: [
            [254, LONG, 1],
            [256, LONG, 1024],
            [257, LONG, 768],
            [259, SHORT, 7],
            [262, SHORT, 6],
            [273, LONG, 'data:small'],
            [279, LONG, 'size:small'],
        ],
    };
    const [first, chained]: [Ifd, Ifd] = largeFirst
        ? [large, small]
        : [small, large];

    return tiff(
        true,
        [
            {
                fields: [
                    [254, LONG, 1],
                    [256, SHORT, 256],
                    [257, SHORT, 192],
                    [258, SHORT, [8, 8, 8]],
                    [259, SHORT, 1],
                    [262, SHORT, 2],
                    [273, LONG, 'data:thumbnail'],
                    [277, SHORT, 3],
                    [279, LONG, 'size:thumbnail'],
                    [330, LONG, ['ifd:1', 'ifd:2']],
                ],
                next: 'ifd:3',
            },
            {
                fields: [
                    [254, LONG, 0],
                    [256, LONG, 8064],
                    [257, LONG, 6048],
                    [259, SHORT, 7],
                    [262, SHORT, 32803],
                    [273, LONG, strips('raw')],
                    [279, LONG, Array<Value>(4).fill('size:raw')],
                ],
            },
            first,
            // Its IFD count claims 65,535 entries, far more than the file holds.
            { ...chained, next: 'data:damaged' },
        ],
        {
            thumbnail: new Uint8Array(256 * 192 * 3),
            raw: rawData,
            large: jpegPreview('large preview', false),
            small: jpegPreview('small preview', false),
            damaged: new Uint8Array([0xff, 0xff]),
        },
    );
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
    assert.match((errors[0] as Error).message, /100 MB/);
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

void test('converts HEIC photos and images over the server limit to the size it keeps', async () => {
    const { decoded, canvases, drawn, closed } = stubImageCodecs();
    const heic = new File(['heic'], 'IMG_0001.HEIC', { type: 'image/heic' });
    const over = new File(
        [new Uint8Array(10_000 * 1024 + 1)],
        'screenshot.png',
        { type: 'image/png' },
    );
    const atLimit = new File([new Uint8Array(10_000 * 1024)], 'at-limit.jpg', {
        type: 'image/jpeg',
    });

    const { images, errors } = await readPickedImages([heic, over, atLimit]);

    assert.deepEqual(errors, []);
    assert.deepEqual(
        images.map(({ name, type }) => [name, type]),
        [
            ['IMG_0001.webp', 'image/webp'],
            ['screenshot.webp', 'image/webp'],
            ['at-limit.jpg', 'image/jpeg'],
        ],
    );
    assert.deepEqual(decoded, [4, 10_000 * 1024 + 1]);
    assert.deepEqual(canvases, [
        { width: 1600, height: 1200 },
        { width: 1600, height: 1200 },
    ]);
    assert.deepEqual(drawn, [
        [0, 0, 1600, 1200],
        [0, 0, 1600, 1200],
    ]);
    assert.deepEqual(closed, decoded);
    assert.equal(images[2].size, 10_000 * 1024);
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

void test('converts a ProRAW DNG through its preview, turned once by the orientation of IFD 0', async () => {
    const { sources, canvases, transforms, drawn } = stubImageCodecs({
        width: 8064,
        height: 6048,
    });
    const dng = new File([proRawDng()], 'IMG_1641.DNG');

    const {
        images: [image],
        errors,
    } = await readPickedImages([dng]);

    assert.equal(needsConversion(dng), true);
    assert.deepEqual(errors, []);
    assert.equal(image.name, 'IMG_1641.webp');
    assert.deepEqual(
        new Uint8Array(await sources[0].arrayBuffer()),
        jpegPreview('the photo', false),
    );
    assert.deepEqual(canvases, [{ width: 1200, height: 1600 }]);
    assert.deepEqual(transforms, [[0, 1, -1, 0, 1200, 0]]);
    assert.deepEqual(drawn, [[0, 0, 1600, 1200]]);
});

void test('turns a DNG upright for each orientation of IFD 0, and an unknown one leaves it as stored', async () => {
    // Where the first stored pixel, the end of the first stored row and the
    // end of the first stored column land on the canvas, as the TIFF
    // orientation defines them: with 6 the first row is the right-hand side,
    // read from the top, and the first column is the top, read from the right.
    const corners: Record<
        number,
        [origin: number[], rowEnd: number[], columnEnd: number[]]
    > = {
        1: [
            [0, 0],
            [1600, 0],
            [0, 1200],
        ],
        2: [
            [1600, 0],
            [0, 0],
            [1600, 1200],
        ],
        3: [
            [1600, 1200],
            [0, 1200],
            [1600, 0],
        ],
        4: [
            [0, 1200],
            [1600, 1200],
            [0, 0],
        ],
        5: [
            [0, 0],
            [0, 1600],
            [1200, 0],
        ],
        6: [
            [1200, 0],
            [1200, 1600],
            [0, 0],
        ],
        7: [
            [1200, 1600],
            [1200, 0],
            [0, 1600],
        ],
        8: [
            [0, 1600],
            [0, 0],
            [1200, 1600],
        ],
        9: [
            [0, 0],
            [1600, 0],
            [0, 1200],
        ],
    };

    for (const [orientation, [origin, rowEnd, columnEnd]] of Object.entries(
        corners,
    )) {
        const { canvases, transforms, drawn } = stubImageCodecs({
            width: 8064,
            height: 6048,
        });

        const { errors } = await readPickedImages([
            new File([proRawDng(Number(orientation))], 'IMG_1641.DNG'),
        ]);

        const [a, b, c, d, e, f] = (transforms[0] ?? [1, 0, 0, 1, 0, 0]) as number[];
        const [x, y, width, height] = drawn[0] as number[];
        const place = ([px, py]: number[]) => [
            a * px + c * py + e,
            b * px + d * py + f,
        ];
        const turned = ['5', '6', '7', '8'].includes(orientation);

        assert.deepEqual(errors, [], `orientation ${orientation}`);
        assert.deepEqual(
            canvases,
            [turned ? { width: 1200, height: 1600 } : { width: 1600, height: 1200 }],
            `orientation ${orientation}`,
        );
        assert.deepEqual(place([x, y]), origin, `orientation ${orientation}`);
        assert.deepEqual(
            place([x + width, y]),
            rowEnd,
            `orientation ${orientation}`,
        );
        assert.deepEqual(
            place([x, y + height]),
            columnEnd,
            `orientation ${orientation}`,
        );
    }
});

void test('recognizes a DNG by its type when its name has no extension', async () => {
    stubImageCodecs({ width: 8064, height: 6048 });

    const { images, errors } = await readPickedImages([
        new File([proRawDng()], 'IMG_1641', { type: 'image/x-adobe-dng' }),
        new File([proRawDng()], 'IMG_1642', { type: 'image/dng' }),
    ]);

    assert.deepEqual(errors, []);
    assert.deepEqual(
        images.map(({ name }) => name),
        ['IMG_1641.webp', 'IMG_1642.webp'],
    );
});

void test('takes the largest JPEG preview of another layout in either order, through an old-style JPEG stream and past a damaged IFD, and one without an orientation stays as stored', async () => {
    for (const largeFirst of [true, false]) {
        const { sources, canvases, transforms } = stubImageCodecs();

        const {
            images: [image],
            errors,
        } = await readPickedImages([
            new File([otherDng(largeFirst)], 'scan.dng', {
                type: 'image/x-adobe-dng',
            }),
        ]);

        assert.deepEqual(errors, [], `large first: ${largeFirst}`);
        assert.equal(image.type, 'image/webp');
        assert.deepEqual(
            new Uint8Array(await sources[0].arrayBuffer()),
            jpegPreview('large preview', false),
            `large first: ${largeFirst}`,
        );
        assert.deepEqual(canvases, [{ width: 1600, height: 1200 }]);
        assert.deepEqual(transforms, []);
    }
});

void test('reads each IFD once, so IFDs that point back to each other still lead to the preview', async () => {
    const { sources } = stubImageCodecs();
    const looped = tiff(
        true,
        [
            {
                fields: [
                    [254, LONG, 1],
                    [259, SHORT, 1],
                    [330, LONG, 'ifd:1'],
                ],
                next: 'ifd:0',
            },
            {
                fields: [
                    [254, LONG, 1],
                    [256, LONG, 4032],
                    [257, LONG, 3024],
                    [259, SHORT, 7],
                    [262, SHORT, 6],
                    [273, LONG, 'data:preview'],
                    [279, LONG, 'size:preview'],
                    [330, LONG, 'ifd:0'],
                ],
                next: 'ifd:1',
            },
        ],
        { preview: jpegPreview('looped preview', false) },
    );

    const { errors } = await readPickedImages([
        new File([looped], 'looped.dng'),
    ]);

    assert.deepEqual(errors, []);
    assert.deepEqual(
        new Uint8Array(await sources[0].arrayBuffer()),
        jpegPreview('looped preview', false),
    );
});

void test('removes the EXIF behind fill bytes, and stops at a segment that runs past the preview', async () => {
    const { sources } = stubImageCodecs();
    const exif = [0xff, 0xe1, 0x00, 0x0a, ...ascii('Exif\0\0'), 0x00, 0x06];
    const jfif = [0xff, 0xe0, 0x00, 0x07, ...ascii('JFIF\0')];
    const scan = [0xff, 0xda, ...ascii('scan')];
    const overrun = [0xff, 0xe0, 0xff, 0xff, ...ascii('short')];
    const dng = (jpeg: number[]) =>
        tiff(
            false,
            [
                {
                    fields: [
                        [256, LONG, 8064],
                        [257, LONG, 6048],
                        [259, SHORT, 7],
                        [262, SHORT, 6],
                        [273, LONG, 'data:preview'],
                        [279, LONG, 'size:preview'],
                    ],
                },
            ],
            { preview: new Uint8Array([0xff, 0xd8, ...jpeg]) },
        );

    const { errors } = await readPickedImages([
        new File([dng([0xff, ...exif, ...jfif, ...scan])], 'filled.dng'),
        new File([dng([...exif, ...overrun])], 'overrun.dng'),
    ]);

    assert.deepEqual(errors, []);
    assert.deepEqual(
        new Uint8Array(await sources[0].arrayBuffer()),
        new Uint8Array([0xff, 0xd8, 0xff, ...jfif, ...scan]),
    );
    assert.deepEqual(
        new Uint8Array(await sources[1].arrayBuffer()),
        new Uint8Array([0xff, 0xd8, ...overrun]),
    );
});

void test('rejects a DNG without a usable JPEG preview, and a file that is no TIFF', async () => {
    const { decoded } = stubImageCodecs();
    const preview = (
        photometric: number,
        fields: Field[] = [
            [273, LONG, 'data:image'],
            [279, LONG, 'size:image'],
        ],
        data: Uint8Array = jpegPreview('preview', false),
    ): Uint8Array<ArrayBuffer> =>
        tiff(
            true,
            [
                {
                    fields: [
                        [254, LONG, 0],
                        [256, LONG, 8064],
                        [257, LONG, 6048],
                        [259, SHORT, 7],
                        [262, SHORT, photometric],
                        ...fields,
                    ],
                },
            ],
            { image: data },
        );
    const whole = preview(6);
    const chain = tiff(
        true,
        [
            ...Array.from({ length: 40 }, (_, index) => ({
                fields: [[254, LONG, 1]] satisfies Field[],
                next: `ifd:${index + 1}` as Reference,
            })),
            {
                fields: [
                    [256, LONG, 4032],
                    [257, LONG, 3024],
                    [259, SHORT, 7],
                    [262, SHORT, 6],
                    [273, LONG, 'data:image'],
                    [279, LONG, 'size:image'],
                ],
            },
        ],
        { image: jpegPreview('preview', false) },
    );

    const { images, errors } = await readPickedImages([
        new File([preview(32803, undefined, rawData)], 'raw.dng', {
            type: 'image/dng',
        }),
        new File([preview(6, undefined, rawData)], 'no-jpeg.dng'),
        new File(
            [
                preview(6, [
                    [273, LONG, ['data:image', 'data:image']],
                    [279, LONG, ['size:image', 'size:image']],
                ]),
            ],
            'two-strips.dng',
        ),
        new File([whole.slice(0, whole.length - 2)], 'cut-preview.dng'),
        new File([proRawDng().slice(0, 20)], 'cut-ifd.dng'),
        new File([chain], 'long-chain.dng'),
        new File(['not a tiff'], 'not-a-tiff.dng'),
    ]);

    assert.deepEqual(images, []);
    assert.equal(errors.length, 7);
    for (const error of errors) {
        assert.ok(error instanceof PickedImageError);
        assert.match(error.message, /no preview/);
    }
    assert.deepEqual(decoded, []);
});

void test('rejects a DNG whose preview the browser cannot decode', async () => {
    stubImageCodecs();
    Object.assign(globalThis, {
        createImageBitmap: () =>
            Promise.reject(
                new DOMException('Undecodable', 'InvalidStateError'),
            ),
    });

    const {
        images,
        errors: [error],
    } = await readPickedImages([new File([proRawDng()], 'IMG_1641.DNG')]);

    assert.deepEqual(images, []);
    assert.ok(error instanceof PickedImageError);
    assert.match(error.message, /no preview this browser can convert/);
});
