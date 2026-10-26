/// <reference types="node" />
import assert from 'node:assert/strict';
import * as React from 'react';
import type { ReactElement, ReactNode } from 'react';
import { test, vi } from 'vite-plus/test';
import type { Commit } from '../../resources/js/types';

const image = new File(['restored'], 'draft.png', { type: 'image/png' });
const updateImages = vi.fn();
const drop = vi.fn();
const user = { id: 'viewer', handle: 'viewer' };
const draft = {
    key: 'stash:viewer:quote',
    body: 'A restored quote',
    images: [image],
    descriptions: ['A restored screenshot'],
    isRestored: true,
    updateBody: vi.fn(),
    updateImages,
    drop,
};

vi.doMock('react', () => ({
    ...React,
    useLayoutEffect: (effect: () => void | (() => void)) => effect(),
    useMemo: (factory: () => unknown) => factory(),
    useSyncExternalStore: (_subscribe: unknown, snapshot: () => unknown) =>
        snapshot(),
    useRef: (initial: unknown) => ({ current: initial }),
    useState: (initial: unknown) => [
        initial === false ? true : initial,
        vi.fn(),
    ],
}));
vi.doMock('@inertiajs/react', async () => ({
    ...(await vi.importActual('@inertiajs/react')),
    usePage: () => ({ props: { auth: { user } } }),
}));
vi.doMock('../../resources/js/hooks/use-user', () => ({
    useUser: () => ({ user }),
}));
vi.doMock('../../resources/js/hooks/use-stash', () => ({
    useStash: () => draft,
    useHasStash: () => true,
    moveCaretToEnd: vi.fn(),
}));
vi.doMock('../../resources/js/components/composer-preview', async () => ({
    ...(await vi.importActual(
        '../../resources/js/components/composer-preview',
    )),
    useComposerPreview: () => ({
        isPreviewing: false,
        handleShortcut: () => false,
    }),
}));

const { Form } = await import('@inertiajs/react');
const { CommitQuoteButton } =
    await import('../../resources/js/components/commit-quote-button');
const { ImagePickerButton, ImagePickerPreviews } =
    await import('../../resources/js/components/commit-image-picker');
const { StashLabel } =
    await import('../../resources/js/components/stash-label');
const { LinkCardPicker } =
    await import('../../resources/js/components/link-card-picker');
const { default: InputError } =
    await import('../../resources/js/components/input-error');

function elements(node: ReactNode): ReactElement<Record<string, unknown>>[] {
    if (!React.isValidElement(node)) {
        return [];
    }

    const element = node as ReactElement<Record<string, unknown>>;

    return [
        element,
        ...React.Children.toArray(element.props.children as ReactNode).flatMap(
            elements,
        ),
    ];
}

test('quotes submit restored images and clear the complete draft only on success or discard', async () => {
    draft.body = 'ghp_' + 'Ab3dEf6hIj9kLm2nOp5qRs8tUv1wXy4zABCD';
    const commit = {
        id: 'quoted',
        user_id: 'author',
        user: { handle: 'author' },
        status: 'approved',
    } as Commit;
    const button = CommitQuoteButton({ commit })!;
    const dialog = React.Children.toArray(
        button.props.children,
    )[1] as ReactElement<{
        children: ReactElement<{ commit: Commit; onClose: () => void }>;
    }>;
    const composer = dialog.props.children;
    const renderComposer = composer.type as (
        props: typeof composer.props,
    ) => ReactElement;
    const form = elements(renderComposer(composer.props)).find(
        (element) => element.type === Form,
    )!;
    const transform = form.props.transform as (
        data: Record<string, unknown>,
    ) => Record<string, unknown>;

    assert.deepEqual(
        transform({ body: draft.body, quoted_commit_id: commit.id }),
        {
            body: draft.body,
            quoted_commit_id: commit.id,
            commit_rules_accepted: true,
            images: [image],
            image_descriptions: ['A restored screenshot'],
        },
    );

    const renderFields = form.props.children as (props: {
        processing: boolean;
        errors: Record<string, string>;
    }) => ReactNode;
    const fields = elements(renderFields({ processing: false, errors: {} }));
    const beforeSubmit = form.props.onBefore as () => boolean;
    assert.equal(beforeSubmit(), true);
    assert.equal(drop.mock.calls.length, 0);
    assert.equal(beforeSubmit(), true);

    const previews = fields.find(
        (element) => element.type === ImagePickerPreviews,
    )!;
    assert.deepEqual(previews.props.images, [image]);

    const picker = fields.find(
        (element) => element.type === ImagePickerButton,
    )!;
    assert.equal(picker.props.count, 1);
    const added = new File(['new'], 'new.png', {
        type: 'image/png',
        lastModified: 1,
    });
    await (picker.props.onPick as (files: File[]) => Promise<void>)([added]);
    const [pickedImages, descriptions] = updateImages.mock.lastCall as [
        File[],
        string[],
    ];
    assert.equal(pickedImages[0], image);
    assert.notEqual(pickedImages[1], added);
    assert.equal(pickedImages[1].name, added.name);
    assert.equal(pickedImages[1].type, added.type);
    assert.equal(await pickedImages[1].text(), await added.text());
    assert.deepEqual(descriptions, ['A restored screenshot', '']);

    const dropzone = elements(renderComposer(composer.props)).find(
        (element) => element.props.onDragEnter !== undefined,
    )!;
    const dropped = new File(['dropped'], 'dropped.png', {
        type: 'image/png',
    });
    (dropzone.props.onDrop as (event: unknown) => void)({
        dataTransfer: { types: ['Files'], files: [dropped] },
        preventDefault: vi.fn(),
    });
    assert.deepEqual(updateImages.mock.lastCall, [
        [image, dropped],
        ['A restored screenshot', ''],
    ]);

    const onSuccess = form.props.onSuccess as (page: {
        flash: { toast?: { type: 'error'; message: string } };
    }) => void;
    updateImages.mockClear();
    onSuccess({
        flash: { toast: { type: 'error', message: 'Slow down' } },
    });
    assert.equal(updateImages.mock.calls.length, 0);
    assert.equal(drop.mock.calls.length, 0);

    onSuccess({ flash: {} });
    assert.deepEqual(updateImages.mock.lastCall, [[], []]);
    assert.equal(drop.mock.calls.length, 1);

    vi.stubGlobal('document', { getElementById: () => null });
    try {
        const stash = fields.find((element) => element.type === StashLabel)!;
        updateImages.mockClear();
        (stash.props.onDrop as () => void)();
        assert.deepEqual(updateImages.mock.lastCall, [[], []]);
        assert.equal(drop.mock.calls.length, 2);
    } finally {
        vi.unstubAllGlobals();
    }
});

