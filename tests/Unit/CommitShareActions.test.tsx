/// <reference types="node" />
import assert from 'node:assert/strict';
import * as React from 'react';
import type { ReactElement, ReactNode } from 'react';
import { afterEach, test, vi } from 'vite-plus/test';
import type { Commit, User } from '../../resources/js/types';

let state: unknown[] = [];
let cursor = 0;
let refs: Array<{ current: unknown }> = [];
let refCursor = 0;
let signedIn = true;
let remotesEnabled = true;
let canReport = false;
let relationship: {
    isFollowing: boolean;
    isMuting: boolean;
    isBlocking: boolean;
} | null = null;
const sendMessage = vi.fn();
const visit = vi.fn();
const copyLink = vi.fn();
const signIn = vi.fn();
const visitSafety = vi.fn();
const forgetPreview = vi.fn();
const setIsFollowing = vi.fn();

vi.doMock('react', () => ({
    ...React,
    useState: (initial: unknown) => {
        const index = cursor++;
        if (!(index in state)) state[index] = initial;
        return [state[index], (next: unknown) => (state[index] = next)];
    },
    useRef: (initial: unknown) => {
        const index = refCursor++;
        if (!(index in refs)) refs[index] = { current: initial };
        return refs[index];
    },
}));
vi.doMock('@inertiajs/react', () => ({
    router: { visit, flushAll: () => undefined },
    usePage: () => ({
        props: {
            auth: {
                user: signedIn ? { id: 'viewer' } : null,
                remotes: remotesEnabled ? {} : null,
            },
        },
    }),
}));
vi.doMock('../../resources/js/lib/chat', () => ({ sendMessage }));
vi.doMock('../../resources/js/components/search-dialog', () => ({
    SearchDialog: () => null,
}));
vi.doMock('../../resources/js/components/quoted-commit', () => ({
    QuotedCommit: () => null,
}));
vi.doMock('../../resources/js/components/copy-link-button', () => ({
    useCopyLink: () => ({ copyLink }),
}));
vi.doMock('../../resources/js/components/commit-report-button', () => ({
    CommitReportDialog: () => null,
    useCanReport: () => canReport,
}));
vi.doMock('../../resources/js/components/user-preview', () => ({
    forgetPreview,
    usePreviewRelationship: () => relationship,
}));
vi.doMock('../../resources/js/components/user-safety-menu', () => ({
    BlockUserDialog: () => null,
    visitSafety,
}));
vi.doMock('../../resources/js/components/follow-button', () => ({
    followVisitOptions: () => ({}),
    onFollowHttpException: () => false,
    useIsFollowing: (_user: unknown, isFollowing: boolean) => [
        isFollowing,
        setIsFollowing,
    ],
}));
vi.doMock('../../resources/js/lib/auth', () => ({ signIn }));

const { CommitSecondaryActions } =
    await import('../../resources/js/components/commit-secondary-actions');
const { Dialog } = await import('../../resources/js/components/ui/dialog');
const { default: CommitController } =
    await import('../../resources/js/actions/App/Http/Controllers/CommitController');
const { default: UserBlockController } =
    await import('../../resources/js/actions/App/Http/Controllers/UserBlockController');
const { default: UserFollowController } =
    await import('../../resources/js/actions/App/Http/Controllers/UserFollowController');
const { default: UserMuteController } =
    await import('../../resources/js/actions/App/Http/Controllers/UserMuteController');
const commit = {
    id: '123e4567-e89b-12d3-a456-426614174000',
    author: { id: 'author', handle: 'author' },
    status: 'approved',
    body: 'Shipped it.',
} as Commit;
const recipient = { id: 'recipient', handle: 'friend' } as User;
type Element = ReactElement<Record<string, unknown>>;

afterEach(() => {
    state = [];
    refs = [];
    refCursor = 0;
    signedIn = true;
    remotesEnabled = true;
    canReport = false;
    relationship = null;
    sendMessage.mockClear();
    signIn.mockClear();
    visitSafety.mockClear();
    forgetPreview.mockClear();
    setIsFollowing.mockClear();
    visit.mockClear();
    copyLink.mockClear();
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
});

function elements(node: ReactNode): Element[] {
    if (!React.isValidElement(node)) return [];
    const element = node as Element;
    return [
        element,
        ...React.Children.toArray(element.props.children as ReactNode).flatMap(
            elements,
        ),
    ];
}

