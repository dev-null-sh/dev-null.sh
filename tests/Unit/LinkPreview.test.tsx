/// <reference types="node" />

import assert from 'node:assert/strict';
import { createInertiaApp } from '@inertiajs/react';
import { renderToString } from 'react-dom/server';
import { test } from 'vite-plus/test';
import { LinkPreview } from '../../resources/js/components/link-preview';
import type { LinkPreview as LinkPreviewData } from '../../resources/js/types';

const id = '01a16ffc-740e-7105-82dc-e3a7ed20aaaa';

const commitCard = {
    url: `https://dev-null.sh/@nunomaduro/commits/${id}`,
    title: 'Nuno Maduro (@nunomaduro) on /dev/null: “shipping it”',
    description: 'shipping it',
    image_url: `https://dev-null.sh/@nunomaduro/commits/${id}/og.png?v=1`,
    site_name: '/dev/null',
    is_pending: false,
    embed: null,
} satisfies LinkPreviewData;

async function render(
    preview: LinkPreviewData,
    isStatic = false,
): Promise<string> {
    const result = await createInertiaApp({
        page: {
            component: 'card',
            props: { errors: {}, auth: { user: null, pasteis: null } },
            url: '/',
            version: null,
            clearHistory: false,
            encryptHistory: false,
            rescuedProps: [],
            rememberedState: {},
            flash: {},
        },
        resolve: () => () => (
            <LinkPreview
                refreshUrl="/link-preview"
                preview={preview}
                isStatic={isStatic}
            />
        ),
        render: renderToString,
        setup: ({ App, props }) => <App {...props} />,
    });

    assert.ok(result);

    return result.body;
}

void test('shows a dev/null commit link card as the commit, opened in place', async () => {
    const body = await render(commitCard);

    assert.match(body, new RegExp(`<a[^>]*href="/@nunomaduro/commits/${id}"`));
    assert.doesNotMatch(body, /target="_blank"/);
    assert.match(body, /nunomaduro(<!-- -->)?\/(<!-- -->)?d20aaaa/);
    assert.match(body, />shipping it</);
    assert.doesNotMatch(body, /on \/dev\/null: /);
    assert.doesNotMatch(body, /<img\b/);
});

void test("keeps a dev/null commit's own image as the thumbnail", async () => {
    const image = `https://dev-null.sh/commit-images/${id}`;
    const body = await render({ ...commitCard, image_url: image });

    assert.match(body, new RegExp(`<img[^>]*src="${image}"`));
});

void test('shows a dev/null commit link card without a link inside a quote', async () => {
    const body = await render(commitCard, true);

    assert.doesNotMatch(body, /<a\b/);
    assert.match(body, />shipping it</);
});

void test('leaves every other link card as it was', async () => {
    const body = await render({
        ...commitCard,
        url: 'https://laravel.com',
        title: 'Laravel',
        description: 'The PHP framework for web artisans.',
        image_url: 'https://laravel.com/og.png',
        site_name: 'Laravel',
    });

    assert.match(body, /<a[^>]*href="https:\/\/laravel.com"[^>]*target="_blank"/);
    assert.match(body, />Laravel</);
    assert.match(body, /<img[^>]*src="https:\/\/laravel.com\/og.png"/);
});

void test('waits for a dev/null commit link card without a thumbnail', async () => {
    const pending = await render({ ...commitCard, is_pending: true });
    const other = await render({
        ...commitCard,
        url: 'https://laravel.com',
        is_pending: true,
    });

    assert.match(pending, /data-test="link-preview-skeleton"/);
    assert.doesNotMatch(pending, /size-20/);
    assert.match(other, /size-20/);
});
