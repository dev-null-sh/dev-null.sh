/// <reference types="node" />
import assert from 'node:assert/strict';
import * as React from 'react';
import { afterAll, afterEach, test, vi } from 'vite-plus/test';
import type { LinkPreview as LinkPreviewData } from '../../resources/js/types';

const requested: string[] = [];

vi.doMock('react', () => ({
    ...React,
    useEffect: (effect: () => void | (() => void)) => {
        effect();
    },
    useState: (initial: unknown) => [initial, () => {}],
}));

vi.stubGlobal('fetch', (url: string) => {
    requested.push(url);

    return Promise.resolve({
        ok: true,
        json: () => Promise.resolve({ link_preview: null }),
    });
});

const { renderToString } = await import('react-dom/server');
const { LinkPreview } = await import(
    '../../resources/js/components/link-preview'
);

const SHA = '3f732656b1b97de366bce8ed147077ed11e25248';

function preview(url: string): LinkPreviewData {
    return {
        url,
        title: 'a.php',
        description: null,
        image_url: null,
        site_name: 'GitHub',
        is_pending: false,
    };
}

afterEach(() => {
    requested.length = 0;
});

afterAll(() => vi.unstubAllGlobals());

test('asks for the lines a permalink cites', () => {
    renderToString(
        <LinkPreview
            refreshUrl="/commits/1/link-preview"
            preview={preview(
                `https://github.com/acme/app/blob/${SHA}/src/a.php#L2-L3`,
            )}
        />,
    );

    assert.deepEqual(requested, ['/commits/1/link-preview']);
});

test('asks for nothing when the link cannot have lines', () => {
    for (const url of [
        'https://github.com/acme/app/blob/main/src/a.php#L2-L3',
        `https://github.com/acme/app/blob/${SHA}/src/a.php`,
        'https://laravel.com',
    ]) {
        renderToString(
            <LinkPreview
                refreshUrl="/commits/1/link-preview"
                preview={preview(url)}
            />,
        );
    }

    assert.deepEqual(requested, []);
});

test('asks for nothing when the card is static', () => {
    renderToString(
        <LinkPreview
            refreshUrl="/commits/1/link-preview"
            preview={preview(
                `https://github.com/acme/app/blob/${SHA}/src/a.php#L2-L3`,
            )}
            isStatic
        />,
    );

    assert.deepEqual(requested, []);
});
