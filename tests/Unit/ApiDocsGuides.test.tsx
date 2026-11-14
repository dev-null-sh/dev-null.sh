/// <reference types="node" />

import assert from 'node:assert/strict';
import { renderToString } from 'react-dom/server';
import { test } from 'vite-plus/test';
import {
    DOCS_WIDTH,
    Guide,
    GuideSection,
} from '../../resources/js/components/api-docs/guide';
import { Authentication } from '../../resources/js/pages/api-docs/guides/authentication';
import { Errors } from '../../resources/js/pages/api-docs/guides/errors';
import { Introduction } from '../../resources/js/pages/api-docs/guides/introduction';
import { RateLimits } from '../../resources/js/pages/api-docs/guides/rate-limits';

test('lays a guide out as sections of one article, never as cards', () => {
    const html = renderToString(
        <Guide>
            <GuideSection title="Signing a request" meta="once per device">
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
    assert.match(html, />once per device</);
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

test('lists every error code with its status and the meaning the server gives it', () => {
    const html = renderToString(
        <Errors
            snippets={{}}
            codes={[
                {
                    code: 'invalid_signature',
                    status: 401,
                    meaning: 'The signature does not match.',
                },
                {
                    code: 'commit_unavailable',
                    status: 404,
                    meaning: 'The commit you report is gone.',
                },
            ]}
        />,
    );

    assert.match(html, />2 codes</);
    assert.match(
        html,
        />401<\/td>.*>invalid_signature<\/td>.*The signature does not match\./s,
    );
    assert.match(
        html,
        />404<\/td>.*>commit_unavailable<\/td>.*The commit you report is gone\./s,
    );
});

test('lists every rate limit the server sets, with what it counts per', () => {
    const html = renderToString(
        <RateLimits
            limits={[
                {
                    what: 'Signed requests',
                    limit: 120,
                    per: 'minute, per person',
                },
                { what: 'Issue suggestions', limit: 300, per: 'day' },
            ]}
        />,
    );

    assert.match(
        html,
        />Signed requests<\/td>.*>120<\/td>.*>minute, per person<\/td>/s,
    );
    assert.match(html, />Issue suggestions<\/td>.*>300<\/td>.*>day<\/td>/s);
});

test('shows how each client signs a request, in the tabs the reference uses', () => {
    const html = renderToString(
        <Authentication
            snippets={{
                keygen: { language: 'bash', html: 'ssh-keygen -t ed25519' },
                message: { language: 'text', html: '&lt;nonce&gt;' },
            }}
            signing={[
                {
                    label: 'curl',
                    language: 'bash',
                    code: 'devnull_sign() {}',
                    html: 'devnull_sign() {}',
                },
                {
                    label: 'Laravel',
                    language: 'php',
                    code: 'function devnullHeaders()',
                    html: 'function devnullHeaders()',
                },
            ]}
        />,
    );

    assert.match(html, /role="tablist"[^>]*>.*>curl<.*>Laravel</s);
    assert.match(html, /devnull_sign\(\) \{\}/);
    assert.match(html, />public<.*No signing headers\./s);
    assert.match(html, />bash<.*ssh-keygen -t ed25519/s);
    assert.match(html, />text<.*&lt;nonce&gt;/s);
});
