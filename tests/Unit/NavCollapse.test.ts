import assert from 'node:assert/strict';
import { test } from 'node:test';
import { runNavCollapse } from '../../resources/js/lib/nav-collapse.ts';

void test('holds notification and commit rows during nav reflow and releases detached anchors', () => {
    const names = [
        'window',
        'document',
        'addEventListener',
        'removeEventListener',
        'requestAnimationFrame',
        'cancelAnimationFrame',
    ] as const;
    const originals = names.map((name) =>
        Object.getOwnPropertyDescriptor(globalThis, name),
    );
    const frames = new Map<number, FrameRequestCallback>();
    const dataset: Record<string, string> = {};
    let nextFrame = 0;
    let scrollY = 1000;
    let offset = 0;
    let connected = true;
    let nestedReads = 0;
    let marker = '[data-notification-group]';
    const anchor = {
        get isConnected() {
            return connected;
        },
        parentElement: { closest: () => null },
        querySelectorAll: () => [],
        getBoundingClientRect: () => ({
            top: 1400 + offset - scrollY,
            bottom: 1500 + offset - scrollY,
        }),
    };
    const nested = {
        parentElement: { closest: () => anchor },
        getBoundingClientRect: () => {
            nestedReads++;
            return { top: 450, bottom: 475 };
        },
    };
    const content = {
        querySelectorAll: (selector: string) =>
            selector.split(',').some((part) => part.trim() === marker)
                ? [anchor, nested]
                : [],
    };
    const frame = (now = performance.now()) => {
        const callbacks = [...frames.values()];
        frames.clear();
        callbacks.forEach((callback) => callback(now));
    };

    Object.assign(globalThis, {
        window: {
            get scrollY() {
                return scrollY;
            },
            innerHeight: 900,
            matchMedia: () => ({ matches: false }),
            clearTimeout: () => undefined,
            setTimeout: () => 1,
            scrollBy: (_x: number, y: number) => {
                scrollY += y;
            },
        },
        document: {
            documentElement: { dataset },
            querySelector: (selector: string) =>
                selector === '[data-nav-content]' ? content : null,
        },
        addEventListener: () => undefined,
        removeEventListener: () => undefined,
        requestAnimationFrame: (callback: FrameRequestCallback) => {
            frames.set(++nextFrame, callback);
            return nextFrame;
        },
        cancelAnimationFrame: (id: number) => frames.delete(id),
    });

    try {
        for (marker of ['[data-notification-group]', '[data-commit]']) {
            delete dataset.navSliding;
            scrollY = 1000;
            offset = 0;
            runNavCollapse(false, () => undefined);
            assert.equal(dataset.navPinning, '');

            offset = 64;
            frame();
            assert.equal(scrollY, 1064);
            assert.equal(anchor.getBoundingClientRect().top, 400);
            assert.equal(nestedReads, 0);

            connected = false;
            frame();
            assert.equal(dataset.navPinning, undefined);
            assert.equal(frames.size, 0);
            connected = true;
        }

        delete dataset.navSliding;
        scrollY = 0;
        runNavCollapse(false, () => undefined);
        assert.equal(dataset.navPinning, undefined);
        assert.equal(frames.size, 0);
    } finally {
        names.forEach((name, index) => {
            const descriptor = originals[index];
            if (descriptor) {
                Object.defineProperty(globalThis, name, descriptor);
            } else {
                Reflect.deleteProperty(globalThis, name);
            }
        });
    }
});
