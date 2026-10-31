/// <reference types="node" />
import assert from 'node:assert/strict';
import type { Ref } from 'react';
import { act, createRef, useImperativeHandle, useRef, useState } from 'react';
import { createRoot } from 'react-dom/client';
import { test, vi } from 'vite-plus/test';
import { useEmojiInsertion } from '../../resources/js/hooks/use-emoji-insertion';

// React DOM reads `window.event` to pick an update's priority; nothing else of the DOM is needed here.
vi.stubGlobal('window', globalThis);
vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT', true);

/**
 * The bare minimum React DOM needs to mount a component that renders nothing and commit its updates.
 */
function container(): Element {
    const view = {
        document: { activeElement: null },
        HTMLIFrameElement: class {},
    };
    const node = {
        nodeType: 1,
        nodeName: 'DIV',
        tagName: 'DIV',
        addEventListener: () => {},
        removeEventListener: () => {},
    };

    return {
        ...node,
        ownerDocument: { ...node, defaultView: view },
    } as unknown as Element;
}

type Composer = {
    body: string;
    type: (body: string) => void;
    insertEmoji: (emoji: string) => void;
};

/**
 * Mounts a composer with React's real effect lifecycle, around a textarea that keeps its selection.
 */
function mountComposer(
    body: string,
    selectionStart: number,
    selectionEnd: number,
) {
    const textarea = {
        selectionStart,
        selectionEnd,
        focus: vi.fn(),
        setSelectionRange: vi.fn(),
    };
    const composer = createRef<Composer>();

    function Composer({ handle }: { handle: Ref<Composer> }) {
        const [value, setValue] = useState(body);
        const textareaRef = useRef(textarea as unknown as HTMLTextAreaElement);
        const insertEmoji = useEmojiInsertion({
            body: value,
            updateBody: setValue,
            textareaRef,
            maxLength: 280,
        });

        useImperativeHandle(handle, () => ({
            body: value,
            type: setValue,
            insertEmoji,
        }));

        return null;
    }

    act(() => createRoot(container()).render(<Composer handle={composer} />));

    return { composer, textarea };
}

test('puts the caret back after the emoji when it replaces the same emoji', () => {
    const { composer, textarea } = mountComposer('a🚀b', 1, 3);

    act(() => composer.current?.insertEmoji('🚀'));

    assert.equal(composer.current?.body, 'a🚀b');
    assert.equal(textarea.focus.mock.calls.length, 1);
    assert.deepEqual(textarea.setSelectionRange.mock.calls, [[3, 3]]);
});

test('leaves the caret alone on the next edit after replacing the same emoji', () => {
    const { composer, textarea } = mountComposer('a🚀b', 1, 3);

    act(() => composer.current?.insertEmoji('🚀'));
    textarea.setSelectionRange.mockClear();
    act(() => composer.current?.type('a🚀bx'));

    assert.equal(composer.current?.body, 'a🚀bx');
    assert.equal(textarea.setSelectionRange.mock.calls.length, 0);
});

test('puts the caret right after a new emoji once React has written it', () => {
    const { composer, textarea } = mountComposer('shipped it', 8, 8);

    act(() => composer.current?.insertEmoji('🚀'));

    assert.equal(composer.current?.body, 'shipped 🚀it');
    assert.deepEqual(textarea.setSelectionRange.mock.calls, [[10, 10]]);
});
