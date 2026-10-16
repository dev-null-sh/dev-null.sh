import type { RefObject } from 'react';
import { useLayoutEffect, useRef } from 'react';
import { insertAtSelection } from '@/lib/text-selection';

/**
 * Inserts a picked emoji at the caret of the composer's textarea. The caret is put back once React has written the
 * new body, since writing a long value can land after an animation frame and would leave the caret at the end. Focus
 * is left alone so the picker can stay open for the next emoji.
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
}): (emoji: string, textarea: HTMLTextAreaElement | null) => void {
    const caret = useRef<number | null>(null);

    useLayoutEffect(() => {
        const element = textareaRef.current;

        if (caret.current === null || element === null) {
            return;
        }

        element.setSelectionRange(caret.current, caret.current);
        caret.current = null;
    }, [body, textareaRef]);

    return (emoji, textarea) => {
        const element = textareaRef.current;
        const inserted = insertAtSelection(
            body,
            textarea !== null && textarea === element ? element : null,
            emoji,
            maxLength,
        );

        if (inserted === null) {
            return;
        }

        caret.current = inserted.caret;
        updateBody(inserted.value);
    };
}
