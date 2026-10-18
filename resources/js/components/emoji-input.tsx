import type { ComponentProps, KeyboardEvent, SyntheticEvent } from 'react';
import { useEffect, useId, useRef, useState } from 'react';
import {
    EmojiSuggestionList,
    SuggestionPopover,
} from '@/components/emoji-suggestions';
import type { EmojiSuggestion } from '@/lib/emoji';
import { emojifyInput, emojiQueryBefore, searchEmoji } from '@/lib/emoji';
import { suggestionPosition } from '@/lib/suggestion-position';
import { characterCount, limitCharacters } from '@/lib/utils';

type Trigger = {
    start: number;
    query: string;
};

type Position = ReturnType<typeof suggestionPosition>;

// Every style that changes how wide the text is, so the mirror below measures it like the input.
const mirroredProperties = [
    'font-family',
    'font-size',
    'font-weight',
    'font-style',
    'font-stretch',
    'font-feature-settings',
    'font-variant',
    'letter-spacing',
    'word-spacing',
    'text-transform',
];

/**
 * Under the input, from where a character of it is drawn, measured on a hidden copy of the text before it.
 */
function positionFor(input: HTMLInputElement, index: number): Position {
    const style = window.getComputedStyle(input);
    const mirror = document.createElement('span');

    mirroredProperties.forEach((property) =>
        mirror.style.setProperty(property, style.getPropertyValue(property)),
    );
    mirror.style.position = 'absolute';
    mirror.style.visibility = 'hidden';
    mirror.style.top = '0';
    mirror.style.left = '-9999px';
    mirror.style.whiteSpace = 'pre';
    mirror.textContent = input.value.slice(0, index);
    document.body.appendChild(mirror);

    const rect = input.getBoundingClientRect();
    const left =
        rect.left +
        Number.parseFloat(style.borderLeftWidth) +
        Number.parseFloat(style.paddingLeft) +
        mirror.offsetWidth -
        input.scrollLeft;

    mirror.remove();

    const viewport = window.visualViewport;

    return suggestionPosition(
        { left, top: rect.top, bottom: rect.bottom },
        {
            left: viewport?.offsetLeft ?? 0,
            top: viewport?.offsetTop ?? 0,
            width: viewport?.width ?? window.innerWidth,
            height: viewport?.height ?? window.innerHeight,
        },
        window.innerHeight,
    );
}

/**
 * A single-line input that suggests emoji for the ":shortcode" being typed, and turns emoji shortcodes into emoji as
 * they are typed, like the body of a commit.
 */
