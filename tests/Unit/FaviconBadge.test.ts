import assert from 'node:assert/strict';
import { test } from 'node:test';
import {
    faviconBadgeLabel,
    showFaviconBadge,
    withFaviconBadge,
} from '../../resources/js/lib/favicon-badge.ts';

const icon = '<svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0"/></svg>';

void test('labels the badge with the unread count, capped at 9+', () => {
    assert.equal(faviconBadgeLabel(0), null);
    assert.equal(faviconBadgeLabel(-1), null);
    assert.equal(faviconBadgeLabel(3), '3');
    assert.equal(faviconBadgeLabel(9), '9');
    assert.equal(faviconBadgeLabel(10), '9+');
    assert.equal(faviconBadgeLabel(250), '9+');
});

void test('draws the badge over the icon only when something is unread', () => {
    assert.equal(withFaviconBadge(icon, 0), icon);

    const badged = withFaviconBadge(icon, 12);

    assert.ok(
        badged.startsWith(
            '<svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0"/><circle',
        ),
    );
    assert.ok(badged.endsWith('>9+</text></svg>'));
});

void test('puts the badge on the tab icon and takes it off once everything is read', async () => {
    const link = { href: 'https://dev-null.sh/favicon.svg' };
    const fetched: string[] = [];

    Object.assign(globalThis, {
        document: { querySelector: () => link },
        fetch: (url: string) => {
            fetched.push(url);

            return Promise.resolve({ text: () => Promise.resolve(icon) });
        },
    });

    await showFaviconBadge(2);
    assert.ok(link.href.startsWith('data:image/svg+xml,'));
    assert.ok(decodeURIComponent(link.href).endsWith('>2</text></svg>'));

    await showFaviconBadge(5);
    assert.ok(decodeURIComponent(link.href).endsWith('>5</text></svg>'));

    await showFaviconBadge(0);
    assert.equal(link.href, 'https://dev-null.sh/favicon.svg');
    assert.deepEqual(fetched, ['https://dev-null.sh/favicon.svg']);
});

void test('tries the tab icon again after its first fetch fails', async () => {
    // A copy of the module of its own, since the icon fetched above stays cached in the shared one.
    const fresh = '../../resources/js/lib/favicon-badge.ts?retry';
    const { showFaviconBadge: show } = (await import(
        fresh
    )) as typeof import('../../resources/js/lib/favicon-badge.ts');
    const link = { href: 'https://dev-null.sh/favicon.svg' };
    let isOnline = false;

    Object.assign(globalThis, {
        document: { querySelector: () => link },
        fetch: () =>
            isOnline
                ? Promise.resolve({ text: () => Promise.resolve(icon) })
                : Promise.reject(new TypeError('Failed to fetch')),
    });

    await show(0);
    await show(1);
    assert.equal(link.href, 'https://dev-null.sh/favicon.svg');

    isOnline = true;
    await show(1);
    assert.ok(decodeURIComponent(link.href).endsWith('>1</text></svg>'));
});
