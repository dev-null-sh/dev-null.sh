import type { ReactNode, Ref } from 'react';
import { createPortal } from 'react-dom';
import type { EmojiSuggestion } from '@/lib/emoji';
import type { suggestionPosition } from '@/lib/suggestion-position';
import { cn } from '@/lib/utils';

/**
 * Where the query matches the text, preferring the start of a word, so ":art" marks "arts" in "martial_arts".
 */
function matchIndex(text: string, query: string): number {
    const lower = text.toLowerCase();
    const first = query === '' ? -1 : lower.indexOf(query);

    for (let at = first; at !== -1; at = lower.indexOf(query, at + 1)) {
        if (at === 0 || /[^\p{L}\p{N}]/u.test(lower[at - 1])) {
            return at;
        }
    }

    return first;
}

export function Highlighted({ text, query }: { text: string; query: string }) {
    const index = matchIndex(text, query);

    if (index === -1) {
        return text;
    }

    return (
        <>
            {text.slice(0, index)}
            <span className="text-foreground">
                {text.slice(index, index + query.length)}
            </span>
            {text.slice(index + query.length)}
        </>
    );
}

/**
 * The floating menu of suggestions, kept open while it's clicked, which the composer dialog looks for so Escape and
 * outside clicks close the menu before the dialog.
 */
export function SuggestionPopover({
    position,
    children,
}: {
    position: ReturnType<typeof suggestionPosition>;
    children: ReactNode;
}) {
    return createPortal(
        <div
            className="pointer-events-auto fixed z-50 flex flex-col overflow-hidden rounded-xl border bg-popover text-popover-foreground shadow-[0_12px_32px_-12px_rgb(0_0_0/0.35)] motion-safe:animate-in motion-safe:fade-in-0 motion-safe:zoom-in-95"
            style={position}
            onMouseDown={(event) => event.preventDefault()}
            data-mention-suggestions
            data-test="mention-suggestions"
        >
            {children}
        </div>,
        document.body,
    );
}

export function EmojiSuggestionList({
    ref,
    id,
    optionId,
    emojis,
    query,
    activeIndex,
    onActivate,
    onSelect,
}: {
    ref?: Ref<HTMLUListElement>;
    id: string;
    optionId: (emoji: EmojiSuggestion) => string;
    emojis: EmojiSuggestion[];
    query: string;
    activeIndex: number;
    onActivate: (index: number) => void;
    onSelect: (emoji: EmojiSuggestion) => void;
}) {
    return (
        <ul
            ref={ref}
            id={id}
            role="listbox"
            aria-label="Insert an emoji"
            className="min-h-0 overflow-y-auto overscroll-contain p-1.5"
        >
            {emojis.map((emoji, index) => (
                <li
                    key={emoji.name}
                    id={optionId(emoji)}
                    role="option"
                    aria-selected={index === activeIndex}
                    onMouseMove={() => onActivate(index)}
                    onClick={() => onSelect(emoji)}
                    className={cn(
                        'flex cursor-pointer items-center gap-2.5 rounded-lg px-2.5 py-1.5',
                        { 'bg-accent': index === activeIndex },
                    )}
                    data-test="emoji-suggestion"
                >
                    <span
                        aria-hidden
                        className="w-6 shrink-0 text-center text-lg leading-none"
                    >
                        {emoji.emoji}
                    </span>
                    <span className="min-w-0 flex-1 truncate font-mono text-[13px] text-muted-foreground">
                        :<Highlighted text={emoji.name} query={query} />:
                    </span>
                    <kbd
                        aria-hidden
                        className={cn(
                            'hidden shrink-0 font-mono text-xs text-muted-foreground opacity-0 sm:block',
                            { 'opacity-100': index === activeIndex },
                        )}
                    >
                        ↵
                    </kbd>
                </li>
            ))}
        </ul>
    );
}
