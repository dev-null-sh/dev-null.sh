/// <reference types="node" />

import assert from 'node:assert/strict';
import { renderToString } from 'react-dom/server';
import { test } from 'vite-plus/test';
import { LinkPreview } from '../../resources/js/components/link-preview';
import type { LinkPreview as LinkPreviewData } from '../../resources/js/types';

const preview = (snippet: LinkPreviewData['snippet']): LinkPreviewData => ({
    url: 'https://github.com/acme/app/blob/3f732656b1b97de366bce8ed147077ed11e25248/src/a.php#L2-L3',
    title: 'a.php at 3f73265',
    description: null,
    image_url: null,
    site_name: 'GitHub',
    is_pending: false,
    embed: null,
    snippet,
});

test('shows the cited lines instead of the card, titled with the path and lines', () => {
    const html = renderToString(
        <LinkPreview
            refreshUrl="/commits/1/link-preview"
            preview={preview({
                path: 'src/a.php',
                start_line: 2,
                end_line: 3,
                cited_end_line: 3,
                html: '<span class="hl-gutter">2</span>one\n<span class="hl-gutter">3</span>two',
            })}
        />,
    );

    assert.match(html, /data-test="code-snippet"/);
    assert.match(html, /src\/a\.php#L2-L3/);
    assert.match(html, /<span class="hl-gutter">3<\/span>two/);
    assert.doesNotMatch(html, /data-test="link-preview"/);
});

test('names a single line once', () => {
    const html = renderToString(
        <LinkPreview
            refreshUrl="/commits/1/link-preview"
            preview={preview({
                path: 'a.txt',
                start_line: 7,
                end_line: 7,
                cited_end_line: 7,
                html: '<span class="hl-gutter">7</span>seven',
            })}
        />,
    );

    assert.match(html, /a\.txt#L7</);
});

test('says when only the first lines of a longer range are shown', () => {
    const html = renderToString(
        <LinkPreview
            refreshUrl="/commits/1/link-preview"
            preview={preview({
                path: 'a.txt',
                start_line: 1,
                end_line: 30,
                cited_end_line: 60,
                html: '<span class="hl-gutter">1</span>one',
            })}
        />,
    );

    assert.match(html, /a\.txt#L1-L30 of L1-L60/);
});

test('keeps the card when there is no snippet', () => {
    const html = renderToString(
        <LinkPreview
            refreshUrl="/commits/1/link-preview"
            preview={preview(null)}
        />,
    );

    assert.match(html, /data-test="link-preview"/);
    assert.doesNotMatch(html, /data-test="code-snippet"/);
});
