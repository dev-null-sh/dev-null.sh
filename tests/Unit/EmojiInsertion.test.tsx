/// <reference types="node" />
import assert from 'node:assert/strict';
import * as React from 'react';
import { afterAll, beforeEach, test, vi } from 'vite-plus/test';

let layoutEffect: () => void = () => {};

// The layout effect runs when the test says React has written the new body.
vi.doMock('react', () => ({
    ...React,
    useLayoutEffect: (effect: () => void) => {
        layoutEffect = effect;
    },
    useRef: (initial: unknown) => ({ current: initial }),
}));

afterAll(() => {
    vi.doUnmock('react');
});

const { useEmojiInsertion } =
    await import('../../resources/js/hooks/use-emoji-insertion');

/**
 * A textarea that kept its selection after the picker took the focus, as browsers do.
 */
function blurredTextarea(
    selectionStart: number,
    selectionEnd = selectionStart,
) {
    const focus = vi.fn();
    const setSelectionRange = vi.fn();
    const textarea = {
        selectionStart,
        selectionEnd,
        focus,
        setSelectionRange,
    } as unknown as HTMLTextAreaElement;

    return { textarea, focus, setSelectionRange };
}

const updateBody = vi.fn();

beforeEach(() => {
    vi.clearAllMocks();
});

test('inserts where the caret was and puts the focus back right after the emoji', () => {
    const { textarea, focus, setSelectionRange } = blurredTextarea(8);
    const insertEmoji = useEmojiInsertion({
        body: 'shipped it',
        updateBody,
        textareaRef: { current: textarea },
        maxLength: 280,
    });

    insertEmoji('🚀');
    layoutEffect();

    assert.deepEqual(updateBody.mock.calls, [['shipped 🚀it']]);
    assert.equal(focus.mock.calls.length, 1);
    assert.deepEqual(setSelectionRange.mock.calls, [[10, 10]]);
});

test('replaces the text that was selected', () => {
    const { textarea } = blurredTextarea(8, 10);

    useEmojiInsertion({
        body: 'shipped it',
        updateBody,
        textareaRef: { current: textarea },
        maxLength: 280,
    })('🎉');

    assert.deepEqual(updateBody.mock.calls, [['shipped 🎉']]);
});

test('leaves a full composer as it is and returns to it', () => {
    const { textarea, focus, setSelectionRange } = blurredTextarea(3);

    useEmojiInsertion({
        body: 'abc',
        updateBody,
        textareaRef: { current: textarea },
        maxLength: 3,
    })('🔥');
    layoutEffect();

    assert.equal(updateBody.mock.calls.length, 0);
    assert.equal(focus.mock.calls.length, 1);
    assert.deepEqual(setSelectionRange.mock.calls, [[3, 3]]);
});

test('keeps the caret at the start of a full restored draft', () => {
    const { textarea, focus, setSelectionRange } = blurredTextarea(0);

    useEmojiInsertion({
        body: 'abc',
        updateBody,
        textareaRef: { current: textarea },
        maxLength: 3,
    })('🔥');

    assert.equal(updateBody.mock.calls.length, 0);
    assert.equal(focus.mock.calls.length, 1);
    assert.deepEqual(setSelectionRange.mock.calls, [[0, 0]]);
});

test('appends when the textarea is gone', () => {
    useEmojiInsertion({
        body: 'shipped',
        updateBody,
        textareaRef: { current: null },
        maxLength: 280,
    })(' 🔥');
    layoutEffect();

    assert.deepEqual(updateBody.mock.calls, [['shipped 🔥']]);
});
