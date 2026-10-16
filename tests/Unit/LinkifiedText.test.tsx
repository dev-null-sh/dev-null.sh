/// <reference types="node" />
import assert from 'node:assert/strict';
import { test } from 'node:test';
import { renderToStaticMarkup } from 'react-dom/server';
import { LinkifiedText } from '../../resources/js/components/linkified-text';

void test('keeps stable mention links alongside shortened URLs and their full copyable text', () => {
    const id = '019a2b3c-4d5e-7f60-8a9b-0c1d2e3f4a5b';
    const url = 'https://www.example.com/docs/a-very-long-path-that-is-shortened';
    const text = `Thanks @ORIGINAL! See ${url} and @futuredev.`;
    const html = renderToStaticMarkup(
        <LinkifiedText text={text} mentions={{ original: id }} />,
    );

    assert.ok(html.includes(`href="/mentions/${id}"`));
    assert.ok(html.includes('>@ORIGINAL</a>'));
    assert.equal(html.match(/data-test="mention"/g)?.length, 1);
    assert.ok(html.includes(`href="${url}"`));
    assert.ok(html.includes(`title="${url}"`));
    assert.ok(html.includes('aria-hidden="true"'));
    assert.equal(html.replace(/<[^>]*>/g, '').replace('…', ''), text);
});

void test('leaves mentions without captured identities as plain text', () => {
    const html = renderToStaticMarkup(
        <LinkifiedText text="Thanks @original!" mentions={null} />,
    );

    assert.equal(html, 'Thanks @original!');
});
