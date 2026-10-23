/// <reference types="node" />
import assert from 'node:assert/strict';
import * as React from 'react';
import type { ReactElement, ReactNode } from 'react';
import { test, vi } from 'vite-plus/test';
import { createDateTime } from '../../resources/js/lib/date-time';
import type { Commit } from '../../resources/js/types';

type Props = Record<string, unknown> & { children?: ReactNode };
type VisitOptions = {
    onHttpException?: (response: { status: number }) => boolean | void;
};

const visits: VisitOptions[] = [];
const reloads: unknown[] = [];
const toasts: string[] = [];

vi.doMock('react', () => ({
    ...React,
    useState: (initial: unknown) => [initial, () => {}],
}));
vi.doMock('../../resources/js/hooks/use-date-time', () => ({
    useDateTime: () => createDateTime({ locale: 'en-US' }),
}));
vi.doMock('@inertiajs/react', async () => ({
    ...(await vi.importActual('@inertiajs/react')),
    usePage: () => ({ component: 'moderation/reports', props: {} }),
    router: {
        visit: (_url: unknown, options: VisitOptions) => visits.push(options),
        reload: (options: unknown) => reloads.push(options),
    },
}));
vi.doMock('sonner', () => ({
    toast: Object.assign((message: string) => toasts.push(message), {
        error: (message: string) => toasts.push(message),
    }),
}));

const { default: Reports } =
    await import('../../resources/js/pages/moderation/reports');
const { deletedResources } =
    await import('../../resources/js/lib/deleted-resources');

function find(
    node: ReactNode,
    matches: (props: Props) => boolean,
): ReactElement<Props> | null {
    if (Array.isArray(node)) {
        for (const child of node) {
            const found = find(child, matches);

            if (found) {
                return found;
            }
        }

        return null;
    }

    if (!React.isValidElement<Props>(node)) {
        return null;
    }

    if (matches(node.props)) {
        return node;
    }

    return (
        find(node.props.children, matches) ??
        find(node.props.actions as ReactNode, matches)
    );
}

function resolveAndFail(label: string, commitId: string): boolean | void {
    const page = Reports({
        reports: {
            data: [
                {
                    id: `report-${commitId}`,
                    reason: 'spam',
                    details: null,
                    created_at: '2026-10-19T12:00:00Z',
                    commit: { id: commitId } as Commit,
                },
            ],
        },
        reasons: { spam: 'Spam' },
    } as unknown as Parameters<typeof Reports>[0]);
    const button = find(
        page,
        (props) =>
            React.Children.toArray(props.children as ReactNode).includes(
                label,
            ) && typeof props.onClick === 'function',
    );
    assert.ok(button);
    (button.props.onClick as () => void)();

    return visits.at(-1)?.onHttpException?.({ status: 404 });
}

test('a report that left the queue refreshes it, without calling its live commit deleted', () => {
    const kept = resolveAndFail('Dismiss report', 'still-live');

    assert.equal(kept, false);
    assert.equal(deletedResources.hasCommit('still-live'), false);
    assert.equal(reloads.length, 1);
    assert.deepEqual(toasts, ['This report is no longer in the queue.']);
});

test('rejecting a commit that is gone turns its actions off', () => {
    const kept = resolveAndFail('Reject commit', 'gone');

    assert.equal(kept, false);
    assert.equal(deletedResources.hasCommit('gone'), true);
});
