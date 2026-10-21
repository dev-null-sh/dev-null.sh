/// <reference types="node" />
import assert from 'node:assert/strict';
import { setImmediate } from 'node:timers/promises';
import * as React from 'react';
import { afterAll, afterEach, test, vi } from 'vite-plus/test';
import type { LinkPreview } from '../../resources/js/types/commit';

type Effect = () => void | (() => void);
type StateUpdate = { url: string; preview: LinkPreview | null };

let effects: Effect[] = [];
let states: unknown[] = [];
let stateIndex = 0;
const updates: StateUpdate[] = [];

vi.doMock('react', () => ({
    ...React,
    useEffect: (effect: Effect) => effects.push(effect),
    useState: (initial: unknown) => {
        const index = stateIndex++;
        states[index] ??= typeof initial === 'function' ? initial() : initial;

        return [states[index], (update: StateUpdate) => updates.push(update)];
    },
}));

const { useDraftLinkPreview } =
    await import('../../resources/js/hooks/use-draft-link-preview');

let nextTimer = 0;
const timers = new Map<number, () => void>();

vi.stubGlobal('window', {
    setTimeout: (callback: () => void) => {
        nextTimer++;
        timers.set(nextTimer, callback);

        return nextTimer;
    },
    clearTimeout: (timer: number) => timers.delete(timer),
});

afterAll(() => vi.unstubAllGlobals());
afterEach(() => {
    effects = [];
    states = [];
    stateIndex = 0;
    updates.length = 0;
    timers.clear();
    nextTimer = 0;
});

test('debounces one request for the chosen URL', () => {
    const fetch = vi.fn();
    vi.stubGlobal('fetch', fetch);
    const url = 'https://example.test/a?tag=gif';

    const cleanup = mount(url);

    assert.equal(fetch.mock.calls.length, 0);
    runTimers();

    assert.equal(fetch.mock.calls.length, 1);
    assert.equal(
        fetch.mock.calls[0]?.[0],
        '/draft-link-preview?url=https%3A%2F%2Fexample.test%2Fa%3Ftag%3Dgif',
    );

    cleanup();
});

test('aborts an in-flight request when the selected URL changes without applying its late result', async () => {
    let resolveResponse: (response: Response) => void;
    let signal: AbortSignal | undefined;
    const fetch = vi.fn(
        (_url: string, options: { signal: AbortSignal }) =>
            new Promise<Response>((resolve) => {
                signal = options.signal;
                resolveResponse = resolve;
            }),
    );
    vi.stubGlobal('fetch', fetch);

    const firstCleanup = mount('https://example.test/first');
    runTimers();

    firstCleanup();
    mount('https://example.test/second');
    resolveResponse!({
        ok: true,
        json: async () => ({ link_preview: null }),
    } as Response);
    await setImmediate();

    assert.equal(signal?.aborted, true);
    assert.deepEqual(updates, []);
});

test('aborts an in-flight request when the component unmounts', () => {
    let signal: AbortSignal | undefined;
    const fetch = vi.fn((_url: string, options: { signal: AbortSignal }) => {
        signal = options.signal;

        return new Promise<Response>(() => {});
    });
    vi.stubGlobal('fetch', fetch);

    const cleanup = mount('https://example.test/first');
    runTimers();

    cleanup();

    assert.equal(signal?.aborted, true);
});

test('applies the returned metadata to the selected preview', async () => {
    const url = 'https://example.test/preview';
    const preview: LinkPreview = {
        url,
        title: 'Example',
        description: null,
        image_url: null,
        site_name: null,
        is_pending: false,
    };
    vi.stubGlobal(
        'fetch',
        vi.fn(async () => ({
            ok: true,
            json: async () => ({ link_preview: preview }),
        })),
    );

    mount(url);
    runTimers();
    await setImmediate();

    assert.deepEqual(updates, [{ url, preview }]);
});

test.each([
    [
        'the request fails',
        async () => {
            throw new Error('Offline');
        },
    ],
    ['the server rejects the request', async () => ({ ok: false })],
    [
        'the response is malformed',
        async () => ({
            ok: true,
            json: async () => {
                throw new SyntaxError('Invalid JSON');
            },
        }),
    ],
])('leaves the preview unavailable when %s', async (_reason, fetch) => {
    const url = 'https://example.test/preview';
    vi.stubGlobal('fetch', vi.fn(fetch));

    mount(url);
    runTimers();
    await setImmediate();

    assert.deepEqual(updates, [{ url, preview: null }]);
});

function mount(url: string | null): () => void {
    stateIndex = 0;
    useDraftLinkPreview(url);
    const effect = effects.at(-1);

    return effect?.() ?? (() => {});
}

function runTimers(): void {
    for (const callback of timers.values()) {
        callback();
    }

    timers.clear();
}
