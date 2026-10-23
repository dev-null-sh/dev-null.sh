import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { registerHooks } from 'node:module';
import { test } from 'node:test';

// Vite finds the worker's sandbox without its extension, which Node needs.
registerHooks({
    resolve: (specifier, context, nextResolve) =>
        nextResolve(
            specifier === './sandbox' ? './sandbox.ts' : specifier,
            context,
        ),
});

const posted: unknown[] = [];

// The worker runs in a scope of its own, which this file plays.
Object.assign(globalThis, {
    WorkerGlobalScope: function WorkerGlobalScope() {},
    self: globalThis,
    postMessage: (message: unknown) => posted.push(message),
});

await import('../../resources/js/lib/php/worker.ts');

const { onmessage } = globalThis as unknown as {
    onmessage: (event: {
        data: { module: WebAssembly.Module; code: string };
    }) => Promise<void>;
};

const module = await WebAssembly.compile(
    readFileSync(
        new URL('../../resources/js/lib/php/php.wasm', import.meta.url),
    ),
);

void test('gives up every way out of the worker before PHP runs', () => {
    for (const name of ['fetch', 'XMLHttpRequest', 'WebSocket', 'indexedDB']) {
        assert.equal(Reflect.get(globalThis, name), undefined, name);
    }
});

void test('hands each line over as PHP prints it, then how the run ended', async () => {
    posted.length = 0;

    await onmessage({
        data: { module, code: 'echo "one\\ntwo\\n"; echo "three";' },
    });

    assert.deepEqual(posted, [
        { type: 'line', line: 'one' },
        { type: 'line', line: 'two' },
        { type: 'line', line: 'three' },
        {
            type: 'done',
            outcome: { exitCode: 0, failure: null, isTruncated: false },
        },
    ]);
});