export function EmojiInput({
    value,
    onValueChange,
    onKeyDown,
    onSelect,
    onBlur,
    maxLength,
    ...props
}: Omit<ComponentProps<'input'>, 'value' | 'onChange'> & {
    value: string;
    onValueChange: (value: string) => void;
}) {
    const id = useId();
    const inputRef = useRef<HTMLInputElement>(null);
    const listRef = useRef<HTMLUListElement>(null);
    const [trigger, setTrigger] = useState<Trigger | null>(null);
    const [position, setPosition] = useState<Position | null>(null);
    const [activeIndex, setActiveIndex] = useState(0);

    const close = () => {
        setTrigger(null);
        setPosition(null);
    };

    const track = (input: HTMLInputElement) => {
        const caret = input.selectionStart;
        const next =
            caret === null || caret !== input.selectionEnd
                ? null
                : emojiQueryBefore(input.value.slice(0, caret));

        if (!next) {
            close();

            return;
        }

        if (next.start !== trigger?.start || next.query !== trigger.query) {
            setActiveIndex(0);
        }

        setTrigger(next);
        setPosition(positionFor(input, next.start));
    };

    useEffect(() => {
        if (!trigger) {
            return;
        }

        const reposition = () => {
            if (inputRef.current) {
                setPosition(positionFor(inputRef.current, trigger.start));
            }
        };

        window.addEventListener('scroll', reposition, {
            capture: true,
            passive: true,
        });
        window.addEventListener('resize', reposition);
        const viewport = window.visualViewport;
        viewport?.addEventListener('resize', reposition);
        viewport?.addEventListener('scroll', reposition);

        return () => {
            window.removeEventListener('scroll', reposition, {
                capture: true,
            });
            window.removeEventListener('resize', reposition);
            viewport?.removeEventListener('resize', reposition);
            viewport?.removeEventListener('scroll', reposition);
        };
    }, [trigger]);

    useEffect(() => {
        listRef.current
            ?.querySelector('[aria-selected="true"]')
            ?.scrollIntoView({ block: 'nearest' });
    }, [activeIndex]);

    const emojiQuery = trigger?.query.toLowerCase() ?? '';
    const emojis = emojiQuery === '' ? [] : searchEmoji(emojiQuery);
    const isOpen = trigger !== null && position !== null && emojis.length > 0;
    const listId = `${id}-emojis`;

    const selectEmoji = (emoji: EmojiSuggestion) => {
        const input = inputRef.current;

        if (!input || !trigger) {
            return;
        }

        const next = `${value.slice(0, trigger.start)}${emoji.emoji}${value.slice(trigger.start + 1 + trigger.query.length)}`;

        close();

        if (maxLength !== undefined && characterCount(next) > maxLength) {
            return;
        }

        const caret = trigger.start + emoji.emoji.length;

        onValueChange(next);

        requestAnimationFrame(() => {
            input.focus();
            input.setSelectionRange(caret, caret);
        });
    };

    const handleKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
        if (isOpen && !event.nativeEvent.isComposing) {
            const hasModifier = event.metaKey || event.ctrlKey || event.altKey;

            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                const step = event.key === 'ArrowDown' ? 1 : -1;
                setActiveIndex(
                    (index) => (index + step + emojis.length) % emojis.length,
                );

                return;
            }

            if (
                (event.key === 'Enter' || event.key === 'Tab') &&
                !hasModifier &&
                !event.shiftKey &&
                activeIndex < emojis.length
            ) {
                event.preventDefault();
                selectEmoji(emojis[activeIndex]);

                return;
            }

            if (event.key === 'Escape') {
                event.preventDefault();
                event.stopPropagation();
                close();

                return;
            }
        }

        onKeyDown?.(event);
    };

    return (
        <>
            <input
                {...props}
                ref={inputRef}
                value={value}
                onChange={(event) => {
                    const target = event.currentTarget;
                    const { text, caret } = emojifyInput(
                        target.value,
                        target.selectionStart ?? target.value.length,
                    );

                    const limited =
                        maxLength === undefined
                            ? text
                            : limitCharacters(text, maxLength);

                    onValueChange(limited);

                    if (limited === target.value) {
                        track(target);

                        return;
                    }

                    close();
                    requestAnimationFrame(() => {
                        const position = Math.min(caret, limited.length);
                        target.setSelectionRange(position, position);
                    });
                }}
                onSelect={(event: SyntheticEvent<HTMLInputElement>) => {
                    track(event.currentTarget);
                    onSelect?.(event);
                }}
                onBlur={(event) => {
                    close();
                    onBlur?.(event);
                }}
                onKeyDown={handleKeyDown}
                aria-autocomplete="list"
                aria-controls={isOpen ? listId : undefined}
                aria-activedescendant={
                    isOpen && emojis[activeIndex]
                        ? `${id}-emoji-${emojis[activeIndex].name}`
                        : undefined
                }
            />

            {isOpen && (
                <SuggestionPopover position={position}>
                    <EmojiSuggestionList
                        ref={listRef}
                        id={listId}
                        optionId={(emoji) => `${id}-emoji-${emoji.name}`}
                        emojis={emojis}
                        query={emojiQuery}
                        activeIndex={activeIndex}
                        onActivate={setActiveIndex}
                        onSelect={selectEmoji}
                    />
                </SuggestionPopover>
            )}
        </>
    );
}
