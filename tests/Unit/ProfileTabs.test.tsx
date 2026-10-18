/// <reference types="node" />
import assert from 'node:assert/strict';
import type { InertiaLinkProps } from '@inertiajs/react';
import { renderToString } from 'react-dom/server';
import { beforeEach, test, vi } from 'vite-plus/test';
import type { Profile } from '../../resources/js/types';

const links: InertiaLinkProps[] = [];

vi.doMock('@inertiajs/react', async () => ({
    ...(await vi.importActual('@inertiajs/react')),
    Link: (props: InertiaLinkProps) => {
        links.push(props);

        return null;
    },
}));

for (const component of [
    'activity-graph',
    'activity-streaks',
    'achievements-shelf',
    'achievements-dialog',
    'commit-feed',
    'follow-button',
    'profile-share-button',
    'user-safety-menu',
]) {
    vi.doMock(`@/components/${component}`, async () =>
        Object.fromEntries(
            Object.keys(await vi.importActual(`@/components/${component}`)).map(
                (name) => [name, () => null],
            ),
        ),
    );
}

const { createInertiaApp } = await import('@inertiajs/react');
const { PageTabs } = await import('../../resources/js/components/page-tabs');
const { default: Show } = await import('../../resources/js/pages/user/show');

beforeEach(() => {
    links.length = 0;
});

const tabs = [
    { title: 'One', href: '/one', isActive: true },
    { title: 'Two', href: '/two', isActive: false },
];

test('tab links only preserve the page state when asked to', () => {
    renderToString(<PageTabs label="Feed" tabs={tabs} />);
    renderToString(<PageTabs label="Feed" tabs={tabs} preserveState />);

    assert.deepEqual(
        links.map((link) => link.preserveState),
        [false, false, true, true],
    );
});

test('profile tabs keep the page mounted and leave the star total out of the reload, so a live total survives a tab switch', async () => {
    const user = {
        id: 'author',
        username: 'author',
        name: 'Author',
        avatar_url: null,
        bio: null,
        location: null,
        website: null,
        company: null,
        has_sponsors_listing: false,
        lane: 1,
        created_at: '2026-10-01T00:00:00.000000Z',
        updated_at: '2026-10-01T00:00:00.000000Z',
        commits_count: 2,
        followers_count: 0,
        following_count: 0,
    } as Profile;

    const result = await createInertiaApp({
        page: {
            component: 'user/show',
            props: {
                auth: { user: { id: 'viewer', username: 'viewer' }, log: {} },
                errors: {},
            },
            url: '/@author',
            version: null,
            clearHistory: false,
            encryptHistory: false,
            rescuedProps: [],
            rememberedState: {},
            flash: {},
        },
        resolve: () => () => (
            <Show
                user={user}
                pasteisReceived={null}
                isFollowing={false}
                followsYou={false}
                isMuting={false}
                starsCount={13}
                canPush={false}
                activity={{ scale: 'day', buckets: [] } as never}
                streaks={{ longest: 0, current: 0 }}
                achievements={{} as never}
                commits={{ data: [] } as never}
                tab="commits"
                pinnedCommit={null}
            />
        ),
        render: renderToString,
        setup: ({ App, props }) => <App {...props} />,
    });

    assert.ok(result);
    assert.match(result.body, /13<\/dd>/);

    const feedTabs = links.filter((link) =>
        String(
            typeof link.href === 'string' ? link.href : link.href?.url,
        ).startsWith('/@author'),
    );
    const tabLinks = feedTabs.filter((link) => link.only !== undefined);

    assert.equal(tabLinks.length, 2);

    for (const link of tabLinks) {
        assert.equal(link.preserveState, true);
        assert.ok(!link.only?.includes('starsCount'));
    }
});