test('escape in the preview returns to writing without closing the quote', () => {
    const commit = {
        id: 'quoted',
        user_id: 'author',
        user: { handle: 'author' },
        status: 'approved',
    } as Commit;
    const button = CommitQuoteButton({ commit })!;
    const dialog = React.Children.toArray(
        button.props.children,
    )[1] as ReactElement<{
        children: ReactElement<{ commit: Commit; onClose: () => void }>;
    }>;
    const composer = dialog.props.children;
    const renderComposer = composer.type as (
        props: typeof composer.props,
    ) => ReactElement<{ onEscapeKeyDown: (event: unknown) => void }>;
    const { onEscapeKeyDown } = renderComposer(composer.props).props;

    class FakeElement {
        constructor(private readonly isInPreview: boolean) {}

        closest(selector: string) {
            return this.isInPreview && selector === '[data-composer-preview]'
                ? this
                : null;
        }
    }

    vi.stubGlobal('Element', FakeElement);
    vi.stubGlobal('document', { querySelector: () => null });
    try {
        const escapeFrom = (isInPreview: boolean) => {
            const preventDefault = vi.fn();
            onEscapeKeyDown({
                target: new FakeElement(isInPreview),
                preventDefault,
            });

            return preventDefault.mock.calls.length;
        };

        assert.equal(escapeFrom(true), 1);
        assert.equal(escapeFrom(false), 0);
    } finally {
        vi.unstubAllGlobals();
    }
});

test('quotes let the author pick which link gets the card', () => {
    draft.body =
        'Read https://laravel.com/docs first, then https://github.com/laravel/framework';
    const commit = {
        id: 'quoted',
        user_id: 'author',
        user: { handle: 'author' },
        status: 'approved',
    } as Commit;
    const button = CommitQuoteButton({ commit })!;
    const dialog = React.Children.toArray(
        button.props.children,
    )[1] as ReactElement<{
        children: ReactElement<{ commit: Commit; onClose: () => void }>;
    }>;
    const composer = dialog.props.children;
    const renderComposer = composer.type as (
        props: typeof composer.props,
    ) => ReactElement;
    const form = elements(renderComposer(composer.props)).find(
        (element) => element.type === Form,
    )!;
    const renderFields = form.props.children as (props: {
        processing: boolean;
        errors: Record<string, string>;
    }) => ReactNode;
    const message = 'The link card must be one of the links in the body.';
    const fields = elements(
        renderFields({
            processing: false,
            errors: { link_preview_url: message },
        }),
    );

    const picker = fields.find((element) => element.type === LinkCardPicker)!;
    assert.deepEqual((picker.props.linkCard as { links: string[] }).links, [
        'https://laravel.com/docs',
        'https://github.com/laravel/framework',
    ]);
    assert.ok(
        fields.some(
            (element) =>
                element.type === InputError &&
                element.props.message === message,
        ),
    );
});
