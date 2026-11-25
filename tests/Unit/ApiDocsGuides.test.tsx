/// <reference types="node" />

import assert from 'node:assert/strict';
import { renderToString } from 'react-dom/server';
import { test } from 'vite-plus/test';
import {
    DOCS_WIDTH,
    Guide,
    GuideSection,
} from '../../resources/js/components/api-docs/guide';
import { Introduction } from '../../resources/js/pages/api-docs/guides/introduction';

test('lays a guide out as sections of one article, never as cards', () => {
    const html = renderToString(
        <Guide>
            <GuideSection title="Signing a request">
                <p>body</p>
            </GuideSection>
        </Guide>,
    );

    assert.match(html, /^<article[^>]*bg-card[^>]*>/);
    assert.match(
        html,
        new RegExp(`^<article[^>]*${DOCS_WIDTH.replace(/[[\]]/g, '\\$&')}`),
    );
    assert.match(html, /^<article[^>]*divide-y/);
    assert.match(html, /<section id="signing-a-request"/);
    assert.doesNotMatch(html.replace(/^<article[^>]*>/, ''), /bg-card/);
    assert.match(
        html,
        /<h2[^>]*><a href="#signing-a-request"[^>]*>Signing a request<\/a>/,
    );
});

test("shows a guide's code in the docs' code blocks, as highlighted by the server", () => {
    const html = renderToString(
        <Introduction
            snippets={{
                firstRequest: {
                    language: 'bash',
                    html: '<span class="hl-keyword">curl</span> https://dev-null.sh/api/v1/users/nunomaduro',
                },
            }}
        />,
    );

    assert.match(html, /<div class="relative isolate my-2 rounded-md/);
    assert.match(html, />bash</);
    assert.match(
        html,
        /<span class="hl-keyword">curl<\/span> https:\/\/dev-null\.sh/,
    );
});

test('asks for JSON on every request, so every error comes back as JSON', () => {
    const html = renderToString(<Introduction snippets={{}} />);

    assert.match(html, />accept<.*Accept: application\/json/s);
});
