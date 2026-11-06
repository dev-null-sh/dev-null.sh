import assert from 'node:assert/strict';
import { test } from 'node:test';
import { webViewAppTabs, webViewAppTabTarget } from '../../resources/js/lib/webview-app-tabs.ts';

const ITEMS = [
    { title: 'Log', url: '/log/matthieu' },
    { title: 'Notifications', url: '/notifications', badge: 3 },
    { title: 'Remotes', url: '/remotes', badge: 0 },
    { title: 'Profile', url: '/matthieu' },
    { title: 'Settings', url: '/settings' },
];
const PAGE = { appearance: 'dark' as const, tint: '#3366cc', isModalOpen: false };

await test('only Log, Notifications and Remotes become tabs', () => {
    const { tabs } = webViewAppTabs(ITEMS, '/log/matthieu', PAGE);

    assert.deepEqual(tabs, [
        { id: 'log', title: 'Log', icon: 'commit', url: '/log/matthieu', badge: undefined },
        { id: 'notifications', title: 'Notifications', icon: 'bell', url: '/notifications', badge: 3 },
        { id: 'remotes', title: 'Remotes', icon: 'bubble.left.and.bubble.right', url: '/remotes', badge: 0 },
    ]);
});

await test('selects the tab of the section the page is in', () => {
    assert.equal(webViewAppTabs(ITEMS, '/', PAGE).selected, 'log');
    assert.equal(webViewAppTabs(ITEMS, '/log/matthieu', PAGE).selected, 'log');
    assert.equal(webViewAppTabs(ITEMS, '/notifications', PAGE).selected, 'notifications');
    assert.equal(webViewAppTabs(ITEMS, '/remotes/42', PAGE).selected, 'remotes');
});

await test('hides the bar on pages outside its sections, whose paths only look alike', () => {
    for (const path of ['/matthieu', '/settings', '/logs', '/remotes-archive']) {
        const tabs = webViewAppTabs(ITEMS, path, PAGE);

        assert.equal(tabs.selected, null, path);
        assert.equal(tabs.visible, false, path);
    }
});

await test('hides the bar under a modal dialog, keeping the selected tab', () => {
    const tabs = webViewAppTabs(ITEMS, '/notifications', { ...PAGE, isModalOpen: true });

    assert.equal(tabs.visible, false);
    assert.equal(tabs.selected, 'notifications');
});

await test('follows the theme of the page', () => {
    const tabs = webViewAppTabs(ITEMS, '/', { ...PAGE, appearance: 'light', tint: null });

    assert.equal(tabs.appearance, 'light');
    assert.equal(tabs.tint, null);
});

await test('leaves out Remotes for people without it', () => {
    const { tabs } = webViewAppTabs(ITEMS.filter((item) => item.title !== 'Remotes'), '/', PAGE);

    assert.deepEqual(tabs.map((tab) => tab.id), ['log', 'notifications']);
});

const PAGE_URL = 'https://dev-null.sh/log/matthieu';

await test('a tab leads to a page of the site, keeping its query and hash', () => {
    const target = webViewAppTabTarget('/remotes/42?tab=open#last', PAGE_URL);

    assert.equal(target?.pathname, '/remotes/42');
    assert.equal(target?.search, '?tab=open');
    assert.equal(target?.hash, '#last');
    assert.equal(webViewAppTabTarget('/login', PAGE_URL)?.pathname, '/login');
    assert.equal(webViewAppTabTarget('https://dev-null.sh/notifications', PAGE_URL)?.pathname, '/notifications');
});

await test('a tab never leads off the site, however its URL is written', () => {
    for (const url of [
        'https://example.com/',
        '//example.com',
        '/\\example.com',
        '/.//example.com',
        '/..//example.com',
        '/a/..//example.com',
        '/%2e//example.com',
        '/%2E%2E//example.com',
        'javascript:alert(1)',
        'http://dev-null.sh/',
        'https://dev-null.sh.example.com/',
        'https://dev-null.sh@example.com/',
        'https://[',
    ]) {
        assert.equal(webViewAppTabTarget(url, PAGE_URL), null, url);
    }
});
