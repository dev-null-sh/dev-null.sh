/// <reference types="node" />
import assert from 'node:assert/strict';
import { renderToStaticMarkup } from 'react-dom/server';
import { test } from 'vite-plus/test';
import { ProfileLiveButton } from '../../resources/js/components/profile-live-button';

test('offers going live straight from your own profile instead of a menu with one item', () => {
    const html = renderToStaticMarkup(
        <ProfileLiveButton user={{ id: 'me', live: null }} />,
    );

    assert.match(html, /<button[^>]*aria-label="Go live"/);
    assert.doesNotMatch(html, /aria-haspopup|More options/);
});

test('offers ending the stream while you are live', () => {
    const html = renderToStaticMarkup(
        <ProfileLiveButton
            user={{
                id: 'me',
                live: {
                    url: 'https://twitch.tv/me',
                    thumbnail_url: '',
                    ends_at: new Date(Date.now() + 3_600_000).toISOString(),
                },
            }}
        />,
    );

    assert.match(html, /<button[^>]*aria-label="End stream"/);
    assert.doesNotMatch(html, /aria-label="Go live"/);
});
