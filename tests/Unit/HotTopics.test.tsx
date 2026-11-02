/// <reference types="node" />
import assert from 'node:assert/strict';
import { createInertiaApp } from '@inertiajs/react';
import { renderToString } from 'react-dom/server';
import { test } from 'vite-plus/test';
import { HotTopics } from '../../resources/js/components/hot-topics';

async function render(names: string[]): Promise<string> {
    const result = await createInertiaApp({
        page: {
            component: 'test',
            props: {
                errors: {},
                hotTopics: names.map((name, index) => ({
                    id: `00000000-0000-4000-8000-${String(index).padStart(12, '0')}`,
                    name,
                })),
            },
            url: '/',
            version: null,
            clearHistory: false,
            encryptHistory: false,
            rescuedProps: [],
            rememberedState: {},
            flash: {},
        },
        resolve: () => HotTopics,
        render: renderToString,
        setup: ({ App, props }) => <App {...props} />,
    });

    assert.ok(result);
    return result.body.replaceAll(/<!--.*?-->/g, '');
}

test('shows topic links without counts or a window caption', async () => {
    const html = await render(['Database indexes']);

    assert.match(
        html,
        /href="\/headlines\/00000000-0000-4000-8000-000000000000"/,
    );
    assert.match(html, /Database indexes/);
    assert.doesNotMatch(html, /commits|Past 8 hours/);
});

test('omits the hot topics section when no topic qualifies', async () => {
    assert.doesNotMatch(await render([]), /<section/);
});
