/// <reference types="node" />
import assert from 'node:assert/strict';
import * as React from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { afterAll, afterEach, test, vi } from 'vite-plus/test';
import type { LinkPreview as LinkPreviewData } from '../../resources/js/types/commit';

type Effect = () => void | (() => void);
type EffectState = {
    dependencies: readonly unknown[] | undefined;
    effect: Effect;
    cleanup?: () => void;
    shouldRun: boolean;
};

let effectStates: EffectState[] = [];
let states: unknown[] = [];
let stateIndex = 0;
let effectIndex = 0;

vi.doMock('react', () => ({
    ...React,
    useEffect: (effect: Effect, dependencies?: readonly unknown[]) => {
        const index = effectIndex++;
        const previous = effectStates[index];
        const shouldRun =
            previous === undefined ||
            !haveSameDependencies(previous.dependencies, dependencies);

        effectStates[index] = {
            cleanup: previous?.cleanup,
            dependencies,
            effect,
            shouldRun,
        };
    },
    useState: (initial: unknown) => {
        const index = stateIndex++;
        states[index] ??= typeof initial === 'function' ? initial() : initial;

        return [
            states[index],
            (update: unknown) => {
                const next =
                    typeof update === 'function'
                        ? update(states[index])
                        : update;

                if (Object.is(states[index], next)) {
                    return;
                }

                states[index] = next;
            },
        ];
    },
}));

const { LinkPreview } =
    await import('../../resources/js/components/link-preview');

type Timer = { callback: () => void; delay: number };
let nextTimer = 0;
const timers = new Map<number, Timer>();

vi.stubGlobal('window', {
    setTimeout: (callback: () => void, delay: number) => {
        nextTimer++;
        timers.set(nextTimer, { callback, delay });

        return nextTimer;
    },
    clearTimeout: (timer: number) => timers.delete(timer),
});

afterAll(() => vi.unstubAllGlobals());
afterEach(() => {
    cleanupEffects();
    effectStates = [];
    states = [];
    stateIndex = 0;
    effectIndex = 0;
    timers.clear();
    nextTimer = 0;
});

test('retries a rate-limited draft poll instead of hiding its pending card', async () => {
    const fetch = vi
        .fn()
        .mockResolvedValueOnce({
            ok: false,
            status: 429,
            headers: new Headers({ 'Retry-After': '60' }),
        })
        .mockResolvedValueOnce({
            ok: true,
            json: async () => ({
                link_preview: {
                    ...pendingPreview,
                    is_pending: false,
                    title: 'Recovered preview',
                },
            }),
        });
    vi.stubGlobal('fetch', fetch);

    const cleanup = mount();
    await runNextTimer();

    assert.ok(renderPreview().includes('data-test="link-preview-skeleton"'));
    assert.equal(timers.size, 1);
    assert.equal([...timers.values()][0]?.delay, 60000);

    await runNextTimer();

    const markup = renderPreview();

    assert.ok(markup.includes('Recovered preview'));
    cleanup();
});

test('gives up only after the bounded poll cycle remains pending', async () => {
    const fetch = vi.fn().mockResolvedValue({
        ok: true,
        json: async () => ({ link_preview: pendingPreview }),
    });
    vi.stubGlobal('fetch', fetch);

    const cleanup = mount();

    for (let attempt = 0; attempt < 6; attempt++) {
        await runNextTimer();
    }

    assert.equal(fetch.mock.calls.length, 6);
    assert.equal(timers.size, 0);
    const markup = renderPreview(
        pendingPreview,
        '/draft-link-preview?url=https%3A%2F%2Fexample.test%2Farticle',
        <div data-test="draft-preview-unavailable">
            Link preview unavailable
        </div>,
    );

    assert.ok(markup.includes('data-test="draft-preview-unavailable"'));
    runPendingEffects();
    assert.equal(timers.size, 0);
    cleanup();
});

test('starts a fresh poll cycle when a pending preview changes URL', async () => {
    const fetch = vi.fn().mockResolvedValue({
        ok: true,
        json: async () => ({ link_preview: pendingPreview }),
    });
    vi.stubGlobal('fetch', fetch);

    const cleanup = mount();

    for (let attempt = 0; attempt < 6; attempt++) {
        await runNextTimer();
    }

    const nextPreview = {
        ...pendingPreview,
        url: 'https://example.test/another-article',
    };
    const markup = renderPreview(nextPreview);

    assert.ok(markup.includes('data-test="link-preview-skeleton"'));
    const nextCleanup = runPendingEffects();
    assert.equal(timers.size, 1);
    cleanup();
    nextCleanup();
});

