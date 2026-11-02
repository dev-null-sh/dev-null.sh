/// <reference types="node" />
import assert from 'node:assert/strict';
import * as React from 'react';
import { afterEach, test, vi } from 'vite-plus/test';

type Effect = {
    readonly deps: unknown[] | undefined;
    readonly cleanup: (() => void) | void;
};

type HookHarness = {
    state: unknown[];
    cursor: number;
    effects: Effect[];
    effectCursor: number;
};

const harnesses = new Set<HookHarness>();

function createHarness(): HookHarness {
    const harness = { state: [], cursor: 0, effects: [], effectCursor: 0 };
    harnesses.add(harness);

    return harness;
}

let activeHarness = createHarness();

const dependenciesChanged = (
    before: unknown[] | undefined,
    after: unknown[] | undefined,
): boolean =>
    before === undefined ||
    after === undefined ||
    before.length !== after.length ||
    before.some((dependency, index) => dependency !== after[index]);

vi.doMock('react', () => ({
    ...React,
    useLayoutEffect: (
        effect: () => void | (() => void),
        dependencies?: unknown[],
    ) => {
        const index = activeHarness.effectCursor++;
        const previous = activeHarness.effects[index];

        if (
            previous === undefined ||
            dependenciesChanged(previous.deps, dependencies)
        ) {
            previous?.cleanup?.();
            activeHarness.effects[index] = {
                deps: dependencies,
                cleanup: effect(),
            };
        }
    },
    useRef: (initial: unknown) => {
        const index = activeHarness.cursor++;
        if (!(index in activeHarness.state)) {
            activeHarness.state[index] = { current: initial };
        }

        return activeHarness.state[index];
    },
    useState: (initial: unknown) => {
        const harness = activeHarness;
        const index = harness.cursor++;
        if (!(index in harness.state)) {
            harness.state[index] =
                typeof initial === 'function'
                    ? (initial as () => unknown)()
                    : initial;
        }

        return [
            harness.state[index],
            (next: unknown) => {
                harness.state[index] =
                    typeof next === 'function'
                        ? (next as (current: unknown) => unknown)(
                              harness.state[index],
                          )
                        : next;
            },
        ];
    },
    useSyncExternalStore: (
        subscribe: (listener: () => void) => () => void,
        snapshot: () => unknown,
    ) => {
        const unsubscribe = subscribe(() => {});
        unsubscribe();

        return snapshot();
    },
}));

const { useImageAttachments } =
    await import('../../resources/js/hooks/use-image-attachments');
const { isReadingImages } =
    await import('../../resources/js/lib/draft-image-reads');

type Draft = {
    key: string;
    images: File[];
    descriptions: string[];
};

function render(draft: Draft, harness = activeHarness) {
    activeHarness = harness;
    activeHarness.cursor = 0;
    activeHarness.effectCursor = 0;

    return useImageAttachments(
        draft.images,
        draft.descriptions,
        (images, descriptions) => {
            draft.images = images;
            draft.descriptions = descriptions;
        },
        draft.key,
    );
}

function unmount(harness: HookHarness): void {
    harness.effects.forEach(({ cleanup }) => cleanup?.());
    harness.effects = [];
}

function deferredImage(name = 'picked.png') {
    let controller!: ReadableStreamDefaultController<Uint8Array>;
    let started!: () => void;
    const startedPromise = new Promise<void>((resolve) => (started = resolve));
    const file = {
        name,
        type: 'image/png',
        stream: () =>
            new ReadableStream<Uint8Array>({
                start(nextController) {
                    controller = nextController;
                    started();
                },
            }),
    } as unknown as File;

    return {
        file,
        started: startedPromise,
        resolve: () => {
            controller.enqueue(new Uint8Array([1]));
            controller.close();
        },
        reject: () =>
            controller.error(
                new DOMException('Unreadable', 'NotReadableError'),
            ),
    };
}

afterEach(() => {
    harnesses.forEach(unmount);
    harnesses.clear();
    activeHarness = createHarness();
});

