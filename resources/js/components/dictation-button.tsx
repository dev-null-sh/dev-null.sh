import { Mic } from 'lucide-react';
import type { RefObject } from 'react';
import { useEffect } from 'react';
import { ActionTooltip } from '@/components/action-tooltip';
import { Button } from '@/components/ui/button';
import { useDictation } from '@/hooks/use-dictation';
import { cn } from '@/lib/utils';

/**
 * Writes the commit by voice where the caret is. Hidden in browsers without speech recognition, and drops whatever
 * wasn't written yet once the commit is being sent, so nothing lands in the next one.
 */
export function DictationButton({
    body,
    updateBody,
    textareaRef,
    maxLength,
    disabled,
}: {
    body: string;
    updateBody: (body: string) => void;
    textareaRef: RefObject<HTMLTextAreaElement | null>;
    maxLength: number;
    disabled: boolean;
}) {
    const dictation = useDictation({
        body,
        updateBody,
        textareaRef,
        maxLength,
    });
    const { isListening, stop, abort } = dictation;

    useEffect(() => {
        if (disabled && isListening) {
            abort();
        }
    }, [disabled, isListening, abort]);

    if (!dictation.isSupported) {
        return null;
    }

    return (
        <ActionTooltip label={isListening ? 'Stop dictating' : 'Dictate'}>
            <Button
                type="button"
                variant="ghost"
                size="icon"
                aria-label="Dictate"
                aria-pressed={isListening}
                disabled={disabled}
                onClick={isListening ? stop : dictation.start}
                className="text-muted-foreground aria-pressed:bg-primary-soft aria-pressed:text-primary aria-pressed:hover:bg-primary-soft aria-pressed:hover:text-primary"
                data-test="dictation-button"
            >
                <Mic
                    className={cn('size-5', { 'animate-pulse': isListening })}
                />
            </Button>
        </ActionTooltip>
    );
}