function render(
    value: Commit = commit,
    keyShortcut?: string,
    onDelete?: (trigger: HTMLButtonElement | null) => void,
): Element[] {
    cursor = 0;
    refCursor = 0;
    return elements(
        CommitSecondaryActions({ commit: value, keyShortcut, onDelete }),
    ).flatMap((element) =>
        typeof element.type === 'function' &&
        element.type.name === 'CommitMoreMenu'
            ? [
                  element,
                  ...elements(
                      (
                          element.type as (
                              props: Record<string, unknown>,
                          ) => ReactNode
                      )(element.props),
                  ),
              ]
            : [element],
    );
}

function selectSend(value: Commit = commit): void {
    (
        render(value).find(
            (element) => element.props['data-test'] === 'share-in-remotes',
        )!.props.onSelect as () => void
    )();
}

function closeShareMenu(
    value: Commit = commit,
    onDelete?: (trigger: HTMLButtonElement | null) => void,
): boolean {
    const menu = render(value, undefined, onDelete).find(
        (element) =>
            element.props.align === 'end' &&
            typeof element.props.onCloseAutoFocus === 'function',
    )!;
    let prevented = false;

    (menu.props.onCloseAutoFocus as (event: Event) => void)(
        {
            preventDefault: () => {
                prevented = true;
            },
        } as Event,
    );

    return prevented;
}

function pick(tree: Element[]): void {
    const picker = tree.find(
        (element) => element.props.pickerScope === 'share',
    )!;
    (picker.props.onOpenChange as (open: boolean) => void)(false);
    (picker.props.onPickDeveloper as (user: User) => void)(recipient);
}

function confirm(value: Commit = commit): Element[] {
    selectSend(value);
    assert.equal(closeShareMenu(value), true);
    pick(render(value));
    const selected = render(value);
    const picker = selected.find(
        (element) => element.props.pickerScope === 'share',
    )!;
    (picker.props.onCloseAutoFocus as (event: Event) => void)(
        { preventDefault() {} } as Event,
    );
    return render(value);
}

test('only offers sharing for signed-in developers with Remotes and approved commits', () => {
    for (const [user, enabled, status] of [
        [false, true, 'approved'],
        [true, false, 'approved'],
        [true, true, 'pending'],
        [true, true, 'rejected'],
    ] as const) {
        signedIn = user;
        remotesEnabled = enabled;
        const tree = render({ ...commit, status });
        assert.equal(
            tree.some(
                (element) => element.props['data-test'] === 'share-in-remotes',
            ),
            false,
        );
        assert.equal(
            tree.find((element) => typeof element.props.onPending === 'function')!
                .props.canSend,
            false,
        );
    }
});

test('picking a recipient waits for the picker to close and never sends by itself', () => {
    selectSend();
    assert.equal(closeShareMenu(), true);
    pick(render());
    const selected = render();

    assert.equal(
        selected.find((element) => element.type === Dialog)!.props.open,
        false,
    );
    assert.equal(sendMessage.mock.calls.length, 0);
    (
        selected.find((element) => element.props.pickerScope === 'share')!.props
            .onCloseAutoFocus as (event: Event) => void
    )({ preventDefault() {} } as Event);
    assert.equal(
        render().find((element) => element.type === Dialog)!.props.open,
        true,
    );
});

test('confirmation sends the absolute commit link through chat and opens that recipient', () => {
    vi.stubGlobal('window', { location: { origin: 'https://dev-null.sh' } });
    const tree = confirm();
    (
        tree.find(
            (element) => element.props['data-test'] === 'confirm-share-commit',
        )!.props.onClick as () => void
    )();

    assert.equal(sendMessage.mock.calls.length, 1);
    assert.deepEqual(sendMessage.mock.calls[0], [
        { kind: 'direct', partner: recipient },
        {
            body: 'https://dev-null.sh/@author/commits/123e4567-e89b-12d3-a456-426614174000',
            replyTo: null,
            images: [],
            descriptions: [],
            linkPreviewUrl: undefined,
        },
    ]);
    assert.deepEqual(visit.mock.calls[0][0], {
        url: '/remotes/@friend',
        method: 'get',
    });
    assert.equal(
        render().find((element) => element.type === Dialog)!.props.open,
        false,
    );
});

