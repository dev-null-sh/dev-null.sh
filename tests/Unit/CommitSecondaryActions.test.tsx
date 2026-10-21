/// <reference types="node" />

import assert from 'node:assert/strict';
import { test } from 'node:test';
import { createInertiaApp } from '@inertiajs/react';
import { renderToString } from 'react-dom/server';
import { CommitSecondaryActions } from '../../resources/js/components/commit-secondary-actions';
import { TooltipProvider } from '../../resources/js/components/ui/tooltip';
import type { Commit } from '../../resources/js/types';

const commit = {
    id: 'commit',
    user_id: 'author',
    status: 'approved',
} as Commit;

async function desktopActionsClasses(isReply: boolean): Promise<string[]> {
    const result = await createInertiaApp<{
        auth: { user: { id: string } | null };
    }>({
        page: {
            component: 'actions',
            props: { auth: { user: { id: 'viewer' } }, errors: {} },
            url: '/@author/commit',
            version: null,
            clearHistory: false,
            encryptHistory: false,
            rescuedProps: [],
            rememberedState: {},
            flash: {},
        },
        resolve: () => () => (
            <CommitSecondaryActions commit={commit} isReply={isReply} />
        ),
        render: renderToString,
        setup: ({ App, props }) => (
            <TooltipProvider>
                <App {...props} />
            </TooltipProvider>
        ),
    });

    assert.ok(result);

    const classes = /<div class="([^"]*md:flex[^"]*)"/.exec(result.body)?.[1];

    assert.ok(classes);

    return classes.split(' ');
}

void test('reveals a commit’s actions while the commit is hovered or focused', async () => {
    const classes = await desktopActionsClasses(false);

    assert.ok(classes.includes('md:opacity-0'));
    assert.ok(classes.includes('md:commit-hover:opacity-100'));
    assert.ok(classes.includes('md:commit-focus-within:opacity-100'));
    assert.ok(!classes.includes('md:reply-hover:opacity-100'));
});

void test('reveals a reply’s actions only while that reply is hovered', async () => {
    const classes = await desktopActionsClasses(true);

    assert.ok(classes.includes('md:opacity-0'));
    assert.ok(classes.includes('md:reply-hover:opacity-100'));
    assert.ok(!classes.includes('md:commit-hover:opacity-100'));
});

void test('keeps the actions visible on screens without hover and while they have focus', async () => {
    for (const isReply of [false, true]) {
        const classes = await desktopActionsClasses(isReply);

        assert.ok(classes.includes('[@media(hover:none)]:opacity-100'));
        assert.ok(classes.includes('md:focus-within:opacity-100'));
    }
});
