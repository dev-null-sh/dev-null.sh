/// <reference types="node" />
import assert from 'node:assert/strict';
import * as Inertia from '@inertiajs/react';
import { renderToString } from 'react-dom/server';
import { afterAll, test, vi } from 'vite-plus/test';
import type { Commit } from '../../resources/js/types';

const forms = vi.fn();

vi.doMock('@inertiajs/react', () => ({
    ...Inertia,
    useForm: (...args: Parameters<typeof Inertia.useForm>) => {
        forms();

        return Inertia.useForm(...args);
    },
}));

afterAll(() => {
    vi.doUnmock('@inertiajs/react');
});

const commit = {
    id: 'reported',
    author: { id: 'author' },
    status: 'approved',
    body: 'Reported commit',
} as Commit;

test('leaves the report form out until the dialog opens, so every card stays light', async () => {
    const { CommitReportDialog } =
        await import('../../resources/js/components/commit-report-button');

    const result = await Inertia.createInertiaApp({
        page: {
            component: 'test',
            props: { auth: { user: { id: 'reader' } }, errors: {} },
            url: '/',
            version: null,
            clearHistory: false,
            encryptHistory: false,
            rescuedProps: [],
            rememberedState: {},
            flash: {},
        },
        resolve: () => () => (
            <CommitReportDialog
                commit={commit}
                open={false}
                onOpenChange={() => {}}
            />
        ),
        render: renderToString,
        setup: ({ App, props }) => <App {...props} />,
    });

    assert.ok(result);
    assert.equal(forms.mock.calls.length, 0);
});

test('leaves the profile report form out until the dialog opens, so every chat row stays light', async () => {
    forms.mockClear();

    const { UserReportDialog } =
        await import('../../resources/js/components/user-report-dialog');

    const result = await Inertia.createInertiaApp({
        page: {
            component: 'test',
            props: { auth: { user: { id: 'reader' } }, errors: {} },
            url: '/',
            version: null,
            clearHistory: false,
            encryptHistory: false,
            rescuedProps: [],
            rememberedState: {},
            flash: {},
        },
        resolve: () => () => (
            <UserReportDialog
                user={{ handle: 'reported' }}
                open={false}
                onOpenChange={() => {}}
            />
        ),
        render: renderToString,
        setup: ({ App, props }) => <App {...props} />,
    });

    assert.ok(result);
    assert.equal(forms.mock.calls.length, 0);
});

test('offers the same report reasons the server takes', async () => {
    const { reportReasons } =
        await import('../../resources/js/lib/report-reasons');

    assert.deepEqual(
        reportReasons.map(([value]) => value),
        ['spam', 'harassment', 'unsafe', 'child_safety', 'other'],
    );
});
