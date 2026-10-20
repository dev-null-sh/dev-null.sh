/// <reference types="node" />
import assert from 'node:assert/strict';
import { renderToStaticMarkup } from 'react-dom/server';
import { test } from 'vite-plus/test';
import { GifEmbed } from '../../resources/js/components/gif-embed';
import { LinkEmbed } from '../../resources/js/components/link-embed';
import type { Embed, LinkPreview } from '../../resources/js/types/commit';

const preview: LinkPreview = {
    url: 'https://example.com/cat.gif',
    title: null,
    description: null,
    image_url: 'https://example.com/untrusted-thumbnail.gif',
    site_name: null,
    is_pending: false,
};

const embed: Embed = {
    provider: 'GIF',
    kind: 'gif',
    src: 'https://example.com/cat.gif',
    aspect_ratio: '1 / 1',
    height: null,
    poster: null,
};

test('waits until hydration and visibility before requesting animated content', () => {
    const html = renderToStaticMarkup(
        <LinkEmbed preview={preview} embed={embed} />,
    );

    assert.ok(html.includes('data-test="gif-embed"'));
    assert.ok(html.includes('aspect-square'));
    assert.ok(html.includes('aria-label="Play GIF"'));
    assert.ok(html.includes('href="https://example.com/cat.gif"'));
    assert.ok(!html.includes('<iframe'));
    assert.ok(!html.includes('<img'));
    assert.ok(!html.includes('untrusted-thumbnail'));
});

test('pending and verified cards reserve the same image box', () => {
    const pending = renderToStaticMarkup(
        <GifEmbed preview={{ ...preview, is_pending: true }} />,
    );
    const verified = renderToStaticMarkup(
        <GifEmbed preview={preview} embed={embed} />,
    );
    const box = /class="[^"]*aspect-square[^"]*"/;

    assert.equal(pending.match(box)?.[0], verified.match(box)?.[0]);
    assert.ok(pending.includes('Loading GIF'));
    assert.ok(!pending.includes('<img'));
    assert.ok(!pending.includes('<button'));
});

test('an unverified GIF remains a source link without fetching the image', () => {
    const html = renderToStaticMarkup(<GifEmbed preview={preview} />);

    assert.ok(html.includes('GIF preview unavailable'));
    assert.ok(html.includes('href="https://example.com/cat.gif"'));
    assert.ok(!html.includes('<img'));
    assert.ok(!html.includes('<button'));
});

test('static cards have no playback or navigation controls', () => {
    const html = renderToStaticMarkup(
        <GifEmbed preview={preview} embed={embed} isStatic />,
    );

    assert.ok(html.includes('aspect-square'));
    assert.ok(!html.includes('<img'));
    assert.ok(!html.includes('<button'));
    assert.ok(!html.includes('<a '));
});

test('only the explicit static poster can load before playback', () => {
    const html = renderToStaticMarkup(
        <GifEmbed
            preview={preview}
            embed={{
                ...embed,
                poster: 'https://media.giphy.com/media/cat/giphy_s.gif',
            }}
        />,
    );

    assert.ok(html.includes('data-test="gif-poster"'));
    assert.ok(html.includes('referrerPolicy="no-referrer"'));
    assert.ok(!html.includes('data-test="gif-animation"'));
    assert.ok(!html.includes('untrusted-thumbnail'));
});

test('arbitrary GIF hosts have no automatic thumbnail or image request', () => {
    const html = renderToStaticMarkup(
        <GifEmbed preview={preview} embed={{ ...embed, poster: 'https://example.com/poster.gif' }} />,
    );

    assert.ok(html.includes('aria-label="Play GIF"'));
    assert.ok(html.includes('External GIF'));
    assert.ok(!html.includes('<img'));
    assert.ok(!html.includes('poster.gif'));
});
