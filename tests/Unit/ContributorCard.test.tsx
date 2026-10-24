/// <reference types="node" />
import assert from 'node:assert/strict';
import * as React from 'react';
import type { ReactElement, ReactNode } from 'react';
import { afterAll, test, vi } from 'vite-plus/test';

type Props = Record<string, unknown> & { children?: ReactNode };

let authUser: { id: string; username: string } | null = {
    id: 'viewer',
    username: 'viewer',
};

vi.doMock('react', () => ({
    ...React,
    useEffect: () => {},
    useLayoutEffect: () => {},
    useRef: (initial: unknown) => ({ current: initial }),
    useState: (initial: unknown) => [
        typeof initial === 'function' ? initial() : initial,
        () => {},
    ],
    useSyncExternalStore: (_subscribe: unknown, read: () => unknown) => read(),
}));

vi.doMock('@inertiajs/react', async () => ({
    ...(await vi.importActual('@inertiajs/react')),
    usePage: () => ({
        component: 'about/show',
        props: {
            auth: { user: authUser },
            repository: 'laravel/dev-null',
        },
    }),
}));

vi.doMock('@/components/ui/tooltip', () => ({
    Tooltip: ({ children }: Props) => children,
    TooltipTrigger: ({ children }: Props) => children,
    TooltipContent: ({ children }: Props) => children,
    TooltipProvider: ({ children }: Props) => children,
}));

const { default: Show } = await import('../../resources/js/pages/about/show');

const assigned: string[] = [];
vi.stubGlobal('window', {
    matchMedia: () => ({ matches: false }),
    location: {
        assign: (url: string) => assigned.push(url),
    },
});
vi.stubGlobal('document', { body: {} });
afterAll(() => vi.unstubAllGlobals());

function findAll(
    node: ReactNode,
    matches: (props: Props) => boolean,
): ReactElement<Props>[] {
    const results: ReactElement<Props>[] = [];

    function search(current: ReactNode) {
        if (Array.isArray(current)) {
            for (const child of current) {
                search(child);
            }

            return;
        }

        if (!React.isValidElement<Props>(current)) {
            return;
        }

        if (matches(current.props)) {
            results.push(current);

            return;
        }

        if (typeof current.type === 'function') {
            try {
                search(
                    (current.type as (props: unknown) => ReactNode)(
                        current.props,
                    ),
                );

                return;
            } catch {
                // Fall back to traversing children if component function fails
            }
        }

        search(current.props.children);
    }

    search(node);

    return results;
}

test('renders follow buttons for contributors except the authenticated user', () => {
    authUser = { id: 'viewer', username: 'viewer' };

    const tree = Show({
        contributors: [
            { login: 'xHeaven', commits: 100 },
            { login: 'viewer', commits: 50 },
            { login: 'nunomaduro', commits: 80 },
        ],
        followingLogins: ['nunomaduro'],
    });

    const followButtons = findAll(
        tree,
        (props) => props['data-test'] === 'contributor-follow-button',
    );

    assert.equal(followButtons.length, 2);

    const xHeavenButton = followButtons.find(
        (btn) =>
            typeof btn.props.user === 'object' &&
            btn.props.user !== null &&
            'username' in btn.props.user &&
            btn.props.user.username === 'xHeaven',
    );
    assert.ok(xHeavenButton);
    assert.equal(xHeavenButton.props.isFollowing, false);

    const nunoButton = followButtons.find(
        (btn) =>
            typeof btn.props.user === 'object' &&
            btn.props.user !== null &&
            'username' in btn.props.user &&
            btn.props.user.username === 'nunomaduro',
    );
    assert.ok(nunoButton);
    assert.equal(nunoButton.props.isFollowing, true);
});

test('renders follow buttons for guests and directs to login when clicked', () => {
    authUser = null;

    const tree = Show({
        contributors: [{ login: 'xHeaven', commits: 100 }],
    });

    const followButtons = findAll(
        tree,
        (props) => props['data-test'] === 'contributor-follow-button',
    );

    assert.equal(followButtons.length, 1);
    const button = followButtons[0];
    assert.ok(button);

    // Call FollowIconButton with auth.user = null
    const renderedButton = (
        button.type as (props: Record<string, unknown>) => ReactElement<Props>
    )(button.props);

    const trigger = findAll(
        renderedButton,
        (props) => typeof props.onClick === 'function',
    )[0];
    assert.ok(trigger);
    (trigger.props.onClick as () => void)();

    assert.deepEqual(assigned, ['/login']);
});
