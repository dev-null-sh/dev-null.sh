/// <reference types="node" />

import assert from 'node:assert/strict';
import { test } from 'node:test';
import { createInertiaApp } from '@inertiajs/react';
import { renderToString } from 'react-dom/server';
import { QuotedCommit } from '../../resources/js/components/quoted-commit';
import type { QuotedCommit as QuotedCommitData } from '../../resources/js/types';

const commit = {
    id: 'quoted',
    user_id: 'author',
    body: "I'm leaking the Algorithm @nunomaduro\n```php\nreturn round($score, 6);\n```",
    body_segments: [
        { type: 'text', text: "I'm leaking the Algorithm @nunomaduro\n" },
        {
            type: 'block',
            language: 'php',
            html: '<span class="hl-keyword">return</span> round($score, 6);',
        },
    ],
    status: 'approved',
    created_at: '2026-10-16T11:51:00.000000Z',
    user: { id: 'author', name: 'Matthieu Napoli', username: 'mnapoli' },
} as QuotedCommitData;

async function render(isPreview: boolean): Promise<string> {
    const result = await createInertiaApp({
        page: {
            component: 'quote',
            props: { errors: {} },
            url: '/@author/quote',
            version: null,
            clearHistory: false,
            encryptHistory: false,
            rescuedProps: [],
            rememberedState: {},
            flash: {},
        },
        resolve: () => () => (
            <QuotedCommit commit={commit} isPreview={isPreview} />
        ),
        render: renderToString,
        setup: ({ App, props }) => <App {...props} />,
    });

    assert.ok(result);

    return result.body;
}

for (const isPreview of [false, true]) {
    void test(`renders a quoted code block highlighted ${isPreview ? 'in the composer' : 'in the log'}`, async () => {
        const body = await render(isPreview);

        assert.match(body, /<pre[^>]*><span class="hl-keyword">return<\/span>/);
        assert.doesNotMatch(body, /```/);
    });
}

void test('keeps the quote a single link', async () => {
    const body = await render(false);

    assert.equal(body.match(/<a\b/g)?.length, 1);
    assert.doesNotMatch(body, /<button\b/);
});
