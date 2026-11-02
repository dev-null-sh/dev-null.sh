/// <reference types="node" />
import assert from 'node:assert/strict';
import * as React from 'react';
import { afterAll, afterEach, test, vi } from 'vite-plus/test';

const cleanups: Array<() => void> = [];
const icons = [
    { href: 'https://dev-null.sh/favicon.ico' },
    { href: 'https://dev-null.sh/favicon.svg' },
];

vi.doMock('react', () => ({
    ...React,
    useEffect: (effect: () => void | (() => void)) => {
        const cleanup = effect();

        if (cleanup) {
            cleanups.push(cleanup);
        }
    },
}));

vi.stubGlobal('document', {
    querySelectorAll: (selector: string) =>
        selector === 'link[rel="icon"]' ? icons : [],
});

const { useFaviconDot } = await import(
    '../../resources/js/hooks/use-favicon-dot'
);

afterEach(() => {
    for (const cleanup of cleanups.splice(0)) {
        cleanup();
    }
});

afterAll(() => vi.unstubAllGlobals());

test('dots both favicons while something is unread', () => {
    useFaviconDot(true);

    assert.deepEqual(
        icons.map((icon) => icon.href),
        [
            'https://dev-null.sh/favicon-unread.ico',
            'https://dev-null.sh/favicon-unread.svg',
        ],
    );
});

test('puts the plain favicons back once everything is read', () => {
    useFaviconDot(true);
    cleanups.splice(0).forEach((cleanup) => cleanup());
    useFaviconDot(false);

    assert.deepEqual(
        icons.map((icon) => icon.href),
        ['https://dev-null.sh/favicon.ico', 'https://dev-null.sh/favicon.svg'],
    );
    assert.equal(cleanups.length, 0);
});
