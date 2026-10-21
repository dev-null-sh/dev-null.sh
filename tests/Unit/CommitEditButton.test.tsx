/// <reference types="node" />

import assert from 'node:assert/strict';
import { test } from 'vite-plus/test';
import { createInertiaApp } from '@inertiajs/react';
import { renderToString } from 'react-dom/server';
import { CommitEditButton } from '../../resources/js/components/commit-edit-button';
import { TooltipProvider } from '../../resources/js/components/ui/tooltip';
import type { Commit } from '../../resources/js/types';

const commit = {
    id: 'edit-target',
    body: 'Original text',
    user_id: 'author',
    status: 'approved',
} as Commit;

for (const user of [null, { id: 'viewer' }, { id: 'author' }]) {
    test(`shows the edit control only to its author for ${user === null ? 'guests' : 'signed-in readers'}`, async () => {
        const result = await createInertiaApp<{
            auth: { user: { id: string } | null };
        }>({
            page: {
                component: 'quote',
                props: { auth: { user }, errors: {} },
                url: '/@author/quote-target',
                version: null,
                clearHistory: false,
                encryptHistory: false,
                rescuedProps: [],
                rememberedState: {},
                flash: {},
            },
            resolve: () => () => <CommitEditButton commit={commit} />,
            render: renderToString,
            setup: ({ App, props }) => (
                <TooltipProvider>
                    <App {...props} />
                </TooltipProvider>
            ),
        });

        assert.ok(result);
        if (user?.id === commit.user_id) {
            assert.match(result.body, /aria-label="Edit commit"/);
        } else {
            assert.doesNotMatch(result.body, /aria-label="Edit commit"/);
        }
    });
}
