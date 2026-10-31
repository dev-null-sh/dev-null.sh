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
const sendMessage = vi.fn();
const visit = vi.fn();
const copyLink = vi.fn();

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
    router: { visit },
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
    CommitReportButton: () => null,
}));

const { CommitSecondaryActions } =
    await import('../../resources/js/components/commit-secondary-actions');
const { Dialog } = await import('../../resources/js/components/ui/dialog');
const { default: CommitController } =
    await import('../../resources/js/actions/App/Http/Controllers/CommitController');
const commit = {
    id: '123e4567-e89b-12d3-a456-426614174000',
    user_id: 'author',
    user: { handle: 'author' },
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
    sendMessage.mockClear();
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

function render(value: Commit = commit, keyShortcut?: string): Element[] {
    cursor = 0;
    refCursor = 0;
    return elements(
        CommitSecondaryActions({ commit: value, keyShortcut }),
    ).flatMap((element) =>
        typeof element.type === 'function' &&
        element.type.name === 'CommitShareMenu'
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

function closeShareMenu(value: Commit = commit): boolean {
    const menu = render(value).find(
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
            tree.find((element) => typeof element.props.onSend === 'function')!
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
            (element) => element.props['data-test'] === 'share-commit-button',
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
        (element) => typeof element.props.onSend === 'function',
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
        (element) => typeof element.props.onSend === 'function',
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
