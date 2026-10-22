import assert from 'node:assert/strict';
import * as React from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { beforeEach, test, vi } from 'vite-plus/test';
import type { Commit, CommitTranslation } from '../../resources/js/types';

let state: unknown[] = [];
let cursor = 0;
let processing = false;
let pending: {
    resolve: (response: { translation: CommitTranslation }) => void;
    reject: (error: Error) => void;
};
const submit = vi.fn(() => {
    processing = true;

    return new Promise<{ translation: CommitTranslation }>(
        (resolve, reject) => {
            pending = { resolve, reject };
        },
    ).finally(() => {
        processing = false;
    });
});
const toastError = vi.fn();

vi.doMock('react', () => ({
    ...React,
    useRef: () => ({ current: null }),
    useState: (initial: unknown) => {
        const index = cursor++;

        if (!(index in state)) {
            state[index] = initial;
        }

        return [
            state[index],
            (value: unknown) => {
                state[index] = value;
            },
        ];
    },
}));
vi.doMock('@inertiajs/react', () => ({
    usePage: () => ({ props: { auth: { user: { id: 'reader' } } } }),
    useHttp: () => ({ processing, submit, transform: vi.fn() }),
}));
vi.doMock('@/hooks/use-deleted-resources', () => ({
    useCommitNotFound: () => vi.fn(),
}));
vi.doMock('sonner', () => ({ toast: { error: toastError } }));

const { useCommitTranslation } =
    await import('../../resources/js/components/commit-translation');

const translation: CommitTranslation = {
    language: 'en',
    source_language: 'de',
    source_name: 'German',
    available: true,
    translated: {
        body_segments: [{ type: 'text', text: 'Good morning' }],
        preview_segments: null,
    },
};
const commit = {
    id: 'commit',
    body: 'Guten Morgen',
    body_segments: [{ type: 'text', text: 'Guten Morgen' }],
    translation: { ...translation, translated: null },
} as Commit;

function render() {
    cursor = 0;
    const view = useCommitTranslation(commit);

    assert.ok(view.button);

    return {
        ...view,
        button: view.button,
        html: renderToStaticMarkup(view.button),
    };
}

beforeEach(() => {
    state = [];
    processing = false;
    vi.clearAllMocks();
});

test('shows a spinner only while translating and restores Translate after showing the original', async () => {
    const initial = render();
    assert.match(initial.html, />Translate<\/span>/);
    assert.match(initial.html, /lucide-languages/);
    assert.doesNotMatch(initial.html, /animate-spin/);
    assert.equal(initial.button.props.disabled, false);
    assert.equal(initial.button.props['aria-busy'], false);
    assert.equal(initial.button.props['aria-label'], 'Translate commit');

    initial.button.props.onClick();

    const loading = render();
    assert.match(loading.html, />Translating…<\/span>/);
    assert.match(loading.html, /animate-spin/);
    assert.doesNotMatch(loading.html, /lucide-languages/);
    assert.equal(loading.button.props.disabled, true);
    assert.equal(loading.button.props['aria-busy'], true);
    assert.equal(loading.button.props['aria-label'], 'Translating commit');

    pending.resolve({ translation });

    await vi.waitFor(() => {
        assert.match(render().html, />Translated<\/span>/);
    });

    const completed = render();
    assert.doesNotMatch(completed.html, /animate-spin/);
    assert.match(completed.html, /lucide-languages/);
    assert.equal(completed.button.props.disabled, true);
    assert.equal(completed.button.props['aria-busy'], false);
    assert.equal(completed.button.props['aria-label'], 'Commit translated');
    assert.deepEqual(completed.segments, translation.translated!.body_segments);

    const showOriginal = React.Children.toArray(
        completed.notice!.props.children.props.children,
    ).find((child) => React.isValidElement(child) && child.type === 'button');
    assert.ok(React.isValidElement<{ onClick: () => void }>(showOriginal));
    showOriginal.props.onClick();

    const original = render();
    assert.match(original.html, />Translate<\/span>/);
    assert.equal(original.button.props.disabled, false);
    assert.deepEqual(original.segments, commit.body_segments);

    original.button.props.onClick();

    assert.match(render().html, />Translated<\/span>/);
    assert.equal(submit.mock.calls.length, 1);
});

test('removes the spinner and enables Translate again after a failed request', async () => {
    render().button.props.onClick();
    assert.match(render().html, /animate-spin/);

    pending.reject(new Error('Provider unavailable'));

    await vi.waitFor(() => {
        assert.equal(toastError.mock.calls.length, 1);
    });

    const failed = render();
    assert.match(failed.html, />Translate<\/span>/);
    assert.doesNotMatch(failed.html, /animate-spin/);
    assert.equal(failed.button.props.disabled, false);
    assert.equal(failed.button.props['aria-busy'], false);
    assert.deepEqual(failed.segments, commit.body_segments);
});
