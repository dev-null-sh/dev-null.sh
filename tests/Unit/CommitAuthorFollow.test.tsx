/// <reference types="node" />
import assert from 'node:assert/strict';
import * as React from 'react';
import type { ReactElement, ReactNode } from 'react';
import { afterAll, test, vi } from 'vite-plus/test';
import type { User } from '../../resources/js/types';

type Props = Record<string, unknown> & { children?: ReactNode };

type VisitOptions = {
    onHttpException?: (response: { status: number }) => boolean | void;
    onSuccess?: () => void;
    onFinish?: () => void;
};

const visits: (VisitOptions & { method?: string })[] = [];
const toasts: string[] = [];
const locations: string[] = [];
let authUser: { id: string } | null = { id: 'viewer' };
let preview: {
    isFollowing: boolean;
    isUnavailable?: boolean;
} | null = { isFollowing: false };
let fetchCount = 0;

vi.doMock('react', () => ({
    ...React,
    useEffect: () => {},
    useLayoutEffect: () => {},
    useRef: (initial: unknown) => ({ current: initial }),
    useState: (initial: unknown) => [
        typeof initial === 'function' ? initial() : initial,
        () => {},
    ],
    useSyncExternalStore: (_subscribe: unknown, read: () => unknown) =>
        read(),
}));

vi.doMock('@inertiajs/react', async () => ({
    ...(await vi.importActual('@inertiajs/react')),
    usePage: () => ({ props: { auth: { user: authUser } } }),
    router: {
        flushAll: () => {},
        visit: (url: { method: string }, options: VisitOptions) =>
            visits.push({ ...options, method: url.method }),
    },
}));

vi.doMock('sonner', () => {
    const toast = Object.assign(
        (message: unknown) => {
            toasts.push(typeof message === 'string' ? message : 'followed');
        },
        {
            error: (message: string) => toasts.push(message),
            success: (message: string) => toasts.push(message),
        },
    );

    return { toast };
});

const { CommitAuthorFollowButton } =
    await import('../../resources/js/components/commit-author-follow-button');
const { FollowButton, resolveIsFollowing, toggleFollow } =
    await import('../../resources/js/components/follow-button');

vi.stubGlobal('window', {
    matchMedia: () => ({ matches: false }),
    location: {
        assign: (url: string) => locations.push(url),
    },
});
vi.stubGlobal('document', { body: {} });
vi.stubGlobal('fetch', async () => {
    fetchCount += 1;

    if (preview === null) {
        return {
            ok: false,
            status: 500,
            json: async () => ({}),
        };
    }

    return {
        ok: true,
        status: 200,
        json: async () => ({
            isUnavailable: false,
            ...preview,
        }),
    };
});
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

function author(username: string): Pick<User, 'id' | 'username'> {
    return { id: username, username };
}

function followButton(
    user: Pick<User, 'id' | 'username'>,
): ReactElement<Props> | null {
    const node = CommitAuthorFollowButton({
        user,
        keyShortcut: 'f',
    }) as ReactNode;

    if (node === null) {
        return null;
    }

    return find(
        node,
        (props) => props['data-test'] === 'commit-follow-author-button',
    );
}

async function pressFollow(user: Pick<User, 'id' | 'username'>): Promise<void> {
    const button = followButton(user);
    assert.ok(button);
    (button.props.onClick as () => void)();
    await new Promise((resolve) => setTimeout(resolve));
}

function followFromProfile(user: Pick<User, 'id' | 'username'>): void {
    const profileButton = find(
        FollowButton({ user, isFollowing: false }),
        (props) => props['data-test'] === 'follow-button',
    );
    assert.ok(profileButton);
    (profileButton.props.onSuccess as () => void)();
}

test('the focused commit exposes f as its follow shortcut', () => {
    authUser = { id: 'viewer' };
    const button = followButton(author('taylor'));

    assert.ok(button);
    assert.equal(button.props['aria-keyshortcuts'], 'f');
    assert.equal(button.props['aria-label'], 'Follow or unfollow @taylor');
});

test('your own commit has no follow shortcut', () => {
    authUser = { id: 'taylor' };

    assert.equal(followButton(author('taylor')), null);
});

test('guests are sent to sign in instead of following', async () => {
    authUser = null;
    locations.length = 0;
    visits.length = 0;

    await pressFollow(author('guest-target'));

    assert.equal(visits.length, 0);
    assert.ok(locations.at(-1)?.includes('login'));
});

test('f follows an author who is not already followed', async () => {
    authUser = { id: 'viewer' };
    preview = { isFollowing: false };
    fetchCount = 0;
    visits.length = 0;

    await pressFollow(author('newfriend'));

    assert.equal(fetchCount, 1);
    assert.equal(visits.at(-1)?.method, 'post');
    visits.at(-1)?.onSuccess?.();
    visits.at(-1)?.onFinish?.();
});

test('f unfollows once shared state already knows they are followed', async () => {
    authUser = { id: 'viewer' };
    const user = author('known');
    followFromProfile(user);
    fetchCount = 0;
    visits.length = 0;

    await pressFollow(user);

    assert.equal(fetchCount, 0);
    assert.equal(visits.at(-1)?.method, 'delete');
});

test('resolveIsFollowing remembers a preview result for later shortcuts', async () => {
    authUser = { id: 'viewer' };
    preview = { isFollowing: true };
    fetchCount = 0;

    assert.equal(await resolveIsFollowing(author('previewed')), true);
    assert.equal(await resolveIsFollowing(author('previewed')), true);
    assert.equal(fetchCount, 1);
});

test('resolveIsFollowing gives up when the author is unavailable', async () => {
    authUser = { id: 'viewer' };
    preview = { isFollowing: false, isUnavailable: true };

    assert.equal(await resolveIsFollowing(author('blocked-author')), null);
});

test('toggleFollow posts a follow and reports success', () => {
    visits.length = 0;
    toasts.length = 0;
    let following = false;

    toggleFollow(author('toastable'), false, (next) => {
        following = next;
    });

    assert.equal(visits.at(-1)?.method, 'post');
    visits.at(-1)?.onSuccess?.();
    assert.equal(following, true);
    assert.equal(toasts.at(-1), 'followed');
});