test('merges a delayed picker result into the latest edited attachment state', async () => {
    const existing = new File(['existing'], 'existing.png', {
        type: 'image/png',
    });
    const added = new File(['added'], 'added.png', { type: 'image/png' });
    const delayed = deferredImage();
    const draft: Draft = {
        key: 'stash:race:merge',
        images: [existing],
        descriptions: ['Initial description'],
    };

    let attachments = render(draft);
    const pick = attachments.pick([delayed.file]);
    assert.equal(attachments.isReading(), true);
    await delayed.started;

    attachments.describe(0, 'Edited description');
    attachments = render(draft);
    attachments.remove(0);
    attachments = render(draft);
    attachments.attach([added]);
    attachments = render(draft);

    delayed.resolve();
    await pick;

    assert.deepEqual(
        draft.images.map((image) => image.name),
        ['added.png', 'picked.png'],
    );
    assert.deepEqual(draft.descriptions, ['', '']);
    assert.equal(isReadingImages(draft.key), false);
});

test('clearing, changing drafts, and unmounting suppress late picker successes and failures', async () => {
    const clearRead = deferredImage('clear.png');
    const draft: Draft = {
        key: 'stash:race:clear',
        images: [],
        descriptions: [],
    };
    let attachments = render(draft);
    const clear = attachments.pick([clearRead.file]);
    await clearRead.started;
    attachments.clear();
    clearRead.resolve();
    await clear;
    assert.deepEqual(draft.images, []);
    assert.equal(attachments.isReading(), false);

    const keyChangeRead = deferredImage('key-change.png');
    attachments = render(draft);
    const keyChange = attachments.pick([keyChangeRead.file]);
    await keyChangeRead.started;
    draft.key = 'stash:race:replacement';
    attachments = render(draft);
    keyChangeRead.reject();
    await keyChange;
    assert.equal(attachments.error, null);
    assert.equal(isReadingImages('stash:race:clear'), false);

    const unmountRead = deferredImage('unmounted.png');
    const unmounted: Draft = {
        key: 'stash:race:unmount',
        images: [],
        descriptions: [],
    };
    attachments = render(unmounted);
    const pending = attachments.pick([unmountRead.file]);
    await unmountRead.started;
    unmount(activeHarness);
    unmountRead.resolve();
    await pending;
    assert.deepEqual(unmounted.images, []);
    assert.equal(isReadingImages(unmounted.key), false);
});

test('two attachment hooks observe a shared draft read synchronously', async () => {
    const delayed = deferredImage('shared.png');
    const first: Draft = {
        key: 'stash:race:shared',
        images: [],
        descriptions: [],
    };
    const second: Draft = { key: first.key, images: [], descriptions: [] };
    const observer: Draft = { key: first.key, images: [], descriptions: [] };

    const firstHarness = createHarness();
    const secondHarness = createHarness();
    const observerHarness = createHarness();
    const firstAttachments = render(first, firstHarness);
    const pick = firstAttachments.pick([delayed.file]);
    assert.equal(firstAttachments.isReading(), true);
    assert.equal(isReadingImages(first.key), true);
    await delayed.started;

    const secondAttachments = render(second, secondHarness);
    assert.equal(secondAttachments.reading, true);
    assert.equal(secondAttachments.isReading(), true);
    assert.equal(await secondAttachments.pick([delayed.file]), undefined);

    const observerAttachments = render(observer, observerHarness);
    assert.equal(observerAttachments.isReading(), true);
    unmount(observerHarness);
    assert.equal(isReadingImages(first.key), true);

    delayed.resolve();
    await pick;
    assert.deepEqual(
        first.images.map((image) => image.name),
        ['shared.png'],
    );
    second.images = first.images;
    second.descriptions = first.descriptions;
    const refreshedSecond = render(second, secondHarness);
    assert.deepEqual(
        refreshedSecond.images.map((image) => image.name),
        ['shared.png'],
    );
});

test('keeps valid images around an oversized selection and displays the error', async () => {
    const draft: Draft = {
        key: 'stash:race:mixed',
        images: [],
        descriptions: [],
    };
    const first = new File(['first'], 'first.png', { type: 'image/png' });
    const oversized = new File(
        [new Uint8Array(10 * 1024 * 1024 + 1)],
        'large.png',
        {
            type: 'image/png',
        },
    );
    const last = new File(['last'], 'last.png', { type: 'image/png' });

    await render(draft).pick([first, oversized, last]);

    const attachments = render(draft);
    assert.deepEqual(
        draft.images.map((image) => image.name),
        ['first.png', 'last.png'],
    );
    assert.deepEqual(
        await Promise.all(draft.images.map((image) => image.text())),
        ['first', 'last'],
    );
    assert.deepEqual(draft.descriptions, ['', '']);
    assert.match(attachments.error!, /10 MB/);
    assert.equal(attachments.isReading(), false);

    attachments.clearError();
    const corrected = render(draft);
    assert.equal(corrected.error, null);
    assert.deepEqual(
        corrected.images.map((image) => image.name),
        ['first.png', 'last.png'],
    );
});

