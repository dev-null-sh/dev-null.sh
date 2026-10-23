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
