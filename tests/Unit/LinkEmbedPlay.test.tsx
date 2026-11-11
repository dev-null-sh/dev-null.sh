/// <reference types="node" />
import assert from 'node:assert/strict';
import { renderToStaticMarkup } from 'react-dom/server';
import { test } from 'vite-plus/test';
import { LinkEmbed } from '../../resources/js/components/link-embed';
import type { Embed, LinkPreview } from '../../resources/js/types';

const preview: LinkPreview = {
    url: 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    title: 'A talk',
    description: null,
    image_url: 'https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg',
    site_name: 'YouTube',
    is_pending: false,
};

const video: Embed = {
    provider: 'youtube',
    kind: 'video',
    src: 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ',
    aspect_ratio: '16 / 9',
    height: null,
};

const audio: Embed = {
    provider: 'spotify',
    kind: 'audio',
    src: 'https://open.spotify.com/embed/track/1',
    aspect_ratio: null,
    height: 152,
};

// The hover and focus styles inside only apply under an element with the `group` class itself.
const reactsToHover = (html: string): boolean =>
    /class="(?:[^"]*\s)?group[\s"]/.test(html);

for (const embed of [video, audio]) {
    test(`plays ${embed.kind} embeds from their own button on a commit`, () => {
        const html = renderToStaticMarkup(
            <LinkEmbed preview={preview} embed={embed} />,
        );

        assert.ok(html.includes('data-test="link-embed-play"'));
        assert.ok(reactsToHover(html));
    });

    test(`shows ${embed.kind} embeds in a quote without a play button that reacts to the pointer`, () => {
        const html = renderToStaticMarkup(
            <LinkEmbed preview={preview} embed={embed} isStatic />,
        );

        assert.ok(!html.includes('data-test="link-embed-play"'));
        assert.ok(!html.includes('<button'));
        assert.ok(!reactsToHover(html));
        assert.ok(html.includes('text-white/60'));
    });
}
