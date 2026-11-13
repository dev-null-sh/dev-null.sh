import type { RefObject } from 'react';
import { useLayoutEffect, useRef } from 'react';
import { insertAtSelection } from '@/lib/text-selection';

/**
 * Inserts a picked emoji where the caret was in the composer's textarea. A textarea keeps its selection while the
 * picker has the focus, so this works whether the picker was opened with the pointer or the keyboard. Once React has
 * written the new body, the focus goes back to the textarea with the caret right after the emoji.
 */
export function useEmojiInsertion({
    body,
    updateBody,
    textareaRef,
    maxLength,
}: {
    body: string;
    updateBody: (body: string) => void;
    textareaRef: RefObject<HTMLTextAreaElement | null>;
    maxLength: number;
}): (emoji: string) => void {
    const caret = useRef<number | null>(null);

    useLayoutEffect(() => {
        const element = textareaRef.current;

        if (caret.current === null || element === null) {
            return;
        }

        element.focus();
        element.setSelectionRange(caret.current, caret.current);
        caret.current = null;
    }, [body, textareaRef]);

    return (emoji) => {
        const element = textareaRef.current;
        const inserted = insertAtSelection(body, element, emoji, maxLength);

        if (inserted === null) {
            // Focusing a restored draft moves a caret at its start to the end, so the selection is put back after.
            if (element !== null) {
                const { selectionStart, selectionEnd } = element;

                element.focus();
                element.setSelectionRange(selectionStart, selectionEnd);
            }

            return;
        }

        // An emoji picked over the same emoji leaves the body as it was, so React skips the effect: return here.
        if (inserted.value === body) {
            caret.current = null;

            if (element !== null) {
                element.focus();
                element.setSelectionRange(inserted.caret, inserted.caret);
            }

            return;
        }

        caret.current = inserted.caret;
        updateBody(inserted.value);
    };
}
