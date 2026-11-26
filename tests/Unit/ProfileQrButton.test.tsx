/// <reference types="node" />

import assert from 'node:assert/strict';
import { renderToString } from 'react-dom/server';
import { test } from 'vite-plus/test';
import { ProfileQrButton } from '../../resources/js/components/profile-qr-button';

test('renders the QR code button with the dialog closed', () => {
    const html = renderToString(
        <ProfileQrButton handle="octocat" href="/@octocat" />,
    );

    assert.match(html, /data-test="profile-qr-button"/);
    assert.match(html, /aria-label="Show a QR code for @octocat&#x27;s profile"/);
    assert.doesNotMatch(html, /data-test="profile-qr-dialog"/);
});