test('cancelling preserves the closing preview and sends nothing', () => {
    const value = { ...commit, body: '', images: [{ id: 'photo' }] } as Commit;
    const tree = confirm(value);
    assert.equal(
        tree.find((element) => element.props.label === 'Sharing')!.props.commit,
        value,
    );
    (
        tree.find((element) => element.type === Dialog)!.props.onOpenChange as (
            open: boolean,
        ) => void
    )(false);

    const closed = render(value);
    assert.equal(
        closed.find((element) => element.type === Dialog)!.props.open,
        false,
    );
    assert.equal(
        closed.find((element) => element.props.label === 'Sharing')!.props
            .commit,
        value,
    );
    assert.equal(sendMessage.mock.calls.length, 0);
});

test('the share menu opens the recipient picker after closing', () => {
    selectSend();
    assert.equal(
        render().some((element) => element.props.pickerScope === 'share'),
        false,
    );
    assert.equal(closeShareMenu(), true);
    assert.ok(
        render().find((element) => element.props.pickerScope === 'share'),
    );
});

test('guests can copy a link without opening Remotes', () => {
    signedIn = false;
    const tree = render();
    assert.ok(
        tree.find(
            (element) => element.props['data-test'] === 'commit-menu-button',
        ),
    );
    (
        tree.find(
            (element) => element.props['data-test'] === 'copy-commit-link',
        )!.props.onSelect as () => void
    )();

    assert.equal(copyLink.mock.calls.length, 1);
    assert.equal(sendMessage.mock.calls.length, 0);
    assert.equal(
        render().some((element) => element.props.pickerScope === 'share'),
        false,
    );
});

test('the copy shortcut still copies directly without adding a tab stop', () => {
    const shortcut = render(commit, 'y').find(
        (element) => element.props['aria-keyshortcuts'] === 'y',
    )!;
    assert.equal(shortcut.props.tabIndex, -1);
    (shortcut.props.onClick as () => void)();

    assert.equal(copyLink.mock.calls.length, 1);
    assert.equal(sendMessage.mock.calls.length, 0);
});

test('sharing an absolute permalink preserves its host', () => {
    vi.stubGlobal('window', { location: { origin: 'http://localhost:8000' } });
    vi.spyOn(CommitController, 'show').mockReturnValue({
        url: 'https://dev-null.sh/@author/commits/123e4567-e89b-12d3-a456-426614174000',
        method: 'get',
    });
    const tree = confirm();
    (
        tree.find(
            (element) => element.props['data-test'] === 'confirm-share-commit',
        )!.props.onClick as () => void
    )();

    assert.equal(
        sendMessage.mock.calls[0][1].body,
        'https://dev-null.sh/@author/commits/123e4567-e89b-12d3-a456-426614174000',
    );
});

test('opening the picker prevents the Share menu from stealing focus', () => {
    selectSend();

    assert.equal(closeShareMenu(), true);
    assert.ok(
        render().some((element) => element.props.pickerScope === 'share'),
    );

    // Once the selection has been consumed, another close cannot reopen it.
    assert.equal(closeShareMenu(), false);
});

test('cancelling the recipient picker restores focus to Share', () => {
    selectSend();
    assert.equal(closeShareMenu(), true);

    const pickerTree = render();
    const menu = pickerTree.find(
        (element) => typeof element.props.onPending === 'function',
    )!;
    const focus = vi.fn();
    (menu.props.triggerRef as { current: unknown }).current = { focus };

    const picker = pickerTree.find(
        (element) => element.props.pickerScope === 'share',
    )!;
    (picker.props.onOpenChange as (open: boolean) => void)(false);
    const preventDefault = vi.fn();
    (
        render().find(
            (element) => element.props.pickerScope === 'share',
        )!.props.onCloseAutoFocus as (event: Event) => void
    )({ preventDefault } as unknown as Event);

    assert.equal(preventDefault.mock.calls.length, 1);
    assert.equal(focus.mock.calls.length, 1);
    assert.equal(sendMessage.mock.calls.length, 0);
    assert.equal(
        render().some((element) => element.props.pickerScope === 'share'),
        false,
    );
});

