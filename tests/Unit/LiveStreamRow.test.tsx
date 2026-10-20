/// <reference types="node" />
import assert from 'node:assert/strict';
import { renderToStaticMarkup } from 'react-dom/server';
import { test } from 'vite-plus/test';
import { LiveStreamRow } from '../../resources/js/components/live-stream-card';

const stream = {
    url: 'https://www.youtube.com/watch?v=abc123',
    thumbnail_url: 'https://i.ytimg.com/vi/abc123/hqdefault_live.jpg',
};

test('live stream row links out to the stream in a new tab', () => {
    const html = renderToStaticMarkup(
        <LiveStreamRow user={{ name: 'Nuno' }} stream={stream} />,
    );

    assert.match(
        html,
        /^<a [^>]*href="https:\/\/www\.youtube\.com\/watch\?v=abc123"/,
    );
    assert.match(html, /target="_blank"/);
    assert.match(html, /rel="noopener noreferrer"/);
    assert.match(html, /data-test="live-stream-row"/);
});

test('live stream row shows the thumbnail, the LIVE label, where it streams and the call to watch', () => {
    const html = renderToStaticMarkup(
        <LiveStreamRow user={{ name: 'Nuno' }} stream={stream} />,
    );

    assert.match(
        html,
        /src="https:\/\/i\.ytimg\.com\/vi\/abc123\/hqdefault_live\.jpg"/,
    );
    assert.match(html, /data-test="live-badge"/);
    assert.match(html, /streaming · youtube\.com/);
    assert.match(html.replace(/<[^>]+>/g, ''), /Nuno is live/);
    assert.match(html, /Watch now/);
});

test('live stream row shortens a long name but always says the person is live', () => {
    const name = 'Prof. Berta McDermott-Vandervort of the Laravel Core Team';
    const html = renderToStaticMarkup(
        <LiveStreamRow user={{ name }} stream={stream} />,
    );

    assert.match(html, new RegExp(`<span class="truncate">${name}</span>`));
    assert.match(html, /<span class="shrink-0"> is live<\/span>/);
    assert.match(html, new RegExp(`title="${name} is live"`));
});