test('rejected files do not consume the last available attachment slot', async () => {
    const existing = new File(['existing'], 'existing.png', {
        type: 'image/png',
    });
    const draft: Draft = {
        key: 'stash:race:capacity',
        images: [existing, existing],
        descriptions: ['first', 'second'],
    };
    const oversized = new File(
        [new Uint8Array(10 * 1024 * 1024 + 1)],
        'large.png',
        {
            type: 'image/png',
        },
    );
    const valid = new File(['valid'], 'valid.png', { type: 'image/png' });

    await render(draft).pick([oversized, valid]);

    assert.deepEqual(
        draft.images.map((image) => image.name),
        ['existing.png', 'existing.png', 'valid.png'],
    );
    assert.deepEqual(draft.descriptions, ['first', 'second', '']);
    assert.match(render(draft).error!, /10 MB/);
});

test('late read errors belong to the originating hook instance', async () => {
    const firstHarness = createHarness();
    const secondHarness = createHarness();
    const first: Draft = {
        key: 'stash:race:first-error',
        images: [],
        descriptions: [],
    };
    const second: Draft = {
        key: 'stash:race:second-error',
        images: [],
        descriptions: [],
    };
    const delayed = deferredImage();
    const pending = render(first, firstHarness).pick([delayed.file]);
    await delayed.started;
    render(second, secondHarness);
    delayed.reject();
    await pending;

    assert.match(render(first, firstHarness).error!, /Could not read/);
    assert.equal(render(second, secondHarness).error, null);
});

test('releases removed and cleared report preview URLs while keeping retained files live', async () => {
    const { objectUrlFor, useImagePreviewCleanup } =
        await import('../../resources/js/components/commit-image-picker');
    const revoke = vi.spyOn(URL, 'revokeObjectURL');
    const first = new File(['first'], 'first.png', { type: 'image/png' });
    const second = new File(['second'], 'second.png', { type: 'image/png' });
    const firstUrl = objectUrlFor(first);
    const secondUrl = objectUrlFor(second);
    const renderCleanup = (images: File[]) => {
        activeHarness.cursor = 0;
        activeHarness.effectCursor = 0;
        useImagePreviewCleanup(images);
    };

    try {
        renderCleanup([first, second]);
        renderCleanup([second]);
        await Promise.resolve();
        assert.deepEqual(revoke.mock.calls, [[firstUrl]]);
        assert.equal(objectUrlFor(second), secondUrl);
        renderCleanup([]);
        await Promise.resolve();
        assert.deepEqual(revoke.mock.calls, [[firstUrl], [secondUrl]]);
        unmount(activeHarness);
        await Promise.resolve();
        assert.equal(revoke.mock.calls.length, 2);
    } finally {
        revoke.mockRestore();
    }
});

test('releases report previews on unmount but survives Strict Mode effect replay', async () => {
    const { objectUrlFor, useImagePreviewCleanup } =
        await import('../../resources/js/components/commit-image-picker');
    const revoke = vi.spyOn(URL, 'revokeObjectURL');
    const file = new File(['image'], 'image.png', { type: 'image/png' });
    const url = objectUrlFor(file);
    const images = [file];
    const renderCleanup = () => {
        activeHarness.cursor = 0;
        activeHarness.effectCursor = 0;
        useImagePreviewCleanup(images);
    };

    try {
        renderCleanup();
        unmount(activeHarness);
        renderCleanup();
        await Promise.resolve();
        assert.equal(revoke.mock.calls.length, 0);
        assert.equal(objectUrlFor(file), url);
        unmount(activeHarness);
        await Promise.resolve();
        assert.deepEqual(revoke.mock.calls, [[url]]);
        const reopenedUrl = objectUrlFor(file);
        assert.notEqual(reopenedUrl, url);
        URL.revokeObjectURL(reopenedUrl);
    } finally {
        revoke.mockRestore();
    }
});
