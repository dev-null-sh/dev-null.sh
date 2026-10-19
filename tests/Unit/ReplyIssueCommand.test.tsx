/// <reference types="node" />
import assert from 'node:assert/strict';
import * as React from 'react';
import type { ReactElement, ReactNode } from 'react';
import { beforeEach, test, vi } from 'vite-plus/test';

let state: unknown[] = [];
let cursor = 0;
const draft = {
    body: '',
    images: [] as File[],
    descriptions: [] as string[],
    isRestored: false,
    updateBody: vi.fn(),
    updateImages: vi.fn(),
    drop: vi.fn(),
};
const user = { id: 'viewer', username: 'viewer', lane: 1 };
const http = {
    processing: false,
    errors: {},
    transform: vi.fn(),
    setData: vi.fn(),
    setError: vi.fn(),
    submit: vi.fn(() => new Promise(() => {})),
};

vi.doMock('react', () => ({
    ...React,
    useEffect: () => {},
    useMemo: (factory: () => unknown) => factory(),
    useId: () => `reply-${cursor++}`,
    useSyncExternalStore: (_subscribe: unknown, snapshot: () => unknown) =>
        snapshot(),
    useRef: (initial: unknown) => {
        const index = cursor++;
        if (!(index in state)) {
            state[index] = { current: initial };
        }
        return state[index];
    },
    useState: (initial: unknown) => {
        const index = cursor++;
        if (!(index in state)) {
            state[index] = typeof initial === 'function' ? initial() : initial;
        }
        return [
            state[index],
            (value: unknown) => {
                state[index] =
                    typeof value === 'function' ? value(state[index]) : value;
            },
        ];
    },
}));
vi.doMock('@inertiajs/react', async () => ({
    ...(await vi.importActual('@inertiajs/react')),
    usePage: () => ({ props: { auth: { user, isAdmin: false } } }),
    useHttp: () => http,
}));
vi.doMock('../../resources/js/hooks/use-user', () => ({
    useUser: () => ({ user }),
}));
vi.doMock('../../resources/js/hooks/use-stash', () => ({
    useStash: () => draft,
    useHasStash: () => false,
    moveCaretToEnd: vi.fn(),
}));

const { ReplyComposer } =
    await import('../../resources/js/components/commit-thread');
const { IssueCommandBadge, IssueCommandSuggestions } =
    await import('../../resources/js/components/issue-command');
const { ImagePickerButton } =
    await import('../../resources/js/components/commit-image-picker');
const { MentionTextarea } =
    await import('../../resources/js/components/mention-textarea');

type Element = ReactElement<Record<string, unknown>>;

function elements(node: ReactNode): Element[] {
    if (!React.isValidElement(node)) {
        return [];
    }
    const element = node as Element;
    return [
        element,
        ...React.Children.toArray(element.props.children as ReactNode).flatMap(
            elements,
        ),
    ];
}

const parent = { id: 'parent', user: { username: 'author' } } as Parameters<
    typeof ReplyComposer
>[0]['parent'];

function render() {
    cursor = 0;
    const form = ReplyComposer({
        parent,
        onCancel: vi.fn(),
        onReplied: vi.fn(),
    }) as Element;
    const fields = elements(form);
    const find = (key: string, value: unknown) =>
        fields.find((element) => element.props[key] === value);
    const textarea = fields.find((element) => element.type === MentionTextarea)!;
    return { form, fields, find, textarea };
}

function keyDown(key: string, modifiers: { metaKey?: boolean } = {}) {
    const event = {
        key,
        metaKey: false,
        ctrlKey: false,
        shiftKey: false,
        ...modifiers,
        nativeEvent: { isComposing: false },
        currentTarget: { selectionStart: 0, selectionEnd: 0, form: null },
        preventDefault: vi.fn(),
        stopPropagation: vi.fn(),
    };
    (render().textarea.props.onKeyDown as (event: unknown) => void)(event);
    return event;
}

beforeEach(() => {
    vi.clearAllMocks();
    state = [];
    draft.body = '';
    draft.images = [];
});

test('a slash in a focused reply opens the command menu, and Escape closes it without cancelling the reply', () => {
    draft.body = '/';
    (render().textarea.props.onFocus as (event: unknown) => void)({});
    assert.ok(
        render().fields.some((element) => element.type === IssueCommandSuggestions),
    );

    const escape = keyDown('Escape');
    assert.equal(escape.stopPropagation.mock.calls.length, 1);
    assert.equal(
        render().fields.some((element) => element.type === IssueCommandSuggestions),
        false,
    );
});

test('typing a command in a reply turns it into an issue that opens instead of replying', () => {
    (render().textarea.props.onValueChange as (value: string) => void)(
        '/bug Replies lose slash commands',
    );
    assert.deepEqual(draft.updateBody.mock.calls.at(-1), [
        'Replies lose slash commands',
    ]);
    draft.body = 'Replies lose slash commands';

    const view = render();
    assert.ok(view.fields.some((element) => element.type === IssueCommandBadge));
    assert.ok(view.find('data-test', 'open-issue-button'));
    assert.equal(view.find('data-test', 'submit-reply-button'), undefined);
    assert.equal(
        view.fields.some((element) => element.type === ImagePickerButton),
        false,
    );

    (view.form.props.onSubmit as (event: unknown) => void)({
        preventDefault: vi.fn(),
    });
    assert.equal(http.submit.mock.calls.length, 0);

    keyDown('Enter', { metaKey: true });
    assert.equal(http.submit.mock.calls.length, 1);
    const [route] = http.submit.mock.calls[0] as unknown as [{ url: string }];
    assert.equal(route.url, '/issues');
    const payload = http.transform.mock.calls[0][0] as () => unknown;
    assert.deepEqual(payload(), {
        command: '/bug',
        body: 'Replies lose slash commands',
    });
});

test('a reply with images keeps a typed command as text', () => {
    draft.images = [new File(['shot'], 'shot.png', { type: 'image/png' })];
    (render().textarea.props.onValueChange as (value: string) => void)(
        '/bug ',
    );
    assert.deepEqual(draft.updateBody.mock.calls.at(-1), ['/bug ']);
    assert.ok(render().find('data-test', 'submit-reply-button'));
});
