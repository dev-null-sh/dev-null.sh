/// <reference types="node" />
import assert from 'node:assert/strict';
import { test } from 'node:test';
import { renderToStaticMarkup } from 'react-dom/server';
import {
    firstUrl,
    LinkifiedText,
} from '../../resources/js/components/linkified-text';

const url = 'https://www.example.com/a-long-path-to-the-release-notes';

void test('finds the full preview URL outside inline and fenced code', () => {
    const body = [
        '`https://inline.example.com`',
        '```text',
        'https://block.example.com',
        '```',
        `Read ${url}.`,
    ].join('\n');

    assert.equal(firstUrl(body), url);
    assert.equal(firstUrl('`https://inline.example.com`'), null);
});

void test('shortens the displayed preview link while retaining the full URL', () => {
    const markup = renderToStaticMarkup(<LinkifiedText text={url} />);

    assert.ok(markup.includes(`href="${url}"`));
    assert.ok(markup.includes(`title="${url}"`));
    assert.ok(markup.includes('example.com/a-long-path-to-the'));
    assert.ok(markup.includes('…'));
    assert.equal(
        markup
            .replace(/<span aria-hidden="true"[^>]*>.*?<\/span>/g, '')
            .replace(/<[^>]+>/g, ''),
        url,
    );
});
