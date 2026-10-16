import type { EmojiSuggestion } from '@/lib/emoji';
import { cn } from '@/lib/utils';

export function EmojiSuggestionHint({
    suggestion,
    className,
}: {
    suggestion: EmojiSuggestion | null;
    className?: string;
}) {
    if (suggestion === null) {
        return null;
    }

    return (
        <div
            className={cn(
                'flex items-center gap-1.5 text-muted-foreground',
                className,
            )}
            data-test="emoji-suggestion"
            aria-live="polite"
        >
            <span className="text-base leading-none" aria-hidden>
                {suggestion.emoji}
            </span>
            <span className="font-mono text-[13px]">:{suggestion.name}</span>
            <kbd
                aria-hidden
                className="rounded border border-b-2 px-1 font-mono text-[10px] leading-4 opacity-70"
            >
                tab
            </kbd>
        </div>
    );
}
