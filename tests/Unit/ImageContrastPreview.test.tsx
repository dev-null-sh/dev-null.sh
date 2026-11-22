/// <reference types="node" />
import assert from 'node:assert/strict';
import * as React from 'react';
import type { ReactElement, ReactNode } from 'react';
import { test, vi } from 'vite-plus/test';

// Each tile renders on its own, so one hook store stands in for React's.
const tileNode = {};
let state: unknown[];
let cursor = 0;
vi.doMock('react', () => ({
    ...React,
    useRef: () => ({ current: tileNode }),
    useState: (initial: unknown) => {
        const index = cursor++;
        if (!(index in state)) state[index] = initial;
        return [
            state[index],
            (value: unknown) => {
                state[index] = value;
            },
        ];
    },
}));

// Reading pixels needs a canvas, so the icon is given: a dark logo on a transparent background.
const pixels = new Uint8ClampedArray(400);
for (let i = 0; i < 30; i++) pixels.set([0, 0, 0, 255], i * 4);
const readPixels = vi.fn((_image: unknown, _size: number) => pixels);
vi.doMock('../../resources/js/lib/image-contrast', async () => ({
    ...(await vi.importActual('../../resources/js/lib/image-contrast')),
    readPixels,
}));

// Each tile's own --muted, as its data-theme-scope gives it.
const muted = { light: '#eef0f2', dark: '#232326' };
let theme: 'light' | 'dark';
const getComputedStyle = vi.fn((_element: unknown) => ({
    getPropertyValue: () => ` ${muted[theme]} `,
}));
vi.stubGlobal('getComputedStyle', getComputedStyle);

const { ImageContrastPreview } =
    await import('../../resources/js/components/image-contrast-preview');

type Element = ReactElement<Record<string, unknown>>;
function elements(node: ReactNode): Element[] {
    if (!React.isValidElement(node)) return [];
    const element = node as Element;
    return [
        element,
        ...React.Children.toArray(element.props.children as ReactNode).flatMap(
            elements,
        ),
    ];
}
function text(node: ReactNode): string {
    if (typeof node === 'string') return node;
    return elements(node)
        .flatMap((element) =>
            React.Children.toArray(element.props.children as ReactNode),
        )
        .filter((child) => typeof child === 'string')
        .join(' ');
}

// One mode's tile, before or after its image loads.
function tile(mode: 'light' | 'dark', load: boolean) {
    theme = mode;
    state = [];
    const element = elements(
        ImageContrastPreview({ src: 'blob:icon', size: 20 }),
    ).find((candidate) => candidate.props.theme === mode)!;
    const render = () => {
        cursor = 0;
        return (element.type as (props: unknown) => Element)(element.props);
    };
    if (load) {
        const image = elements(render()).find((e) => e.type === 'img')!;
        (image.props.onLoad as (event: unknown) => void)({ currentTarget: {} });
    }
    const root = render();
    return {
        text: text(root),
        warns: String(root.props.className).includes('--destructive'),
    };
}

test('shows no status until the image loads', () => {
    assert.deepEqual(tile('dark', false), { text: 'Dark mode', warns: false });
});

test('marks only the mode where the icon is hard to see', () => {
    assert.deepEqual(tile('dark', true), {
        text: 'Dark mode Hard to see',
        warns: true,
    });
    assert.deepEqual(tile('light', true), {
        text: 'Light mode Easy to see',
        warns: false,
    });
    // Judged at the size the caller shows it, against the tile's own --muted, not the page's.
    assert.equal(readPixels.mock.calls.at(-1)?.[1], 20);
    assert.equal(getComputedStyle.mock.calls.at(-1)?.[0], tileNode);
});

test('marks a mode where only part of the icon is hard to see', () => {
    // The dark logo with a light-gray part apart from it, which vanishes in light mode.
    const twoParts = pixels.slice();
    for (let i = 70; i < 100; i++) twoParts.set([235, 235, 235, 255], i * 4);
    readPixels.mockImplementationOnce(() => twoParts);
    assert.deepEqual(tile('light', true), {
        text: 'Light mode Partly hard to see',
        warns: true,
    });
});

test('says when an icon is only short of the WCAG minimum', () => {
    // An orange logo: 2:1 on the light background, short of WCAG's 3:1.
    const orange = new Uint8ClampedArray(400);
    for (let i = 0; i < 30; i++) orange.set([255, 138, 76, 255], i * 4);
    readPixels.mockImplementationOnce(() => orange);
    assert.deepEqual(tile('light', true), {
        text: 'Light mode Hard to see (WCAG)',
        warns: true,
    });
});

test('shows no status for an image whose pixels the browser will not let it read', () => {
    readPixels.mockImplementationOnce(() => {
        throw new DOMException('The canvas has been tainted', 'SecurityError');
    });
    assert.deepEqual(tile('dark', true), { text: 'Dark mode', warns: false });
});
