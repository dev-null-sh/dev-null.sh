import type { ComponentProps, KeyboardEvent, Ref, SyntheticEvent } from 'react';
import { useEffect, useId, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import MentionSuggestionController from '@/actions/App/Http/Controllers/MentionSuggestionController';
import { Spinner } from '@/components/ui/spinner';
import { UserAvatar } from '@/components/user-avatar';
import { typedProse } from '@/lib/code';
import type { EmojiSuggestion } from '@/lib/emoji';
import { emojifyInput, searchEmoji } from '@/lib/emoji';
import { suggestionPosition } from '@/lib/suggestion-position';
import { toastWhenThrottled } from '@/lib/throttle';
import { characterCount, cn, laneColor, limitCharacters } from '@/lib/utils';
import type { User } from '@/types';

type Trigger = {
    kind: 'mention' | 'emoji';
    start: number;
    query: string;
};

type Suggestions = {
    key: string;
    users: User[];
};

type Position = ReturnType<typeof suggestionPosition>;

const debounce = 120;
const cache = new Map<string, User[]>();

export function clearMentionSuggestionsCache(): void {
    cache.clear();
}

// The "@handle" being typed right before the caret. Keep the rules in sync with app/Services/Mentions.php.
const mentionBeforeCaretPattern =
    /(?:^|[^\p{L}\p{N}_./@-])@([A-Za-z0-9][A-Za-z0-9-]{0,38}|)$/u;

// The ":shortcode" being typed in prose right before the caret, from two characters on so times like 10:30 stay quiet.
const emojiBeforeCaretPattern =
    /(?:^|[^\p{L}\p{N}_:])(:([A-Za-z0-9_+-]{2,}))$/u;

// Every style that changes where text wraps, so the mirror below wraps exactly like the textarea.
const mirroredProperties = [
    'box-sizing',
    'width',
    'border-top-width',
    'border-right-width',
    'border-bottom-width',
    'border-left-width',
    'padding-top',
    'padding-right',
    'padding-bottom',
    'padding-left',
    'font-family',
    'font-size',
    'font-weight',
    'font-style',
    'font-stretch',
    'font-feature-settings',
    'font-variant',
    'line-height',
    'letter-spacing',
    'word-spacing',
    'text-transform',
    'text-indent',
    'tab-size',
];

function triggerAt(
    textarea: HTMLTextAreaElement,
    suggestsMentions: boolean,
): Trigger | null {
    if (textarea.selectionStart !== textarea.selectionEnd) {
        return null;
    }

    const caret = textarea.selectionStart;
    const before = textarea.value.slice(0, caret);
    const mention = suggestsMentions
        ? before.match(mentionBeforeCaretPattern)
        : null;

    if (mention) {
        return {
            kind: 'mention',
            start: caret - mention[1].length - 1,
            query: mention[1],
        };
    }

    const emoji = (typedProse(before).at(-1) ?? '').match(
        emojiBeforeCaretPattern,
    );

    return emoji
        ? { kind: 'emoji', start: caret - emoji[1].length, query: emoji[2] }
        : null;
}

/**
 * Where a character of the textarea is drawn, measured on a hidden copy of it that wraps the same way.
 */
function characterRect(textarea: HTMLTextAreaElement, index: number) {
    const style = window.getComputedStyle(textarea);
    const mirror = document.createElement('div');
    const marker = document.createElement('span');

    mirroredProperties.forEach((property) =>
        mirror.style.setProperty(property, style.getPropertyValue(property)),
    );
    mirror.style.position = 'absolute';
    mirror.style.visibility = 'hidden';
    mirror.style.top = '0';
    mirror.style.left = '-9999px';
    mirror.style.whiteSpace = 'pre-wrap';
    mirror.style.overflowWrap = 'break-word';

    mirror.textContent = textarea.value.slice(0, index);
    marker.textContent = textarea.value.slice(index) || '.';
    mirror.appendChild(marker);
    document.body.appendChild(mirror);

    const rect = textarea.getBoundingClientRect();
    const lineHeight =
        Number.parseFloat(style.lineHeight) ||
        Number.parseFloat(style.fontSize) * 1.4;
    const top = rect.top + marker.offsetTop - textarea.scrollTop;
    const left = rect.left + marker.offsetLeft - textarea.scrollLeft;

    mirror.remove();

    return { top, left, bottom: top + lineHeight };
}

function positionFor(textarea: HTMLTextAreaElement, index: number): Position {
    const character = characterRect(textarea, index);
    const viewport = window.visualViewport;

    return suggestionPosition(
        character,
        {
            left: viewport?.offsetLeft ?? 0,
            top: viewport?.offsetTop ?? 0,
            width: viewport?.width ?? window.innerWidth,
            height: viewport?.height ?? window.innerHeight,
        },
        window.innerHeight,
    );
}

async function fetchSuggestions(
    query: string,
    org: string | undefined,
    reply: string | undefined,
    quote: string | undefined,
    signal: AbortSignal,
): Promise<User[]> {
    const response = await fetch(
        MentionSuggestionController.index({
            query: { q: query, org, reply, quote },
        }).url,
        {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            signal,
        },
    );

    if (!response.ok) {
        toastWhenThrottled(response);

        throw new Error('Unable to load suggestions');
    }

    const data = (await response.json()) as { users: User[] };
    // Replies need fresh participants when someone joins the thread.
    if (!reply) {
        cache.set(
            `${org ?? ''}:${reply ?? ''}:${quote ?? ''}:${query.toLowerCase()}`,
            data.users,
        );
    }

    return data.users;
}

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

function Highlighted({ text, query }: { text: string; query: string }) {
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
 * A textarea that suggests developers to mention as soon as "@" is typed, right under the "@", unless it doesn't
 * suggest mentions, suggests emoji for the ":shortcode" being typed, and turns emoji shortcodes into emoji as they
 * are typed. Within an org, it only suggests the org's members.
 */
export function MentionTextarea({
    ref,
    value,
    onValueChange,
    onKeyDown,
    onSelect,
    onBlur,
    maxLength,
    suggestsMentions = true,
    org,
    reply,
    quote,
    ...props
}: Omit<ComponentProps<'textarea'>, 'value' | 'onChange'> & {
    ref?: Ref<HTMLTextAreaElement>;
    value: string;
    onValueChange: (value: string) => void;
    suggestsMentions?: boolean;
    org?: string;
    reply?: string;
    quote?: string;
}) {
    const id = useId();
    const textareaRef = useRef<HTMLTextAreaElement>(null);
    const listRef = useRef<HTMLUListElement>(null);
    const [trigger, setTrigger] = useState<Trigger | null>(null);
    const [position, setPosition] = useState<Position | null>(null);
    const [results, setResults] = useState<Suggestions | null>(null);
    const [activeIndex, setActiveIndex] = useState(0);

    const query =
        trigger?.kind === 'mention' ? trigger.query.toLowerCase() : null;
    const key =
        query === null
            ? null
            : `${org ?? ''}:${reply ?? ''}:${quote ?? ''}:${query}`;

    const setRefs = (element: HTMLTextAreaElement | null) => {
        textareaRef.current = element;

        if (typeof ref === 'function') {
            ref(element);
        } else if (ref) {
            ref.current = element;
        }
    };

    const close = () => {
        setTrigger(null);
        setPosition(null);
    };

    const track = (textarea: HTMLTextAreaElement) => {
        const next = triggerAt(textarea, suggestsMentions);

        if (!next) {
            close();

            return;
        }

        if (
            next.kind !== trigger?.kind ||
            next.start !== trigger.start ||
            next.query !== trigger.query
        ) {
            setActiveIndex(0);
        }

        setTrigger(next);
        setPosition(positionFor(textarea, next.start));
    };

    useEffect(() => {
        if (query === null || key === null || cache.has(key)) {
            return;
        }

        const controller = new AbortController();
        const timeout = setTimeout(
            () =>
                fetchSuggestions(query, org, reply, quote, controller.signal)
                    .then((users) => setResults({ key, users }))
                    .catch(() => undefined),
            query === '' ? 0 : debounce,
        );

        return () => {
            clearTimeout(timeout);
            controller.abort();
        };
    }, [key, query, org, reply, quote]);

    useEffect(() => {
        if (!trigger) {
            return;
        }

        const reposition = () => {
            if (textareaRef.current) {
                setPosition(positionFor(textareaRef.current, trigger.start));
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

    const cached = key === null ? undefined : cache.get(key);
    const current = key !== null && cached ? { key, users: cached } : results;
    const suggestions =
        key !== null && current?.key === key ? current.users : [];
    const emojiQuery =
        trigger?.kind === 'emoji' ? trigger.query.toLowerCase() : '';
    const emojis = emojiQuery === '' ? [] : searchEmoji(emojiQuery);
    const optionCount =
        trigger?.kind === 'emoji' ? emojis.length : suggestions.length;
    const isOpen =
        trigger !== null &&
        position !== null &&
        (trigger.kind === 'emoji'
            ? emojis.length > 0
            : suggestions.length > 0 || query !== '');

    const insert = (text: string, separator: string) => {
        const textarea = textareaRef.current;

        if (!textarea || !trigger) {
            return;
        }

        const caret = trigger.start + 1 + trigger.query.length;
        const after = value.slice(caret);
        const spacing = /^\s/u.test(after) ? '' : separator;
        let next = `${value.slice(0, trigger.start)}${text}${spacing}${after}`;

        if (maxLength !== undefined && characterCount(next) > maxLength) {
            next = `${value.slice(0, trigger.start)}${text}${after}`;
        }

        if (maxLength !== undefined && characterCount(next) > maxLength) {
            close();

            return;
        }

        const nextCaret = trigger.start + text.length + separator.length;

        onValueChange(next);
        close();

        requestAnimationFrame(() => {
            textarea.focus();
            textarea.setSelectionRange(
                Math.min(nextCaret, next.length),
                Math.min(nextCaret, next.length),
            );
        });
    };

    const select = (user: User) => insert(`@${user.username}`, ' ');

    const selectEmoji = (emoji: EmojiSuggestion) => insert(emoji.emoji, '');

    const handleKeyDown = (event: KeyboardEvent<HTMLTextAreaElement>) => {
        if (isOpen && !event.nativeEvent.isComposing) {
            const hasModifier = event.metaKey || event.ctrlKey || event.altKey;

            if (
                (event.key === 'ArrowDown' || event.key === 'ArrowUp') &&
                optionCount > 0
            ) {
                event.preventDefault();
                const step = event.key === 'ArrowDown' ? 1 : -1;
                setActiveIndex(
                    (index) => (index + step + optionCount) % optionCount,
                );

                return;
            }

            if (
                (event.key === 'Enter' || event.key === 'Tab') &&
                !hasModifier &&
                !event.shiftKey &&
                activeIndex < optionCount
            ) {
                event.preventDefault();

                if (trigger?.kind === 'emoji') {
                    selectEmoji(emojis[activeIndex]);
                } else {
                    select(suggestions[activeIndex]);
                }

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

    const activeId = !isOpen
        ? undefined
        : trigger?.kind === 'emoji'
          ? emojis[activeIndex] && `${id}-emoji-${emojis[activeIndex].name}`
          : suggestions[activeIndex] &&
            `${id}-mention-${suggestions[activeIndex].id}`;

    const isSearching = current?.key !== key;
    const listId =
        trigger?.kind === 'emoji' ? `${id}-emojis` : `${id}-mentions`;

    return (
        <>
            <textarea
                {...props}
                ref={setRefs}
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
                onSelect={(event: SyntheticEvent<HTMLTextAreaElement>) => {
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
                aria-activedescendant={activeId}
            />

            {isOpen &&
                createPortal(
                    <div
                        className="pointer-events-auto fixed z-50 flex flex-col overflow-hidden rounded-xl border bg-popover text-popover-foreground shadow-[0_12px_32px_-12px_rgb(0_0_0/0.35)] motion-safe:animate-in motion-safe:fade-in-0 motion-safe:zoom-in-95"
                        style={position}
                        onMouseDown={(event) => event.preventDefault()}
                        data-mention-suggestions
                        data-test="mention-suggestions"
                    >
                        {trigger?.kind === 'emoji' ? (
                            <ul
                                ref={listRef}
                                id={listId}
                                role="listbox"
                                aria-label="Insert an emoji"
                                className="min-h-0 overflow-y-auto overscroll-contain p-1.5"
                            >
                                {emojis.map((emoji, index) => (
                                    <li
                                        key={emoji.name}
                                        id={`${id}-emoji-${emoji.name}`}
                                        role="option"
                                        aria-selected={index === activeIndex}
                                        onMouseMove={() =>
                                            setActiveIndex(index)
                                        }
                                        onClick={() => selectEmoji(emoji)}
                                        className={cn(
                                            'flex cursor-pointer items-center gap-2.5 rounded-lg px-2.5 py-1.5',
                                            {
                                                'bg-accent':
                                                    index === activeIndex,
                                            },
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
                                            :
                                            <Highlighted
                                                text={emoji.name}
                                                query={emojiQuery}
                                            />
                                            :
                                        </span>
                                        <kbd
                                            aria-hidden
                                            className={cn(
                                                'hidden shrink-0 font-mono text-xs text-muted-foreground opacity-0 sm:block',
                                                {
                                                    'opacity-100':
                                                        index === activeIndex,
                                                },
                                            )}
                                        >
                                            ↵
                                        </kbd>
                                    </li>
                                ))}
                            </ul>
                        ) : suggestions.length > 0 ? (
                            <ul
                                ref={listRef}
                                id={listId}
                                role="listbox"
                                aria-label="Mention a developer"
                                className="min-h-0 overflow-y-auto overscroll-contain p-1.5"
                            >
                                {query === '' &&
                                    !reply &&
                                    !quote &&
                                    current?.key === key && (
                                        <li
                                            role="presentation"
                                            className="px-2.5 pt-1 pb-1.5 text-xs text-muted-foreground"
                                        >
                                            {org
                                                ? 'Members'
                                                : 'People you follow'}
                                        </li>
                                    )}
                                {suggestions.map((user, index) => (
                                    <li
                                        key={user.id}
                                        id={`${id}-mention-${user.id}`}
                                        role="option"
                                        aria-selected={index === activeIndex}
                                        onMouseMove={() =>
                                            setActiveIndex(index)
                                        }
                                        onClick={() => select(user)}
                                        className={cn(
                                            'flex cursor-pointer items-center gap-2.5 rounded-lg px-2.5 py-2',
                                            {
                                                'bg-accent':
                                                    index === activeIndex,
                                            },
                                        )}
                                        data-test="mention-suggestion"
                                    >
                                        <UserAvatar
                                            user={user}
                                            className="size-8 shrink-0"
                                        />
                                        <span className="min-w-0 flex-1">
                                            <span className="block truncate text-sm font-semibold">
                                                <Highlighted
                                                    text={user.name}
                                                    query={query ?? ''}
                                                />
                                            </span>
                                            <span className="flex items-center gap-1 font-mono text-xs text-muted-foreground">
                                                <span
                                                    aria-hidden
                                                    className="size-1.5 shrink-0 rounded-full"
                                                    style={{
                                                        background: laneColor(
                                                            user.lane,
                                                        ),
                                                    }}
                                                />
                                                <span className="truncate">
                                                    @
                                                    <Highlighted
                                                        text={user.username}
                                                        query={query ?? ''}
                                                    />
                                                </span>
                                            </span>
                                        </span>
                                        <kbd
                                            aria-hidden
                                            className={cn(
                                                'hidden shrink-0 font-mono text-xs text-muted-foreground opacity-0 sm:block',
                                                {
                                                    'opacity-100':
                                                        index === activeIndex,
                                                },
                                            )}
                                        >
                                            ↵
                                        </kbd>
                                    </li>
                                ))}
                            </ul>
                        ) : (
                            <div
                                role="status"
                                className="px-4 py-3 text-sm text-muted-foreground"
                            >
                                {isSearching ? (
                                    <span className="flex items-center gap-2">
                                        <Spinner className="size-3.5" />
                                        Searching…
                                    </span>
                                ) : (
                                    <span className="font-mono text-[13px]">
                                        fatal: no developers match '
                                        {trigger?.query}'
                                    </span>
                                )}
                            </div>
                        )}
                    </div>,
                    document.body,
                )}
        </>
    );
}
