/// <reference types="node" />
import assert from 'node:assert/strict';
import * as React from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { test, vi } from 'vite-plus/test';

vi.doMock('@inertiajs/react', () => ({
    Link: ({
        href,
        prefetch,
        children,
    }: {
        href: { url: string };
        prefetch: boolean;
        children: React.ReactNode;
    }) => (
        <a href={href.url} data-prefetch={String(prefetch)}>
            {children}
        </a>
    ),
}));

const { PageTabs } = await import('../../resources/js/components/page-tabs');
const { settingsTabs } =
    await import('../../resources/js/layouts/settings/tabs');

test('the terminal settings tab is never prefetched, so opening it always syncs the SSH keys', () => {
    const html = renderToStaticMarkup(
        <PageTabs
            label="Settings"
            tabs={settingsTabs.map((tab) => ({ ...tab, isActive: false }))}
        />,
    );

    const prefetched = Object.fromEntries(
        [...html.matchAll(/href="([^"]+)" data-prefetch="(\w+)"/gu)].map(
            ([, url, prefetch]) => [url, prefetch],
        ),
    );

    assert.deepEqual(prefetched, {
        '/settings/profile': 'true',
        '/settings/appearance': 'true',
        '/settings/terminal': 'false',
        '/settings/safety': 'true',
        '/settings/devices': 'true',
    });
});
