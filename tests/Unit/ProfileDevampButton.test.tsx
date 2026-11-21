/// <reference types="node" />
import assert from 'node:assert/strict';
import * as React from 'react';
import type { ReactElement } from 'react';
import { beforeEach, test, vi } from 'vite-plus/test';

type Element = ReactElement<Record<string, unknown>>;
type Visit = {
    only: string[];
    onSuccess: (page: { props: { playlist?: unknown } }) => void;
    onFinish: () => void;
};

const slots: unknown[] = [];
let cursor = 0;
const visits: Visit[] = [];
const openDevamp = vi.fn(() => Promise.resolve());
const preloadDevamp = vi.fn();
const toastError = vi.fn();

vi.doMock('react', () => ({
    ...React,
    useState: (initial: unknown) => {
        const index = cursor++;

        if (!(index in slots)) {
            slots[index] = initial;
        }

        return [
            slots[index],
            (value: unknown) => {
                slots[index] = value;
            },
        ];
    },
    useRef: (initial: unknown) => {
        const index = cursor++;

        if (!(index in slots)) {
            slots[index] = { current: initial };
        }

        return slots[index];
    },
}));
vi.doMock('@inertiajs/react', () => ({
    router: { reload: (visit: Visit) => visits.push(visit) },
}));
vi.doMock('sonner', () => ({ toast: { error: toastError } }));
vi.doMock('@/lib/devamp', () => ({ openDevamp, preloadDevamp }));

const { ProfileDevampButton } =
    await import('../../resources/js/components/profile-devamp-button');

const track = {
    artist: 'Broey.',
    title: 'Paradise',
    duration: 143,
    url: 'https://api.audius.co/v1/tracks/emKAp/stream?app_name=devnull',
    link: 'https://audius.co/broeybeats/paradise',
};

function render(playlist?: (typeof track)[]): Element {
    cursor = 0;

    return ProfileDevampButton({ handle: 'byte', playlist }) as Element;
}

function trigger(element: Element, handler: string, focusVisible = true) {
    (element.props[handler] as (event: unknown) => void)({
        currentTarget: { matches: (selector: string) => selector === ':focus-visible' && focusVisible },
    });
}

beforeEach(() => {
    slots.length = 0;
    visits.length = 0;
    openDevamp.mockClear();
    preloadDevamp.mockClear();
    toastError.mockClear();
});

test('loads Devamp and the playlist once while the pointer is on the button or it has keyboard focus', () => {
    trigger(render(), 'onPointerEnter');
    trigger(render(), 'onFocus');
    trigger(render(), 'onFocus', false);

    assert.equal(preloadDevamp.mock.calls.length, 2);
    assert.equal(visits.length, 1);
    assert.deepEqual(visits[0].only, ['playlist']);
    assert.equal(render().props['aria-busy'], false);

    visits[0].onSuccess({ props: { playlist: [track] } });
    visits[0].onFinish();
    trigger(render([track]), 'onPointerEnter');

    assert.equal(openDevamp.mock.calls.length, 0);
    assert.equal(preloadDevamp.mock.calls.length, 3);
    assert.equal(visits.length, 1);
});

test('plays a loaded playlist straight from the click', () => {
    trigger(render([track]), 'onClick');

    assert.deepEqual(openDevamp.mock.calls, [[[track]]]);
    assert.equal(visits.length, 0);
});

test('waits for the playlist when clicked before it loaded', () => {
    trigger(render(), 'onPointerEnter');
    trigger(render(), 'onClick');
    trigger(render(), 'onClick');

    assert.equal(render().props['aria-busy'], true);
    assert.equal(visits.length, 1);

    visits[0].onSuccess({ props: { playlist: [track] } });
    visits[0].onFinish();

    assert.deepEqual(openDevamp.mock.calls, [[[track]]]);
    assert.equal(render().props['aria-busy'], false);
});

test('fetches an empty playlist again and says so when it still has nothing to play', () => {
    trigger(render([]), 'onPointerEnter');
    trigger(render([]), 'onClick');
    visits[0].onSuccess({ props: { playlist: [] } });
    visits[0].onFinish();
    trigger(render(), 'onClick');
    visits[1].onSuccess({ props: {} });
    visits[1].onFinish();

    assert.equal(visits.length, 2);
    assert.equal(openDevamp.mock.calls.length, 0);
    assert.deepEqual(toastError.mock.calls, [
        ["@byte's playlist has nothing to play right now."],
        ["@byte's playlist has nothing to play right now."],
    ]);
});

test('does not start playing later when loading the playlist failed', () => {
    trigger(render(), 'onClick');
    visits[0].onFinish();
    trigger(render(), 'onPointerEnter');
    visits[1].onSuccess({ props: { playlist: [track] } });
    visits[1].onFinish();

    assert.equal(openDevamp.mock.calls.length, 0);
});
