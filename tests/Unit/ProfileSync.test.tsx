/// <reference types="node" />
import assert from 'node:assert/strict';
import * as React from 'react';
import type { ReactElement, ReactNode } from 'react';
import { test, vi } from 'vite-plus/test';

type Props = Record<string, unknown> & { children?: ReactNode };
const visits: {
    except?: string[];
    preserveScroll?: boolean;
    onSuccess?: (page: { props: { suggestedUsers?: unknown[] } }) => void;
}[] = [];
const reload = vi.fn();
const flushAll = vi.fn();
const suggestionStates = [
    { state: 'missing', suggestedUsers: undefined },
    { state: 'empty', suggestedUsers: [] },
    { state: 'loaded', suggestedUsers: [{ id: 'suggested-user' }] },
];

vi.doMock('react', () => ({
    ...React,
    useState: (initial: unknown) => [initial, () => {}],
}));
vi.doMock('@inertiajs/react', async () => ({
    ...(await vi.importActual('@inertiajs/react')),
    router: {
        reload,
        flushAll,
        visit: (_url: unknown, options: (typeof visits)[number]) =>
            visits.push(options),
    },
}));
vi.doMock('@/hooks/use-user', () => ({
    useUser: () => ({ user: { id: 'viewer', handle: 'viewer' } }),
}));
vi.doMock('@/hooks/use-now', () => ({ useNow: () => 0 }));

const { EditProfileMenu } =
    await import('../../resources/js/components/edit-profile-menu');
const { default: Edit } =
    await import('../../resources/js/pages/user-profile/edit');

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

    return matches(node.props) ? node : find(node.props.children, matches);
}

test.each(suggestionStates)(
    'syncing from the profile menu preserves the feed and only reloads missing suggestions ($state)',
    ({ suggestedUsers }) => {
        reload.mockClear();
        flushAll.mockClear();
        const sync = find(
            EditProfileMenu(),
            (props) => props['data-test'] === 'sync-profile',
        );
        assert.ok(sync);

        (sync.props.onSelect as () => void)();

        assert.deepEqual(visits.at(-1)?.except, ['suggestedUsers', 'commits']);
        assert.equal(visits.at(-1)?.preserveScroll, true);

        visits.at(-1)?.onSuccess?.({ props: { suggestedUsers } });

        assert.equal(flushAll.mock.calls.length, 1);
        assert.deepEqual(
            reload.mock.calls,
            suggestedUsers === undefined
                ? [[{ only: ['suggestedUsers'] }]]
                : [],
        );
    },
);

test.each(suggestionStates)(
    'syncing from settings only reloads missing suggestions ($state)',
    ({ suggestedUsers }) => {
        reload.mockClear();
        flushAll.mockClear();
        const form = find(
            Edit({
                translationLanguage: null,
                translationLanguages: [],
                preferredLane: null,
                automaticLane: 1,
                githubSyncedAt: null,
                activityGraphUrl: '/activity',
                profileUrl: '/@viewer',
            }),
            (props) => 'options' in props && 'onSuccess' in props,
        );
        assert.ok(form);

        const options = form.props.options as (typeof visits)[number];
        assert.deepEqual(options.except, ['suggestedUsers']);
        assert.equal(options.preserveScroll, true);

        (
            form.props.onSuccess as NonNullable<
                (typeof visits)[number]['onSuccess']
            >
        )({ props: { suggestedUsers } });

        assert.equal(flushAll.mock.calls.length, 1);
        assert.deepEqual(
            reload.mock.calls,
            suggestedUsers === undefined
                ? [[{ only: ['suggestedUsers'] }]]
                : [],
        );
    },
);
