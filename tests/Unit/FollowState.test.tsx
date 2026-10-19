/// <reference types="node" />
import assert from 'node:assert/strict';
import * as React from 'react';
import type { ReactElement, ReactNode } from 'react';
import { afterAll, test, vi } from 'vite-plus/test';
import type { Profile, SuggestedUser, User } from '../../resources/js/types';

type Props = Record<string, unknown> & { children?: ReactNode };

let showPreview = false;
type VisitOptions = {
    onSuccess?: () => void;
    onError?: () => void;
    onNetworkError?: () => void;
    onCancel?: () => void;
};

const visits: (VisitOptions & { method?: string })[] = [];

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
    useSyncExternalStore: (_subscribe: unknown, read: () => unknown) =>
        read(),
}));
vi.doMock('react-dom', async () => ({
    ...(await vi.importActual('react-dom')),
    createPortal: (children: ReactNode) => children,
}));
vi.doMock('@inertiajs/react', async () => ({
    ...(await vi.importActual('@inertiajs/react')),
    usePage: () => ({ props: { auth: { user: { id: 'viewer' } } } }),
    router: {
        flushAll: () => {},
        visit: (url: { method: string }, options: VisitOptions) =>
            visits.push({ ...options, method: url.method }),
    },
}));

vi.doMock('../../resources/js/lib/chat', async () => ({
    ...(await vi.importActual('../../resources/js/lib/chat')),
    blockPartner: async () => true,
}));

const { FollowButton } =
    await import('../../resources/js/components/follow-button');
const { UserPreview } =
    await import('../../resources/js/components/user-preview');
const { SuggestedUserRow } =
    await import('../../resources/js/components/who-to-follow');
const { UserSafetyMenu } =
    await import('../../resources/js/components/user-safety-menu');
const { ConversationActions } =
    await import('../../resources/js/components/chat/conversation-actions');

vi.stubGlobal('window', { matchMedia: () => ({ matches: false }) });
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

function render(element: ReactElement<Props>): ReactNode {
    return (element.type as (props: Props) => ReactNode)(element.props);
}

function suggestion(username: string): SuggestedUser {
    return {
        id: username,
        name: username,
        username,
        avatar_url: null,
        lane: 1,
        timezone: null,
        followed_by_following_count: 0,
        followers_count: 0,
    } as unknown as SuggestedUser;
}

function suggestionLabel(user: SuggestedUser): unknown {
    const row = SuggestedUserRow({ user }) as ReactElement<Props>;
    const button = React.Children.toArray(row.props.children)[1];
    assert.ok(React.isValidElement<Props>(button));

    return find(render(button), (props) => 'aria-label' in props)?.props[
        'aria-label'
    ];
}

function previewFollowButton(
    user: SuggestedUser,
    isFollowing = false,
    onChange?: (data: { user: Profile }) => void,
): ReactElement<Props> {
    showPreview = true;
    const preview = UserPreview({ user: user as User, children: user.name });
    showPreview = false;

    const content = find(preview, (props) => 'onMenuOpenChange' in props);
    assert.ok(content);

    const button = find(
        render({
            ...content,
            props: {
                ...content.props,
                ...(onChange && { onChange }),
                data: {
                    isFollowing,
                    followsYou: false,
                    isMuting: false,
                    isBlocking: false,
                    isUnavailable: false,
                    user: { ...user, followers_count: 0 } as unknown as Profile,
                },
            },
        }),
        (props) => props['data-test'] === 'user-preview-follow-button',
    );
    assert.ok(button);

    return button;
}

function followFromProfile(user: SuggestedUser): void {
    const profileButton = find(
        FollowButton({ user, isFollowing: false }),
        (props) => props['data-test'] === 'follow-button',
    );
    assert.ok(profileButton);

    (profileButton.props.onSuccess as () => void)();
}

test('a follow from their profile shows in who to follow', () => {
    const user = suggestion('taylor');

    followFromProfile(user);

    assert.equal(suggestionLabel(user), 'Unfollow @taylor');
});

test('blocking someone you follow shows them as not followed in who to follow', () => {
    const user = suggestion('blocked');
    followFromProfile(user);

    const block = find(
        UserSafetyMenu({ user, isMuting: false, isBlocking: false }),
        (props) => props.children === 'Block',
    );
    assert.ok(block);
    (block.props.onClick as () => void)();
    visits.at(-1)?.onSuccess?.();

    assert.equal(suggestionLabel(user), 'Follow @blocked');
});

test('a follow from their preview shows in who to follow', () => {
    const user = suggestion('nuno');

    (previewFollowButton(user).props.onClick as () => void)();
    visits.at(-1)?.onSuccess?.();

    assert.equal(suggestionLabel(user), 'Unfollow @nuno');
});

test('blocking someone you follow from chat shows them as not followed in who to follow', async () => {
    const user = suggestion('chatter');
    followFromProfile(user);

    const block = find(
        ConversationActions({
            target: { kind: 'direct', partner: user },
            title: '@chatter',
            user: suggestion('viewer'),
            isArchived: false,
        }),
        (props) => props['data-test'] === 'confirm-block-button',
    );
    assert.ok(block);
    (block.props.onClick as () => void)();
    await new Promise((resolve) => setTimeout(resolve));

    assert.equal(suggestionLabel(user), 'Follow @chatter');
});

for (const failure of ['onError', 'onNetworkError', 'onCancel'] as const) {
    test(`a follow from their preview that ends in ${failure} leaves who to follow unchanged`, () => {
        const user = suggestion(`jess-${failure}`);

        (previewFollowButton(user).props.onClick as () => void)();
        visits.at(-1)?.[failure]?.();

        assert.equal(suggestionLabel(user), `Follow @jess-${failure}`);
    });
}

test('a cached preview offers to unfollow someone followed elsewhere', () => {
    const user = suggestion('cached');
    followFromProfile(user);

    const button = previewFollowButton(user, false);
    (button.props.onClick as () => void)();

    assert.equal(button.props['aria-label'], 'Unfollow @cached');
    assert.equal(visits.at(-1)?.method, 'delete');
});

test('unfollowing through a cached preview keeps its follower count', () => {
    const user = suggestion('counted');
    followFromProfile(user);
    const changes: { user: Profile }[] = [];

    (previewFollowButton(user, false, (data) => changes.push(data)).props
        .onClick as () => void)();
    visits.at(-1)?.onSuccess?.();

    assert.equal(changes.at(-1)?.user.followers_count, 0);
});

test('a preview unfollow that fails after a block keeps who to follow unfollowed', () => {
    const user = suggestion('raced');
    followFromProfile(user);

    (previewFollowButton(user, true).props.onClick as () => void)();
    const unfollow = visits.at(-1);

    const block = find(
        UserSafetyMenu({ user, isMuting: false, isBlocking: false }),
        (props) => props.children === 'Block',
    );
    assert.ok(block);
    (block.props.onClick as () => void)();
    visits.at(-1)?.onSuccess?.();
    unfollow?.onError?.();

    assert.equal(suggestionLabel(user), 'Follow @raced');
});