test('starts a fresh poll cycle when its refresh URL changes', async () => {
    const fetch = vi.fn().mockResolvedValue({
        ok: true,
        json: async () => ({ link_preview: pendingPreview }),
    });
    vi.stubGlobal('fetch', fetch);

    const cleanup = mount();

    for (let attempt = 0; attempt < 6; attempt++) {
        await runNextTimer();
    }

    const markup = renderPreview(
        pendingPreview,
        '/draft-link-preview?url=https%3A%2F%2Fexample.test%2Farticle&refresh=1',
    );

    assert.ok(markup.includes('data-test="link-preview-skeleton"'));
    const nextCleanup = runPendingEffects();
    assert.equal(timers.size, 1);
    cleanup();
    nextCleanup();
});

test('starts a fresh poll cycle when a preview becomes pending again', async () => {
    const fetch = vi.fn().mockResolvedValue({
        ok: true,
        json: async () => ({ link_preview: pendingPreview }),
    });
    vi.stubGlobal('fetch', fetch);

    const cleanup = mount();

    for (let attempt = 0; attempt < 6; attempt++) {
        await runNextTimer();
    }

    mount({ ...pendingPreview, is_pending: false });

    const markup = renderPreview();

    assert.ok(markup.includes('data-test="link-preview-skeleton"'));
    const nextCleanup = runPendingEffects();
    assert.equal(timers.size, 1);
    cleanup();
    nextCleanup();
});

test('ignores a previous cycle response that completes after a new render', async () => {
    let resolvePreview: (value: unknown) => void;
    const deferredPreview = new Promise((resolve) => {
        resolvePreview = resolve;
    });
    const fetch = vi.fn().mockResolvedValue({
        ok: true,
        json: async () => deferredPreview,
    });
    vi.stubGlobal('fetch', fetch);

    const cleanup = mount();
    await runNextTimer();
    const nextPreview = {
        ...pendingPreview,
        url: 'https://example.test/another-article',
    };

    assert.ok(
        renderPreview(nextPreview).includes(
            'data-test="link-preview-skeleton"',
        ),
    );

    resolvePreview!({
        link_preview: {
            ...pendingPreview,
            is_pending: false,
            title: 'Stale preview',
        },
    });
    await Promise.resolve();
    await Promise.resolve();

    const markup = renderPreview(nextPreview);

    assert.ok(markup.includes('data-test="link-preview-skeleton"'));
    assert.equal(markup.includes('Stale preview'), false);
    cleanup();
});

test('gives up when a rate limit outlasts the visible retry window', async () => {
    const fetch = vi.fn().mockResolvedValue({
        ok: false,
        status: 429,
        headers: new Headers({ 'Retry-After': '61' }),
    });
    vi.stubGlobal('fetch', fetch);

    const cleanup = mount();
    await runNextTimer();

    assert.equal(timers.size, 0);
    const markup = renderPreview(
        pendingPreview,
        undefined,
        <div data-test="draft-preview-unavailable">
            Link preview unavailable
        </div>,
    );

    assert.ok(markup.includes('data-test="draft-preview-unavailable"'));
    cleanup();
});

const pendingPreview: LinkPreviewData = {
    url: 'https://example.test/article',
    title: null,
    description: null,
    image_url: null,
    site_name: null,
    is_pending: true,
};

function mount(
    preview = pendingPreview,
    refreshUrl = '/draft-link-preview?url=https%3A%2F%2Fexample.test%2Farticle',
): () => void {
    renderPreview(preview, refreshUrl);

    return runPendingEffects();
}

function renderPreview(
    preview = pendingPreview,
    refreshUrl = '/draft-link-preview?url=https%3A%2F%2Fexample.test%2Farticle',
    emptyState?: React.ReactNode,
): string {
    stateIndex = 0;
    effectIndex = 0;

    return renderToStaticMarkup(
        LinkPreview({
            refreshUrl,
            preview,
            emptyState,
        }),
    );
}

function runPendingEffects(): () => void {
    for (const effectState of effectStates) {
        if (!effectState.shouldRun) {
            continue;
        }

        effectState.cleanup?.();
        effectState.cleanup = effectState.effect() ?? undefined;
        effectState.shouldRun = false;
    }

    return cleanupEffects;
}

function cleanupEffects(): void {
    for (const effectState of effectStates) {
        effectState.cleanup?.();
        effectState.cleanup = undefined;
    }
}

function haveSameDependencies(
    previous: readonly unknown[] | undefined,
    next: readonly unknown[] | undefined,
): boolean {
    if (previous === undefined || next === undefined) {
        return previous === next;
    }

    return (
        previous.length === next.length &&
        previous.every((dependency, index) =>
            Object.is(dependency, next[index]),
        )
    );
}

async function runNextTimer(): Promise<void> {
    const [timer, value] = timers.entries().next().value as [number, Timer];
    timers.delete(timer);
    value.callback();
    await Promise.resolve();
    await Promise.resolve();
}
