/// <reference types="node" />
import assert from 'node:assert/strict';
import * as React from 'react';
import type { ReactElement, ReactNode } from 'react';
import { afterAll, test, vi } from 'vite-plus/test';
import type { Profile, User } from '../../resources/js/types';

type Props = Record<string, unknown> & { children?: ReactNode };

let showPreview = false;
const visits: unknown[] = [];
const assigned: string[] = [];

vi.doMock('react', () => ({
    ...React,
    useEffect: () => {},
    useLayoutEffect: () => {},
    useRef: (initial: unknown) => ({ current: initial }),
    useState: (initial: unknown) => [
        typeof initial === 'function'
            ? initial()
            : initial === null && showPreview
              ? { left: 0, top: 0 }
              : initial,
        () => {},
    ],
    useSyncExternalStore: (_subscribe: unknown, read: () => unknown) => read(),
}));
vi.doMock('react-dom', async () => ({
    ...(await vi.importActual('react-dom')),
    createPortal: (children: ReactNode) => children,
}));
vi.doMock('@inertiajs/react', async () => ({
    ...(await vi.importActual('@inertiajs/react')),
    usePage: () => ({ props: { auth: { user: null } } }),
    router: {
        flushAll: () => {},
        visit: (...args: unknown[]) => visits.push(args),
    },
}));

const { UserPreview } =
    await import('../../resources/js/components/user-preview');

vi.stubGlobal('window', {
    matchMedia: () => ({ matches: false }),
    location: { assign: (url: string) => assigned.push(url) },
});
vi.stubGlobal('document', { body: {} });
afterAll(() => vi.unstubAllGlobals());

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

function previewFollowButton(user: User): ReactElement<Props> {
    showPreview = true;
    const preview = UserPreview({ user, children: user.name });
    showPreview = false;

    const content = find(preview, (props) => 'onMenuOpenChange' in props);
    assert.ok(content);

    const button = find(
        (content.type as (props: Props) => ReactNode)({
            ...content.props,
            data: {
                isFollowing: false,
                followsYou: false,
                isMuting: false,
                isBlocking: false,
                isUnavailable: false,
                user: { ...user, followers_count: 0 } as unknown as Profile,
            },
        }),
        (props) => props['data-test'] === 'user-preview-follow-button',
    );
    assert.ok(button);

    return button;
}

test('a guest following from a profile preview goes to sign in', () => {
    const user = {
        id: 'author',
        name: 'Author',
        handle: 'author',
        avatar_url: null,
        lane: 1,
        timezone: null,
        show_local_time: false,
        followers_count: 0,
    } as unknown as User;

    (previewFollowButton(user).props.onClick as () => void)();

    assert.deepEqual(visits, []);
    assert.deepEqual(assigned, ['/login']);
});