test('closing the confirmation returns focus to Share', () => {
    const tree = confirm();
    const menu = tree.find(
        (element) => typeof element.props.onPending === 'function',
    )!;
    const focus = vi.fn();
    (menu.props.triggerRef as { current: unknown }).current = { focus };

    const cancel = tree.find(
        (element) => element.props.variant === 'outline',
    )!;
    (cancel.props.onClick as () => void)();

    const preventDefault = vi.fn();
    const content = render().find(
        (element) =>
            typeof element.props.className === 'string' &&
            element.props.className.includes('grid-rows-[auto_minmax(0,1fr)_auto]'),
    )!;
    (content.props.onCloseAutoFocus as (event: Event) => void)(
        { preventDefault } as unknown as Event,
    );

    assert.equal(preventDefault.mock.calls.length, 1);
    assert.equal(focus.mock.calls.length, 1);
    assert.equal(sendMessage.mock.calls.length, 0);
});

test('closing Share without selecting Send does not open the picker', () => {
    assert.equal(closeShareMenu(), false);
    assert.equal(
        render().some((element) => element.props.pickerScope === 'share'),
        false,
    );
    assert.equal(sendMessage.mock.calls.length, 0);
});

test('copying the link does not open the recipient picker', () => {
    const copy = render().find(
        (element) => element.props['data-test'] === 'copy-commit-link',
    )!;

    (copy.props.onSelect as () => void)();
    assert.equal(closeShareMenu(), false);
    assert.equal(copyLink.mock.calls.length, 1);
    assert.equal(
        render().some((element) => element.props.pickerScope === 'share'),
        false,
    );
});

const userCommit = {
    ...commit,
    author: { ...commit.author, type: 'user' },
} as Commit;

function select(
    dataTest: string,
    value: Commit = commit,
    onDelete?: (trigger: HTMLButtonElement | null) => void,
): void {
    (
        render(value, undefined, onDelete).find(
            (element) => element.props['data-test'] === dataTest,
        )!.props.onSelect as () => void
    )();
}

function component(tree: Element[], name: string): Element | undefined {
    return tree.find(
        (element) =>
            typeof element.type === 'function' && element.type.name === name,
    );
}

function authorItems(onBlock: () => void = () => undefined): Element[] {
    const items = component(render(userCommit), 'CommitAuthorItems')!;

    return elements(
        (items.type as (props: Record<string, unknown>) => ReactNode)({
            ...items.props,
            onBlock,
        }),
    );
}

function authorItem(dataTest: string, onBlock?: () => void): Element {
    return authorItems(onBlock).find(
        (element) => element.props['data-test'] === dataTest,
    )!;
}

function text(element: Element): string {
    return elements(element)
        .flatMap((child) =>
            React.Children.toArray(child.props.children as ReactNode),
        )
        .filter((child) => typeof child === 'string')
        .join('');
}

test('reporting opens the report dialog once the menu has closed', () => {
    canReport = true;
    const dialog = () => component(render(), 'CommitReportDialog')!;

    select('report-commit');
    assert.equal(dialog().props.open, false);
    assert.equal(closeShareMenu(), true);
    assert.equal(dialog().props.open, true);
});

test('only offers reporting when the viewer can report the commit', () => {
    assert.equal(
        render().some(
            (element) => element.props['data-test'] === 'report-commit',
        ),
        false,
    );
});

test('deleting hands the menu trigger to the card once the menu has closed', () => {
    const onDelete = vi.fn();
    const trigger = { focus: vi.fn() };

    select('delete-commit-button', commit, onDelete);
    (
        render(commit, undefined, onDelete).find(
            (element) => typeof element.props.onPending === 'function',
        )!.props.triggerRef as { current: unknown }
    ).current = trigger;
    assert.equal(onDelete.mock.calls.length, 0);
    assert.equal(closeShareMenu(commit, onDelete), true);

    assert.deepEqual(onDelete.mock.calls, [[trigger]]);
});

test('only offers deleting when the card can delete the commit', () => {
    assert.equal(
        render().some(
            (element) => element.props['data-test'] === 'delete-commit-button',
        ),
        false,
    );
    assert.ok(
        render(commit, undefined, () => undefined).some(
            (element) => element.props['data-test'] === 'delete-commit-button',
        ),
    );
});

test('offers following, muting and blocking the author of other people\'s commits only', () => {
    const author = userCommit.author;

    assert.ok(component(render(userCommit), 'CommitAuthorItems'));
    assert.equal(
        component(
            render({
                ...userCommit,
                author: { ...author, id: 'viewer' },
            } as Commit),
            'CommitAuthorItems',
        ),
        undefined,
    );
    assert.equal(
        component(
            render({
                ...userCommit,
                author: { ...author, type: 'organization' },
            } as Commit),
            'CommitAuthorItems',
        ),
        undefined,
    );
});

