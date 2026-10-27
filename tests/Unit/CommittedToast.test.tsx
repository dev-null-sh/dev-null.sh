/// <reference types="node" />
import assert from 'node:assert/strict';
import * as React from 'react';
import type { ReactElement } from 'react';
import { test, vi } from 'vite-plus/test';

type LinkProps = {
    href: { url: string; method: string };
    draggable: boolean;
    onPointerDown: (event: { clientX: number; clientY: number }) => void;
    onClick: (event: {
        detail: number;
        clientX: number;
        clientY: number;
        preventDefault: () => void;
    }) => void;
    onStart: () => void;
};

const dismissed: unknown[] = [];

vi.doMock('react', () => ({
    ...React,
    useRef: (initial: unknown) => ({ current: initial }),
}));
vi.doMock('sonner', () => ({
    toast: { dismiss: (id: unknown) => dismissed.push(id) },
}));

const { CommittedToast } =
    await import('../../resources/js/components/committed-toast');

function render(): LinkProps {
    return (
        CommittedToast({
            committed: {
                id: '019a0000-0000-7000-8000-00000c5efedb',
                body: 'Ship it :rocket:',
                user: { handle: 'ada' },
            },
            toastId: 'committed-019a0000-0000-7000-8000-00000c5efedb',
        }) as ReactElement<LinkProps>
    ).props;
}

function click(
    link: LinkProps,
    at: { clientX: number; clientY: number; detail?: number },
): boolean {
    let prevented = false;

    link.onClick({
        detail: 1,
        ...at,
        preventDefault: () => {
            prevented = true;
        },
    });

    return prevented;
}

test('links to the page of the commit just posted', () => {
    const link = render();

    assert.deepEqual(link.href, {
        url: '/@ada/commits/019a0000-0000-7000-8000-00000c5efedb',
        method: 'get',
    });
    assert.equal(link.draggable, false);
});

test('opens the commit when the toast is clicked', () => {
    const link = render();

    link.onPointerDown({ clientX: 100, clientY: 40 });

    assert.equal(click(link, { clientX: 102, clientY: 41 }), false);
});

test('stays put when the toast is swiped away', () => {
    const link = render();

    link.onPointerDown({ clientX: 100, clientY: 40 });

    assert.equal(click(link, { clientX: 180, clientY: 40 }), true);
});

test('opens the commit from the keyboard after an abandoned swipe', () => {
    const link = render();

    link.onPointerDown({ clientX: 100, clientY: 40 });

    assert.equal(click(link, { clientX: 0, clientY: 0, detail: 0 }), false);
});

test('dismisses the toast once it opens the commit', () => {
    render().onStart();

    assert.deepEqual(dismissed, [
        'committed-019a0000-0000-7000-8000-00000c5efedb',
    ]);
});
