/// <reference types="node" />
import assert from 'node:assert/strict';
import type { ReactElement } from 'react';
import { test, vi } from 'vite-plus/test';

let user: { id: string } | null = null;

vi.doMock('@inertiajs/react', async () => ({
    ...(await vi.importActual('@inertiajs/react')),
    usePage: () => ({ props: { auth: { user } } }),
}));

const { Link } = await import('@inertiajs/react');
const { MembersLink } =
    await import('../../resources/js/components/members-link');

function render(): ReactElement<{ href: unknown; className?: string }> {
    return MembersLink({
        href: '/@ada/followers',
        className: 'underline',
        children: 'followers',
    }) as ReactElement<{ href: unknown; className?: string }>;
}

test('sends guests to sign in', () => {
    user = null;
    const link = render();

    assert.equal(link.type, 'a');
    assert.equal(link.props.href, '/login');
    assert.equal(link.props.className, 'underline');
});

test('opens the page for members', () => {
    user = { id: 'ada' };
    const link = render();

    assert.equal(link.type, Link);
    assert.equal(link.props.href, '/@ada/followers');
});
