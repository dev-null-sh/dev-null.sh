/// <reference lib="webworker" />
import { execute } from './sandbox';

declare const self: DedicatedWorkerGlobalScope;

// The worker shares the page's origin, so it gives up every way out before PHP runs: Emscripten would turn PHP's
// sockets into WebSockets, and fetch would carry the reader's cookies.
for (const name of [
    'fetch',
    'XMLHttpRequest',
    'WebSocket',
    'WebTransport',
    'EventSource',
    'importScripts',
    'indexedDB',
    'caches',
    'BroadcastChannel',
]) {
    try {
        Object.defineProperty(self, name, {
            value: undefined,
            configurable: false,
            writable: false,
        });
    } catch {
        // This browser doesn't have it, or won't let it go: either way PHP can't reach it.
    }
}

self.onmessage = async ({
    data,
}: MessageEvent<{ module: WebAssembly.Module; code: string }>) => {
    // Each line goes out as PHP prints it, so what came before a loop that never ends still shows once it's stopped.
    const outcome = await execute(data.module, data.code, (line) =>
        self.postMessage({ type: 'line', line }),
    );

    self.postMessage({ type: 'done', outcome });
};
