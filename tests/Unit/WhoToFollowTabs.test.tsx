/// <reference types="node" />
import assert from 'node:assert/strict';
import * as React from 'react';
import type { ReactElement } from 'react';
import { afterAll, afterEach, test, vi } from 'vite-plus/test';

type Props = { value: number; onChange: (value: number) => void };

const initialProps = {
    showsWhoToFollow: true,
    whoToFollowCount: 3,
    refreshesWhoToFollow: true,
};
let props = { ...initialProps };
const reloads: { only?: string[]; onFinish?: () => void }[] = [];

// State lives across renders, and setting it while rendering renders again, as React does.
let slots: unknown[] = [];
let cursor = 0;
let isRendering = false;
let rendersAgain = false;

vi.doMock('react', () => ({
    ...React,
    useEffect: (effect: () => void) => effect(),
    useState: (initial: unknown) => {
        const slot = cursor++;

        if (!(slot in slots)) {
            slots[slot] = typeof initial === 'function' ? initial() : initial;
        }

        return [
            slots[slot],
            (update: unknown) => {
                slots[slot] =
                    typeof update === 'function' ? update(slots[slot]) : update;
                rendersAgain ||= isRendering;
            },
        ];
    },
    useSyncExternalStore: (_subscribe: unknown, read: () => unknown) => read(),
}));
vi.doMock('@inertiajs/react', async () => ({
    ...(await vi.importActual('@inertiajs/react')),
    usePage: () => ({ props }),
    router: {
        flushAll: () => {},
        reload: (options: (typeof reloads)[number]) => reloads.push(options),
        replaceProp: () => {},
    },
}));

const { default: WhoToFollowTabs } =
    await import('../../resources/js/components/who-to-follow-tabs');
const { refreshSharedState } =
    await import('../../resources/js/hooks/use-shared-state');

vi.stubGlobal('document', { cookie: '' });
afterAll(() => vi.unstubAllGlobals());
afterEach(() => {
    props = { ...initialProps };
    reloads.length = 0;
    slots = [];
    hideFromBlock(false);
});

function render(): ReactElement<Props> {
    let tabs: ReactElement<Props>;

    do {
        rendersAgain = false;
        cursor = 0;
        isRendering = true;
        tabs = WhoToFollowTabs() as ReactElement<Props>;
        isRendering = false;
    } while (rendersAgain);

    return tabs;
}

function hideFromBlock(hides = true): void {
    refreshSharedState(new Map([['who-to-follow-hidden', hides]]));
}

test('moves to Hide, and back, when the block on the page is hidden from its own menu', () => {
    assert.equal(render().props.value, 3);

    hideFromBlock();
    assert.equal(render().props.value, 0);

    hideFromBlock(false);
    assert.equal(render().props.value, 3);
});

test('a count picked after hiding the block never shows the count from before', async () => {
    hideFromBlock();
    const shown = [render().props.value];

    render().props.onChange(1);
    await Promise.resolve();
    shown.push(render().props.value);

    props.whoToFollowCount = 1;
    shown.push(render().props.value);

    reloads.at(-1)?.onFinish?.();
    shown.push(render().props.value);

    assert.deepEqual(shown, [0, 1, 1, 1]);
});

test('a count picked again before the first one loads never shows the count from before', async () => {
    hideFromBlock();
    const shown = [render().props.value];

    render().props.onChange(1);
    await Promise.resolve();
    const first = reloads.at(-1)!;
    shown.push(render().props.value);

    render().props.onChange(5);
    await Promise.resolve();
    const second = reloads.at(-1)!;
    shown.push(render().props.value);

    // Cut short by the second reload, which still fires its finish.
    first.onFinish?.();
    shown.push(render().props.value);

    props.whoToFollowCount = 5;
    second.onFinish?.();
    shown.push(render().props.value);

    assert.deepEqual(shown, [0, 1, 5, 5, 5]);
});

test('a visit that leaves the suggestions out forgets they were hidden from the block', () => {
    hideFromBlock();
    props.showsWhoToFollow = false;
    assert.equal(render().props.value, 0);

    props.showsWhoToFollow = true;
    assert.equal(render().props.value, 3);
});