test('waits for the author\'s preview before offering follow, mute or block', () => {
    for (const dataTest of [
        'follow-commit-author',
        'mute-commit-author',
        'block-commit-author',
    ]) {
        assert.equal(authorItem(dataTest).props.disabled, true);
    }
});

test('follows the author, then shares that they are followed', () => {
    relationship = { isFollowing: false, isMuting: false, isBlocking: false };
    const follow = authorItem('follow-commit-author');
    assert.equal(follow.props.disabled, false);
    assert.equal(text(follow), 'Follow @author');

    (follow.props.onSelect as () => void)();

    assert.deepEqual(
        visit.mock.calls[0][0],
        UserFollowController.store(userCommit.author),
    );
    (visit.mock.calls[0][1] as { onSuccess: () => void }).onSuccess();
    assert.deepEqual(setIsFollowing.mock.calls, [[true]]);
});

test('unfollows an author already followed', () => {
    relationship = { isFollowing: true, isMuting: false, isBlocking: false };
    const unfollow = authorItem('follow-commit-author');
    assert.equal(text(unfollow), 'Unfollow @author');

    (unfollow.props.onSelect as () => void)();

    assert.deepEqual(
        visit.mock.calls[0][0],
        UserFollowController.destroy(userCommit.author),
    );
});

test('guests are only offered following, which signs them in', () => {
    signedIn = false;
    const items = authorItems();
    assert.equal(
        items.some((element) =>
            ['mute-commit-author', 'block-commit-author'].includes(
                element.props['data-test'] as string,
            ),
        ),
        false,
    );

    const follow = authorItem('follow-commit-author');
    assert.equal(follow.props.disabled, false);
    (follow.props.onSelect as () => void)();

    assert.equal(signIn.mock.calls.length, 1);
    assert.equal(visit.mock.calls.length, 0);
});

test('muting and unmuting forget the author\'s stale preview', () => {
    for (const isMuting of [false, true]) {
        visitSafety.mockClear();
        forgetPreview.mockClear();
        relationship = { isFollowing: false, isMuting, isBlocking: false };
        const mute = authorItem('mute-commit-author');
        assert.equal(
            text(mute),
            `${isMuting ? 'Unmute' : 'Mute'} @author`,
        );

        (mute.props.onSelect as () => void)();

        assert.deepEqual(
            visitSafety.mock.calls[0][0],
            isMuting
                ? UserMuteController.destroy(userCommit.author)
                : UserMuteController.store(userCommit.author),
        );
        (visitSafety.mock.calls[0][1] as { onSuccess: () => void }).onSuccess();
        assert.deepEqual(forgetPreview.mock.calls, [[userCommit.author]]);
    }
});

test('blocking asks for confirmation instead of blocking right away', () => {
    relationship = { isFollowing: false, isMuting: false, isBlocking: false };
    const onBlock = vi.fn();
    const block = authorItem('block-commit-author', onBlock);
    assert.equal(text(block), 'Block @author');
    assert.equal(block.props.variant, 'destructive');

    (block.props.onSelect as () => void)();

    assert.equal(onBlock.mock.calls.length, 1);
    assert.equal(visitSafety.mock.calls.length, 0);
});

test('a blocked author can be unblocked but not followed', () => {
    relationship = { isFollowing: false, isMuting: false, isBlocking: true };
    const onBlock = vi.fn();
    assert.equal(
        authorItems().some(
            (element) => element.props['data-test'] === 'follow-commit-author',
        ),
        false,
    );

    const unblock = authorItem('block-commit-author', onBlock);
    assert.equal(text(unblock), 'Unblock @author');
    (unblock.props.onSelect as () => void)();

    assert.equal(onBlock.mock.calls.length, 0);
    assert.deepEqual(
        visitSafety.mock.calls[0][0],
        UserBlockController.destroy(userCommit.author),
    );
});

test('blocking confirms in a dialog once the menu has closed', () => {
    const dialog = () => component(render(userCommit), 'BlockUserDialog')!;
    const items = component(render(userCommit), 'CommitAuthorItems')!;

    (items.props.onBlock as () => void)();
    assert.equal(dialog().props.open, false);
    assert.equal(closeShareMenu(userCommit), true);
    assert.equal(dialog().props.open, true);
});
