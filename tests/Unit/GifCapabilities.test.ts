/// <reference types="node" />
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { afterEach, beforeEach, test } from 'node:test';

const scope = {
    isSecureContext: true,
    onmessage: undefined as unknown as (event: { data: File }) => Promise<void>,
    messages: [] as Array<{ error?: string; static?: boolean }>,
    postMessage(message: { error?: string; static?: boolean }) {
        this.messages.push(message);
    },
};
const originals = new Map(
    ['self', 'VideoEncoder', 'OffscreenCanvas'].map((key) => [
        key,
        Object.getOwnPropertyDescriptor(globalThis, key),
    ]),
);
Object.defineProperty(globalThis, 'self', { value: scope, configurable: true });
await import('../../resources/js/lib/gif/worker.ts');
Reflect.deleteProperty(globalThis, 'self');

const animated = readFileSync(
    new URL('../Fixtures/media/animated.gif', import.meta.url),
);

beforeEach(() => {
    scope.isSecureContext = true;
    scope.messages = [];
    Object.defineProperty(globalThis, 'VideoEncoder', {
        value: undefined,
        configurable: true,
    });
    Object.defineProperty(globalThis, 'OffscreenCanvas', {
        value: class {},
        configurable: true,
    });
});
afterEach(() => {
    for (const [key, descriptor] of originals) {
        if (descriptor) Object.defineProperty(globalThis, key, descriptor);
        else Reflect.deleteProperty(globalThis, key);
    }
});

async function run(bytes = animated) {
    await scope.onmessage({
        data: new File([new Uint8Array(bytes)], 'fixture.gif', {
            type: 'image/gif',
        }),
    });
    return scope.messages.at(-1)!;
}

void test('explains the HTTPS requirement instead of blaming the browser', async () => {
    scope.isSecureContext = false;
    assert.match((await run()).error!, /requires HTTPS or localhost/);
});

void test('requires a native encoder when no WASM encoder is installed', async () => {
    assert.match((await run()).error!, /browser cannot convert animated GIFs/);
});

void test('checks the actual VP8 encoder configuration', async () => {
    const configurations: VideoEncoderConfig[] = [];
    Object.defineProperty(globalThis, 'VideoEncoder', {
        value: class {
            static async isConfigSupported(config: VideoEncoderConfig) {
                configurations.push(config);
                return { supported: false };
            }
        },
        configurable: true,
    });
    assert.match((await run()).error!, /no supported VP8 encoder/);
    assert.equal(configurations[0].codec, 'vp8');
    assert.equal(configurations[0].width, 32);
    assert.equal(configurations[0].height, 24);
});

void test('static GIFs do not require a secure context or a video encoder', async () => {
    scope.isSecureContext = false;
    const bytes = readFileSync(
        new URL('../Fixtures/media/static.gif', import.meta.url),
    );
    assert.deepEqual(await run(bytes), { static: true });
});

void test('rejects animated GIFs beyond the duration limit before starting an encoder', async () => {
    const bytes = Buffer.from(animated);
    const offset = bytes.indexOf(Buffer.from([0x21, 0xf9, 4]));
    bytes.writeUInt16LE(3100, offset + 4);
    assert.match((await run(bytes)).error!, /at most 30 seconds/);
});

void test('rejects too many frames without decoding their pixels', async () => {
    const still = readFileSync(
        new URL('../Fixtures/media/static.gif', import.meta.url),
    );
    const header = still.subarray(0, 25);
    const frame = still.subarray(25, -1);
    const bytes = Buffer.concat([
        header,
        ...Array.from({ length: 601 }, () => frame),
        Buffer.from([0x3b]),
    ]);
    assert.match((await run(bytes)).error!, /1–600 frames/);
});

void test('bounds source pixel memory before creating canvases', async () => {
    const bytes = Buffer.from(animated);
    bytes.writeUInt16LE(2500, 6);
    bytes.writeUInt16LE(2000, 8);
    assert.match((await run(bytes)).error!, /4 million source pixels/);
});

void test('rejects oversized GIF input before parsing it', async () => {
    assert.match(
        (await run(Buffer.alloc(10 * 1024 * 1024 + 1))).error!,
        /at most 10 MB/,
    );
});

void test('stops at the frame limit before reading another frame payload', async () => {
    const header = Buffer.from('47494638396101000100800000000000ffffff', 'hex');
    const frame = Buffer.from('2c0000000001000100000202440100', 'hex');
    const bytes = Buffer.concat([
        header,
        ...Array.from({ length: 600 }, () => frame),
        Buffer.from([0x2c]), // The 601st frame has no descriptor or pixel data.
    ]);
    assert.match((await run(bytes)).error!, /1–600 frames/);
});

void test('bounds extension objects before parsing comment-heavy GIFs', async () => {
    const bytes = Buffer.concat([
        animated.subarray(0, 25),
        ...Array.from({ length: 4097 }, () => Buffer.from([0x21, 0xfe, 0])),
        animated.subarray(25),
    ]);
    assert.match((await run(bytes)).error!, /too many blocks/);
});

void test('bounds sub-block arrays even inside one extension', async () => {
    const bytes = Buffer.concat([
        animated.subarray(0, 25),
        Buffer.from([0x21, 0xfe]),
        ...Array.from({ length: 65_537 }, () => Buffer.from([1, 0])),
        Buffer.from([0]),
        animated.subarray(25),
    ]);
    assert.match((await run(bytes)).error!, /too many data blocks/);
});

void test('rejects truncated tables, sub-blocks, and missing GIF trailers', async () => {
    for (const bytes of [
        animated.subarray(0, 15),
        Buffer.concat([animated.subarray(0, 25), Buffer.from([0x21, 0xfe, 255])]),
        animated.subarray(0, -1),
    ]) {
        assert.match((await run(bytes)).error!, /incomplete or malformed/);
    }
});
