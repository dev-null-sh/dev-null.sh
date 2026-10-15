/// <reference types="node" />
import assert from 'node:assert/strict';
import { test } from 'node:test';
import { renderToStaticMarkup } from 'react-dom/server';
import { LinkifiedText } from './linkified-text';

void test('links only known handles and preserves escaped text and URL rendering', () => {
    const html = renderToStaticMarkup(
        <LinkifiedText
            text={
                'Thanks @DEV-one (@unknown) <script> mail@dev-one.com https://example.com/@dev-one'
            }
            mentions={['dev-one']}
        />,
    );
    assert.match(html, /href="\/@dev-one"/);
    assert.equal((html.match(/href="\/@dev-one"/g) ?? []).length, 1);
    assert.match(html, /@unknown/);
    assert.match(html, /&lt;script&gt;/);
    assert.match(html, /href="https:\/\/example.com\/@dev-one"/);
    assert.match(html, /noopener noreferrer nofollow ugc/);
    assert.doesNotMatch(html, /href="\/@unknown"/);
});
