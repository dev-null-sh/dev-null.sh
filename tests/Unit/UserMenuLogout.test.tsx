/// <reference types="node" />
import assert from 'node:assert/strict';
import * as React from 'react';
import type { ReactElement } from 'react';
import { test, vi } from 'vite-plus/test';
import type { User } from '../../resources/js/types';

vi.doMock('react', () => ({
    ...React,
    useCallback: (callback: unknown) => callback,
}));
vi.doMock('@inertiajs/react', async () => ({
    ...(await vi.importActual('@inertiajs/react')),
    usePage: () => ({ props: { auth: { isAdmin: false }, repository: '' } }),
}));

const { UserMenuContent } =
    await import('../../resources/js/components/user-menu-content');

test('logging out remounts the page instead of keeping what the signed in account had open', () => {
    const items = React.Children.toArray(
        UserMenuContent({ user: { id: 'author', handle: 'author' } as User })
            .props.children,
    );
    const logout = (
        items.at(-1) as ReactElement<{
            children: ReactElement<{
                preserveState?: boolean;
                'data-test': string;
            }>;
        }>
    ).props.children;

    assert.equal(logout.props['data-test'], 'logout-button');
    assert.equal(logout.props.preserveState, false);
});
