/// <reference types="node" />
import assert from 'node:assert/strict';
import { test } from 'node:test';
import { renderToStaticMarkup } from 'react-dom/server';
import { LiveStreamCard } from '../../resources/js/components/live-stream-card';

const user = { name: 'Octocat' };

void test('a stream on /dev/null plays right in the card', () => {
    const html = renderToStaticMarkup(
        <LiveStreamCard
            user={user}
            stream={{
                url: 'https://customer-abc.cloudflarestream.com/input-1/iframe',
                thumbnail_url:
                    'https://customer-abc.cloudflarestream.com/input-1/thumbnails/thumbnail.jpg',
                embed_url:
                    'https://customer-abc.cloudflarestream.com/input-1/iframe',
            }}
        />,
    );

    assert.match(
        html,
        /<iframe src="https:\/\/customer-abc\.cloudflarestream\.com\/input-1\/iframe"/,
    );
    assert.match(html, /on \/dev\/null/);
    assert.doesNotMatch(html, /live-stream-watch/);
});

void test('a YouTube stream sends the viewer to YouTube', () => {
    const html = renderToStaticMarkup(
        <LiveStreamCard
            user={user}
            stream={{
                url: 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                thumbnail_url:
                    'https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault_live.jpg',
                embed_url: null,
            }}
        />,
    );

    assert.doesNotMatch(html, /<iframe/);
    assert.match(html, /youtube\.com/);
    assert.match(html, /data-test="live-stream-watch"/);
});

void test('a Twitch stream sends the viewer to Twitch', () => {
    const html = renderToStaticMarkup(
        <LiveStreamCard
            user={user}
            stream={{
                url: 'https://www.twitch.tv/octocat',
                thumbnail_url:
                    'https://static-cdn.jtvnw.net/previews-ttv/live_user_octocat-1280x720.jpg',
                embed_url: null,
            }}
        />,
    );

    assert.doesNotMatch(html, /<iframe/);
    assert.match(html, /twitch\.tv/);
    assert.doesNotMatch(html, /youtube\.com/);
    assert.match(html, /data-test="live-stream-watch"/);
});
