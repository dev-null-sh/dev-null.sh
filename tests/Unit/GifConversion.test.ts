/// <reference types="node" />
import assert from 'node:assert/strict';
import { afterEach, beforeEach, test } from 'node:test';
import { convertGif } from '../../resources/js/lib/gif/convert.ts';

class ConversionWorker {
    static instances: ConversionWorker[] = [];
    onmessage: ((event: { data: unknown }) => void) | null = null;
    onerror: ((event: { message: string }) => void) | null = null;
    terminated = false;
    file?: File;

    constructor() {
        ConversionWorker.instances.push(this);
    }
    postMessage(file: File) {
        this.file = file;
    }
    terminate() {
        this.terminated = true;
    }
    send(data: unknown) {
        this.onmessage?.({ data });
    }
}

const originalWorker = Object.getOwnPropertyDescriptor(globalThis, 'Worker');
const file = new File(['GIF89a'], 'animation.gif', { type: 'image/gif' });

beforeEach(() => {
    ConversionWorker.instances = [];
    Object.defineProperty(globalThis, 'Worker', {
        value: ConversionWorker,
        configurable: true,
    });
});
afterEach(() => {
    if (originalWorker)
        Object.defineProperty(globalThis, 'Worker', originalWorker);
    else Reflect.deleteProperty(globalThis, 'Worker');
});

void test('creates a worker only when requested and forwards progress', async () => {
    assert.equal(ConversionWorker.instances.length, 0);
    const progress: string[] = [];
    const pending = convertGif(file, new AbortController().signal, (value) =>
        progress.push(value),
    );
    const worker = ConversionWorker.instances[0];
    worker.send({ progress: 'Converting GIF… 50%' });
    assert.deepEqual(progress, ['Converting GIF… 50%']);
    worker.send({ static: true });
    assert.equal((await pending).file, file);
    assert.equal(worker.terminated, true);
});

void test('returns a named WebM and poster from a completed worker', async () => {
    const pending = convertGif(file, new AbortController().signal, () => {});
    const worker = ConversionWorker.instances[0];
    worker.send({
        video: new Uint8Array([1, 2, 3]).buffer,
        poster: new Blob(['poster'], { type: 'image/png' }),
    });
    const result = await pending;
    assert.equal(result.file.name, 'animation.webm');
    assert.equal(result.file.type, 'video/webm');
    assert.equal(result.poster?.type, 'image/png');
    assert.equal(worker.terminated, true);
});

void test('cancels an active worker and releases the conversion slot', async () => {
    const controller = new AbortController();
    const pending = convertGif(file, controller.signal, () => {});
    const rejected = assert.rejects(pending, { name: 'AbortError' });
    controller.abort();
    await rejected;
    assert.equal(ConversionWorker.instances[0].terminated, true);
    const next = convertGif(file, new AbortController().signal, () => {});
    ConversionWorker.instances[1].send({ static: true });
    assert.equal((await next).file, file);
});

void test('honors cancellation before conversion starts', async () => {
    const controller = new AbortController();
    controller.abort();
    await assert.rejects(
        convertGif(file, controller.signal, () => {}),
        { name: 'AbortError' },
    );
    assert.ok(ConversionWorker.instances.every((worker) => worker.terminated));
});

void test('does not run two conversion workers concurrently', async () => {
    const pending = convertGif(file, new AbortController().signal, () => {});
    await assert.rejects(
        convertGif(file, new AbortController().signal, () => {}),
        /Another GIF is converting/,
    );
    assert.equal(ConversionWorker.instances.length, 1);
    ConversionWorker.instances[0].send({ static: true });
    await pending;
});

void test('preserves conversion errors and terminates the worker', async () => {
    const pending = convertGif(file, new AbortController().signal, () => {});
    const rejected = assert.rejects(
        pending,
        /GIF contains invalid frame dimensions/,
    );
    ConversionWorker.instances[0].send({
        error: 'GIF contains invalid frame dimensions',
    });
    await rejected;
    assert.equal(ConversionWorker.instances[0].terminated, true);
});

void test('reports a crashed worker and releases its resources', async () => {
    const pending = convertGif(file, new AbortController().signal, () => {});
    const rejected = assert.rejects(
        pending,
        /worker failed: Unexpected exception/,
    );
    ConversionWorker.instances[0].onerror?.({
        message: 'Unexpected exception',
    });
    await rejected;
    assert.equal(ConversionWorker.instances[0].terminated, true);
});

void test('terminates a stalled worker at the conversion deadline', async (context) => {
    context.mock.timers.enable({ apis: ['setTimeout'] });
    const pending = convertGif(file, new AbortController().signal, () => {});
    const rejected = assert.rejects(pending, /conversion took too long/);
    context.mock.timers.tick(120_000);
    await rejected;
    assert.equal(ConversionWorker.instances[0].terminated, true);
});
