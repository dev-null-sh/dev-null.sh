/// <reference types="node" />
import assert from 'node:assert/strict';
import { test } from 'node:test';
import { createInertiaApp } from '@inertiajs/react';
import { renderToString } from 'react-dom/server';
import { AppNav, MobileNav } from '../../resources/js/components/app-nav';
import type { Profile } from '../../resources/js/types';

const admin = {
    id: 'moderator',
    username: 'moderator',
    name: 'Moderator',
    avatar_url: null,
} as Profile;

for (const mobile of [false, true]) {
    for (const scenario of [
        { url: '/', user: null, active: '/' },
        { url: '/@author', user: null, active: null },
        { url: '/moderation', user: admin, active: '/moderation' },
        { url: '/moderation/decisions', user: admin, active: '/moderation' },
        { url: '/log/latest', user: admin, active: '/log/following' },
    ]) {
        void test(`${mobile ? 'mobile' : 'desktop'} navigation marks ${scenario.active ?? 'nothing'} active at ${scenario.url} for ${scenario.user === null ? 'guests' : 'admins'}`, async () => {
            const result = await createInertiaApp({
                page: {
                    component: 'navigation',
                    props: {
                        errors: {},
                        auth: {
                            user: scenario.user,
                            isAdmin: scenario.user !== null,
                            log: scenario.user === null ? null : 'following',
                            unreadNotificationsCount: 0,
                        },
                        hasUnmoderatedCommits: false,
                    },
                    url: scenario.url,
                    version: null,
                    clearHistory: false,
                    encryptHistory: false,
                    rescuedProps: [],
                    rememberedState: {},
                    flash: {},
                },
                resolve: () => () =>
                    mobile ? <MobileNav /> : <AppNav onSearch={() => {}} />,
                render: renderToString,
                setup: ({ App, props }) => <App {...props} />,
            });

            assert.ok(result);
            const links =
                result.body.match(/<a\b[^>]*aria-current="page"[^>]*>/g) ?? [];
            if (scenario.active === null) {
                assert.deepEqual(links, []);
            } else {
                assert.equal(links.length, 1);
                assert.ok(links[0].includes(`href="${scenario.active}"`));
            }
        });
    }
}
