import type { RefObject } from 'react';
import { useId } from 'react';
import { Button } from '@/components/ui/button';
import {
    bodyCharacterCount,
    maxBodyLength,
    maxRawBodyLength,
} from '@/lib/commit';
import { ambiguousColorInDraft } from '@/lib/hashtags';
import { cn } from '@/lib/utils';

const longestColorHintExample = 'color(#a1234567)';

function fitsCommitBody(body: string): boolean {
    return (
        body.length <= maxRawBodyLength &&
        bodyCharacterCount(body) <= maxBodyLength
    );
}

export function useColorSwatchHint({
    body,
    textareaRef,
    updateBody,
    hidden = false,
    acceptsBody = fitsCommitBody,
}: {
    body: string;
    textareaRef: RefObject<HTMLTextAreaElement | null>;
    updateBody: (body: string) => void;
    hidden?: boolean;
    acceptsBody?: (body: string) => boolean;
}) {
    const id = useId();
    const color = hidden ? undefined : ambiguousColorInDraft(body);

    if (color === undefined) {
        return null;
    }

    const markup = `color(${color.hex})`;
    const replacement =
        body.slice(0, color.index) +
        markup +
        body.slice(color.index + color.hex.length);
    const caret = color.index + markup.length;

    return {
        id: `${id}-color-hint`,
        markup,
        apply: acceptsBody(replacement)
            ? () => {
                  updateBody(replacement);
                  requestAnimationFrame(() => {
                      const textarea = textareaRef.current;
                      textarea?.focus();
                      textarea?.setSelectionRange(caret, caret);
                  });
              }
            : undefined,
    };
}

export function ColorSwatchHint({
    hint,
    disabled,
    className,
}: {
    hint: ReturnType<typeof useColorSwatchHint>;
    disabled?: boolean;
    className?: string;
}) {
    return (
        <div
            className={cn(
                'flex min-h-8 shrink-0 items-center gap-2 text-xs text-muted-foreground',
                { invisible: hint === null },
                className,
            )}
            aria-hidden={hint === null}
        >
            <span id={hint?.id}>
                For a swatch, use{' '}
                <code className="whitespace-nowrap">
                    {hint?.markup ?? longestColorHintExample}
                </code>
                .
            </span>
            <Button
                type="button"
                variant="link"
                size="sm"
                className="shrink-0 px-1 text-xs"
                disabled={disabled || hint?.apply === undefined}
                data-test="use-color"
                onClick={hint?.apply}
            >
                Use as color
            </Button>
        </div>
    );
}
