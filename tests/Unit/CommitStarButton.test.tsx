/// <reference types="node" />

import assert from 'node:assert/strict';
import { createInertiaApp } from '@inertiajs/react';
import { renderToString } from 'react-dom/server';
import { test } from 'vite-plus/test';
import { CommitStarButton } from '../../resources/js/components/commit-star-button';
import { TooltipProvider } from '../../resources/js/components/ui/tooltip';
import type { Commit } from '../../resources/js/types';

async function render(
    user: { id: string } | null,
    commit: Partial<Commit> = {},
    keyShortcut?: string,
): Promise<string> {
    const result = await createInertiaApp<{
        auth: { user: { id: string } | null };
    }>({
        page: {
            component: 'star',
            props: { auth: { user }, errors: {} },
            url: '/@author/commit',
            version: null,
            clearHistory: false,
            encryptHistory: false,
            rescuedProps: [],
            rememberedState: {},
            flash: {},
        },
        resolve: () => () => (
            <CommitStarButton
                commit={
                    {
                        id: 'commit',
                        user_id: 'author',
                        status: 'approved',
                        starred: false,
                        stars_count: 2,
                        ...commit,
                    } as Commit
                }
                keyShortcut={keyShortcut}
            />
        ),
        render: renderToString,
        setup: ({ App, props }) => (
            <TooltipProvider>
                <App {...props} />
            </TooltipProvider>
        ),
    });

    assert.ok(result);

    return result.body;
}

for (const status of ['approved', 'pending', 'rejected'] as const) {
    void test(`authors can star their ${status} commit and open who starred`, async () => {
        const body = await render({ id: 'author' }, { status });

        assert.match(body, /data-test="star-commit-button"/);
        assert.match(body, /aria-pressed="false"/);
        assert.match(body, /aria-label="Star commit, 2 stars"/);
        assert.match(body, /aria-label="Show who starred"/);
    });
}

void test('authors with no stars yet still get a star control', async () => {
    const body = await render({ id: 'author' }, { stars_count: 0 });

    assert.match(body, /data-test="star-commit-button"/);
    assert.match(body, /aria-label="Star commit"/);
    assert.doesNotMatch(body, /aria-label="Show who starred"/);
});

for (const user of [null, { id: 'viewer' }]) {
    void test(`approved commits keep the star control for ${user === null ? 'guests' : 'other readers'}`, async () => {
        const body = await render(user);

        assert.match(body, /data-test="star-commit-button"/);
        assert.match(body, /aria-pressed="false"/);
        assert.match(body, /aria-label="Star commit, 2 stars"/);
        assert.doesNotMatch(body, /aria-label="Show who starred"/);
    });
}

void test('star button exposes the keyboard shortcut used by j/k navigation', async () => {
    const body = await render({ id: 'viewer' }, {}, 's');

    assert.match(body, /data-test="star-commit-button"/);
    assert.match(body, /aria-keyshortcuts="s"/);
});

void test('unstarred commits reserve the count slot outside the star control', async () => {
    const body = await render({ id: 'viewer' }, { stars_count: 0 });

    assert.match(
        body,
        /class="[^"]*mr-\[calc\(0\.25rem\+1ch\)\][^"]*" data-test="star-commit-button"/,
    );
    assert.doesNotMatch(body, /data-test="star-count"/);
});

void test('starred commits keep the count slot inside the star control', async () => {
    const body = await render({ id: 'viewer' }, { stars_count: 1 });

    assert.doesNotMatch(body, /mr-\[calc/);
    assert.match(body, /data-test="star-count"[^>]*>1<\/span>/);
});

for (const status of ['pending', 'rejected'] as const) {
    void test(`other readers can star a ${status} commit`, async () => {
        const body = await render({ id: 'viewer' }, { status });

        assert.match(body, /data-test="star-commit-button"/);
    });
}

for (const status of ['approved', 'pending', 'rejected'] as const) {
    void test(`authors can star their ${status} reply and open who starred`, async () => {
        const body = await render(
            { id: 'author' },
            { status, parent_id: 'parent' },
        );

        assert.match(body, /data-test="star-commit-button"/);
        assert.match(body, /aria-label="Show who starred"/);
    });
}
