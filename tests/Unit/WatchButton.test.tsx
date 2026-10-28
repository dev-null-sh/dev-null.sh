/// <reference types="node" />

import assert from 'node:assert/strict';
import { createInertiaApp } from '@inertiajs/react';
import { renderToString } from 'react-dom/server';
import { test } from 'vite-plus/test';
import { WatchButton } from '../../resources/js/components/watch-button';

const user = { id: 'octocat', handle: 'octocat' };

async function render(isFollowing: boolean, isWatching: boolean) {
    const result = await createInertiaApp({
        page: {
            component: 'user/show',
            props: { auth: { user: { id: 'me' } }, errors: {} },
            url: '/@octocat',
            version: null,
            clearHistory: false,
            encryptHistory: false,
            rescuedProps: [],
            rememberedState: {},
            flash: {},
        },
        resolve: () => () => (
            <WatchButton
                user={user}
                isFollowing={isFollowing}
                isWatching={isWatching}
            />
        ),
        render: renderToString,
        setup: ({ App, props }) => <App {...props} />,
    });

    assert.ok(result);
    return result.body;
}

test('offers to watch someone you follow', async () => {
    const html = await render(true, false);

    assert.match(html, /aria-label="Get notified when @octocat commits"/);
    assert.match(html, /aria-pressed="false"/);
});

test('offers to stop watching someone you watch', async () => {
    const html = await render(true, true);

    assert.match(
        html,
        /aria-label="Stop notifications for @octocat&#x27;s commits"/,
    );
    assert.match(html, /aria-pressed="true"/);
});

test('leaves the bell out for someone you do not follow', async () => {
    assert.doesNotMatch(await render(false, false), /data-test="watch-button"/);
});
