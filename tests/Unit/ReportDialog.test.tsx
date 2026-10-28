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
    user_id: 'author',
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
