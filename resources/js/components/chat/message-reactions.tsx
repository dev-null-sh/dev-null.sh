import * as DialogPrimitive from '@radix-ui/react-dialog';
import { Copy, Search, SmilePlus } from 'lucide-react';
import type { KeyboardEvent, PointerEvent, ReactNode } from 'react';
import { useEffect, useId, useMemo, useRef, useState } from 'react';
import { toast } from 'sonner';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { Skeleton } from '@/components/ui/skeleton';
import { emojiName, searchEmojiByUse } from '@/lib/emoji';
import type { ReactionUsage, Tap } from '@/lib/reactions';
import {
    frequentReactions,
    isDoubleTap,
    quickReactions,
    summarizeReactions,
} from '@/lib/reactions';
import { cn } from '@/lib/utils';
import type { MessageReaction } from '@/types';

type EmojiGroup = { name: string; emoji: string[] };

const columns = 8;
const longPressDelay = 420;

let loadedGroups: EmojiGroup[] | null = null;
let loadingGroups: Promise<EmojiGroup[]> | null = null;

/**
 * The full emoji set, grouped as on a keyboard, loads on its own the first time someone is about to react.
 */
export function preloadEmojiGroups(): Promise<EmojiGroup[]> {
    loadingGroups ??= import('@/lib/emoji-groups.json').then((module) => {
        loadedGroups = module.default as EmojiGroup[];

        return loadedGroups;
    });

    return loadingGroups;
}

function useEmojiGroups(): EmojiGroup[] | null {
    const [groups, setGroups] = useState(loadedGroups);

    useEffect(() => {
        if (groups !== null) {
            return;
        }

        let isCurrent = true;

        void preloadEmojiGroups().then((loaded) => {
            if (isCurrent) {
                setGroups(loaded);
            }
        });

        return () => {
            isCurrent = false;
        };
    }, [groups]);

    return groups;
}

/**
 * Shows the skeleton only once loading takes long enough to notice.
 */
function useIsSlow(isLoading: boolean): boolean {
    const [isSlow, setIsSlow] = useState(false);

    useEffect(() => {
        if (!isLoading) {
            return;
        }

        const timer = window.setTimeout(() => setIsSlow(true), 300);

        return () => {
            window.clearTimeout(timer);
            setIsSlow(false);
        };
    }, [isLoading]);

    return isSlow;
}

function shortcodeOf(emoji: string): string {
    const name = emojiName(emoji);

    return name === undefined ? emoji : `:${name}:`;
}

/**
 * One chip per emoji under the message, pressed when the user left it. Pressing one adds or takes back theirs.
 */
export function ReactionChips({
    reactions,
    userId,
    isMine,
    nameOf,
    onToggle,
}: {
    reactions: MessageReaction[] | undefined;
    userId: string;
    isMine: boolean;
    nameOf: (userId: string) => string;
    onToggle: ((emoji: string) => void) | null;
}) {
    const summaries = summarizeReactions(reactions, userId);

    if (summaries.length === 0) {
        return null;
    }

    return (
        <ul
            aria-label="Reactions"
            className={cn('flex max-w-full flex-wrap gap-1', {
                'justify-end': isMine,
            })}
            data-test="message-reactions"
        >
            {summaries.map((summary) => {
                const names = summary.userIds.map(nameOf).join(', ');

                return (
                    <li key={summary.emoji}>
                        <button
                            type="button"
                            disabled={onToggle === null}
                            aria-pressed={summary.isMine}
                            aria-label={`${shortcodeOf(summary.emoji)} ${summary.count}, from ${names}`}
                            title={`${names} reacted with ${shortcodeOf(summary.emoji)}`}
                            onClick={() => onToggle?.(summary.emoji)}
                            className={cn(
                                'inline-flex h-6 items-center gap-1 rounded-full border bg-card pr-2 pl-1.5 text-[13px] leading-none transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none enabled:hover:border-foreground/25',
                                {
                                    'border-primary/45 bg-primary-soft enabled:hover:border-primary':
                                        summary.isMine,
                                },
                            )}
                            data-test="reaction-chip"
                        >
                            <span aria-hidden>{summary.emoji}</span>
                            <span
                                aria-hidden
                                className={cn(
                                    'min-w-[1ch] font-mono text-[11px] font-medium text-muted-foreground tabular-nums',
                                    { 'text-primary': summary.isMine },
                                )}
                            >
                                {summary.count}
                            </span>
                        </button>
                    </li>
                );
            })}
        </ul>
    );
}

/**
 * The quick reactions and the picker, floating over the top corner of the message on hover. Clamped to the message's
 * column, so it never pokes out of the thread on short bubbles.
 */
export function ReactionBar({
    usage,
    reacted,
    isMine,
    onReact,
}: {
    usage: ReactionUsage[];
    reacted: Set<string>;
    isMine: boolean;
    onReact: (emoji: string) => void;
}) {
    const [isOpen, setIsOpen] = useState(false);
    const isPicked = useRef(false);

    return (
        <div
            role="toolbar"
            aria-label="React"
            data-open={isOpen ? '' : undefined}
            onPointerEnter={() => void preloadEmojiGroups()}
            className={cn(
                'pointer-events-none absolute bottom-[calc(100%-8px)] z-10 flex items-center gap-0.5 rounded-full border bg-popover p-[3px] opacity-0 shadow-[0_8px_24px_-10px_rgb(0_0_0/0.45)] transition-opacity group-hover/message:pointer-events-auto group-hover/message:opacity-100 has-focus-visible:pointer-events-auto has-focus-visible:opacity-100 data-open:pointer-events-auto data-open:opacity-100 pointer-coarse:hidden',
                isMine
                    ? 'right-[max(0px,calc(100%-7.5rem))]'
                    : 'left-[max(0px,calc(100%-7.5rem))]',
            )}
            data-test="reaction-bar"
        >
            {quickReactions(usage).map((emoji) => (
                <button
                    key={emoji}
                    type="button"
                    aria-label={`React with ${shortcodeOf(emoji)}`}
                    aria-pressed={reacted.has(emoji)}
                    title={shortcodeOf(emoji)}
                    onClick={() => onReact(emoji)}
                    className={cn(
                        'grid size-7 place-items-center rounded-full text-base leading-none transition-[background-color,scale] hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none active:scale-90',
                        { 'bg-primary-soft': reacted.has(emoji) },
                    )}
                    data-test="quick-reaction"
                >
                    {emoji}
                </button>
            ))}
            <span aria-hidden className="mx-0.5 h-4 w-px bg-border" />
            <Popover open={isOpen} onOpenChange={setIsOpen}>
                <PopoverTrigger asChild>
                    <button
                        type="button"
                        aria-label="More reactions"
                        title="More reactions"
                        className="grid size-7 place-items-center rounded-full text-muted-foreground transition-colors hover:bg-accent hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none data-[state=open]:bg-accent data-[state=open]:text-foreground"
                        data-test="more-reactions"
                    >
                        <SmilePlus aria-hidden className="size-4" />
                    </button>
                </PopoverTrigger>
                <PopoverContent
                    align={isMine ? 'end' : 'start'}
                    collisionPadding={8}
                    className="flex h-[min(25rem,var(--radix-popover-content-available-height))] w-76 flex-col overflow-hidden rounded-xl p-0 shadow-[0_12px_32px_-12px_rgb(0_0_0/0.35)]"
                    onCloseAutoFocus={(event) => {
                        if (isPicked.current) {
                            event.preventDefault();
                            isPicked.current = false;
                        }
                    }}
                >
                    <EmojiPicker
                        usage={usage}
                        reacted={reacted}
                        onPick={(emoji) => {
                            isPicked.current = true;
                            setIsOpen(false);
                            onReact(emoji);
                        }}
                    />
                </PopoverContent>
            </Popover>
        </div>
    );
}

/**
 * Searchable grid of every emoji: the user's most used first, then each group. Arrow keys move through it from the
 * search field and Enter reacts, as the composer's suggestions do.
 */
export function EmojiPicker({
    usage,
    reacted,
    onPick,
    isSheet = false,
}: {
    usage: ReactionUsage[];
    reacted: Set<string>;
    onPick: (emoji: string) => void;
    isSheet?: boolean;
}) {
    const id = useId();
    const groups = useEmojiGroups();
    const isSlow = useIsSlow(groups === null);
    const [query, setQuery] = useState('');
    const [activeIndex, setActiveIndex] = useState(0);
    const body = useRef<HTMLDivElement>(null);
    const uses = useMemo(
        () => new Map(usage.map(({ emoji, count }) => [emoji, count])),
        [usage],
    );
    const isSearching = query.trim().replace(/:/g, '') !== '';

    const sections = useMemo<EmojiGroup[]>(() => {
        if (isSearching) {
            return [
                {
                    name: 'Results',
                    emoji: searchEmojiByUse(query, uses, 200).map(
                        (result) => result.emoji,
                    ),
                },
            ];
        }

        const frequent = frequentReactions(usage, columns);

        return [
            ...(frequent.length > 0
                ? [{ name: 'Frequently used', emoji: frequent }]
                : []),
            ...(groups ?? []),
        ];
    }, [isSearching, query, uses, usage, groups]);

    const all = useMemo(
        () => sections.flatMap((section) => section.emoji),
        [sections],
    );
    const active = all[Math.min(activeIndex, all.length - 1)] as
        | string
        | undefined;
    const cellId = (index: number) => `${id}-emoji-${index}`;

    const move = (to: number) => {
        const next = Math.max(0, Math.min(all.length - 1, to));

        setActiveIndex(next);
        document
            .getElementById(cellId(next))
            ?.scrollIntoView({ block: 'nearest' });
    };

    const onKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
        const moves: Record<string, number> = {
            ArrowRight: 1,
            ArrowLeft: -1,
            ArrowDown: columns,
            ArrowUp: -columns,
        };

        if (event.key in moves && all.length > 0) {
            event.preventDefault();
            move(activeIndex + moves[event.key]);
        } else if (event.key === 'Enter' && active !== undefined) {
            event.preventDefault();
            onPick(active);
        }
    };

    let offset = 0;

    return (
        <div className="flex min-h-0 flex-1 flex-col" data-test="emoji-picker">
            <label
                className={cn(
                    'mx-2 mt-2 mb-1 flex h-9 shrink-0 items-center gap-2 rounded-lg border bg-background px-2.5 text-muted-foreground focus-within:border-ring',
                    { 'mx-3': isSheet },
                )}
            >
                <Search aria-hidden className="size-4 shrink-0" />
                <input
                    type="search"
                    autoFocus={!isSheet}
                    value={query}
                    onChange={(event) => {
                        setQuery(event.target.value);
                        setActiveIndex(0);
                        body.current?.scrollTo({ top: 0 });
                    }}
                    onKeyDown={onKeyDown}
                    placeholder="Search emoji"
                    aria-label="Search emoji"
                    aria-controls={`${id}-emoji`}
                    aria-activedescendant={
                        active === undefined
                            ? undefined
                            : cellId(Math.min(activeIndex, all.length - 1))
                    }
                    autoComplete="off"
                    spellCheck={false}
                    className="min-w-0 flex-1 bg-transparent text-base text-foreground outline-none placeholder:text-muted-foreground md:text-sm [&::-webkit-search-cancel-button]:hidden"
                    data-test="emoji-search"
                />
            </label>

            <div
                ref={body}
                id={`${id}-emoji`}
                role="listbox"
                aria-label="Emoji"
                className="min-h-0 flex-1 [scrollbar-width:thin] overflow-y-auto overscroll-contain px-1.5 pb-1.5"
            >
                {isSearching && all.length === 0 && (
                    <p className="px-2 py-8 text-center font-mono text-[13px] text-muted-foreground">
                        no emoji for “{query.trim()}”
                    </p>
                )}
                {sections.map((section) => {
                    const start = offset;
                    offset += section.emoji.length;

                    return (
                        <div
                            key={section.name}
                            role="group"
                            aria-label={section.name}
                        >
                            {!isSearching && (
                                <div className="sticky top-0 z-10 bg-popover px-1.5 pt-2 pb-1 text-[11px] font-medium text-muted-foreground">
                                    {section.name}
                                </div>
                            )}
                            <div
                                className={cn('grid grid-cols-8', {
                                    'pt-1': isSearching,
                                })}
                            >
                                {section.emoji.map((emoji, index) => (
                                    <EmojiCell
                                        key={emoji}
                                        id={cellId(start + index)}
                                        emoji={emoji}
                                        isActive={
                                            start + index ===
                                            Math.min(
                                                activeIndex,
                                                all.length - 1,
                                            )
                                        }
                                        isReacted={reacted.has(emoji)}
                                        isSheet={isSheet}
                                        onHover={() =>
                                            setActiveIndex(start + index)
                                        }
                                        onPick={() => onPick(emoji)}
                                    />
                                ))}
                            </div>
                        </div>
                    );
                })}
                {!isSearching && groups === null && isSlow && (
                    <div aria-hidden className="grid grid-cols-8 gap-1 p-1.5">
                        {Array.from({ length: 40 }, (_, index) => (
                            <Skeleton key={index} className="aspect-square" />
                        ))}
                    </div>
                )}
            </div>

            <div
                aria-live="polite"
                className="flex h-11 shrink-0 items-center gap-2.5 border-t px-3"
            >
                {active !== undefined && (
                    <>
                        <span aria-hidden className="text-2xl leading-none">
                            {active}
                        </span>
                        <span className="min-w-0 flex-1 truncate font-mono text-[13px]">
                            {shortcodeOf(active)}
                        </span>
                        {(uses.get(active) ?? 0) > 0 && (
                            <span className="shrink-0 font-mono text-[11px] text-muted-foreground tabular-nums">
                                used {uses.get(active)}×
                            </span>
                        )}
                    </>
                )}
            </div>
        </div>
    );
}

function EmojiCell({
    id,
    emoji,
    isActive,
    isReacted,
    isSheet,
    onHover,
    onPick,
}: {
    id: string;
    emoji: string;
    isActive: boolean;
    isReacted: boolean;
    isSheet: boolean;
    onHover: () => void;
    onPick: () => void;
}) {
    return (
        <div
            id={id}
            role="option"
            aria-selected={isActive}
            aria-label={shortcodeOf(emoji)}
            title={shortcodeOf(emoji)}
            onMouseMove={isActive ? undefined : onHover}
            onClick={onPick}
            className={cn(
                'grid aspect-square cursor-pointer place-items-center rounded-md text-xl leading-none select-none',
                {
                    'bg-accent': isActive && !isSheet && !isReacted,
                    'bg-primary-soft': isReacted,
                    'ring-1 ring-primary/60 ring-inset':
                        isActive && !isSheet && isReacted,
                    'text-2xl': isSheet,
                },
            )}
            data-test="emoji-option"
        >
            {emoji}
        </div>
    );
}

/**
 * Pressing and holding a message on a touch screen, and tapping it twice. Moving the finger, as scrolling or swiping
 * to reply does, cancels either, taps on links and buttons in the message don't count towards a double tap, and the
 * tap that ends a long press doesn't also follow a link.
 */
export function useTouchReactions(
    gestures: { onLongPress: () => void; onDoubleTap: () => void } | null,
) {
    const timer = useRef<number | undefined>(undefined);
    const start = useRef<{ x: number; y: number } | null>(null);
    const lastTap = useRef<Tap | null>(null);
    const didLongPress = useRef(false);
    const didDoubleTap = useRef(false);

    const cancel = () => {
        window.clearTimeout(timer.current);
        start.current = null;
    };

    if (gestures === null) {
        return {};
    }

    return {
        onPointerDown: (event: PointerEvent) => {
            if (event.pointerType !== 'touch') {
                return;
            }

            didLongPress.current = false;
            start.current = { x: event.clientX, y: event.clientY };
            timer.current = window.setTimeout(() => {
                didLongPress.current = true;
                start.current = null;
                lastTap.current = null;
                navigator.vibrate?.(8);
                gestures.onLongPress();
            }, longPressDelay);
        },
        onPointerMove: (event: PointerEvent) => {
            if (
                start.current !== null &&
                Math.hypot(
                    event.clientX - start.current.x,
                    event.clientY - start.current.y,
                ) > 10
            ) {
                cancel();
                lastTap.current = null;
            }
        },
        onPointerUp: (event: PointerEvent) => {
            const isTap =
                start.current !== null &&
                !(event.target as Element).closest('a, button, input, img');

            cancel();

            if (!isTap) {
                return;
            }

            const tap = {
                x: event.clientX,
                y: event.clientY,
                time: event.timeStamp,
            };

            if (isDoubleTap(lastTap.current, tap)) {
                lastTap.current = null;
                didDoubleTap.current = true;
                navigator.vibrate?.(8);
                gestures.onDoubleTap();
            } else {
                lastTap.current = tap;
            }
        },
        onPointerCancel: () => {
            cancel();
            lastTap.current = null;
        },
        // The browser would follow a double tap with mouse events that select the word under the finger.
        onTouchEnd: (event: { preventDefault: () => void }) => {
            if (didDoubleTap.current) {
                didDoubleTap.current = false;
                event.preventDefault();
            }
        },
        onContextMenu: (event: { preventDefault: () => void }) => {
            if (start.current !== null || didLongPress.current) {
                event.preventDefault();
            }
        },
        onClickCapture: (event: {
            preventDefault: () => void;
            stopPropagation: () => void;
        }) => {
            if (didLongPress.current) {
                didLongPress.current = false;
                event.preventDefault();
                event.stopPropagation();
            }
        },
    };
}

/**
 * What a long press opens on a touch screen: the message lifted above the thread with the quick reactions over it,
 * and the full picker in a sheet from the bottom.
 */
export function ReactionSheet({
    isOpen,
    onOpenChange,
    usage,
    reacted,
    isMine,
    body,
    preview,
    onReact,
}: {
    isOpen: boolean;
    onOpenChange: (isOpen: boolean) => void;
    usage: ReactionUsage[];
    reacted: Set<string>;
    isMine: boolean;
    body: string;
    preview: ReactNode;
    onReact: (emoji: string) => void;
}) {
    const [isPicking, setIsPicking] = useState(false);
    const isArmed = useRef(false);

    const close = () => {
        onOpenChange(false);
        setIsPicking(false);
    };

    const react = (emoji: string) => {
        close();
        onReact(emoji);
    };

    return (
        <DialogPrimitive.Root
            open={isOpen}
            onOpenChange={(open) => {
                if (!open) {
                    close();
                }
            }}
        >
            <DialogPrimitive.Portal>
                <DialogPrimitive.Overlay className="fixed inset-0 z-50 bg-background/70 backdrop-blur-sm data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:animate-in data-[state=open]:fade-in-0" />
                <DialogPrimitive.Content
                    aria-describedby={undefined}
                    onOpenAutoFocus={(event) => {
                        event.preventDefault();
                        isArmed.current = false;
                    }}
                    // The finger that long pressed lifts over the sheet, which must not count as a tap on it.
                    onPointerDownCapture={() => {
                        isArmed.current = true;
                    }}
                    onClickCapture={(event) => {
                        if (!isArmed.current && event.detail > 0) {
                            event.preventDefault();
                            event.stopPropagation();
                        }
                    }}
                    // The content covers the screen, so a tap anywhere but the message and its controls closes it.
                    onClick={(event) => {
                        if (
                            !(event.target as Element).closest(
                                '[data-sheet-part]',
                            )
                        ) {
                            close();
                        }
                    }}
                    className="fixed inset-0 z-50 flex flex-col px-3 pt-[max(1rem,env(safe-area-inset-top))] outline-none"
                    data-test="reaction-sheet"
                >
                    <DialogPrimitive.Title className="sr-only">
                        React to the message
                    </DialogPrimitive.Title>
                    <div
                        className={cn(
                            'flex min-h-0 flex-col gap-2 transition-[flex-grow] duration-300 ease-out motion-safe:animate-in motion-safe:fade-in-0 motion-safe:zoom-in-95',
                            isPicking ? 'grow-0' : 'grow justify-center',
                            { 'items-end': isMine },
                        )}
                    >
                        <div
                            role="toolbar"
                            aria-label="React"
                            data-sheet-part
                            className="flex w-max items-center gap-1 rounded-full border bg-popover p-1 shadow-[0_10px_28px_-10px_rgb(0_0_0/0.45)]"
                        >
                            {quickReactions(usage).map((emoji) => (
                                <button
                                    key={emoji}
                                    type="button"
                                    aria-label={`React with ${shortcodeOf(emoji)}`}
                                    aria-pressed={reacted.has(emoji)}
                                    onClick={() => react(emoji)}
                                    className={cn(
                                        'grid size-11 place-items-center rounded-full text-2xl leading-none transition-transform active:scale-90',
                                        {
                                            'bg-primary-soft':
                                                reacted.has(emoji),
                                        },
                                    )}
                                >
                                    {emoji}
                                </button>
                            ))}
                            <span
                                aria-hidden
                                className="mx-0.5 h-6 w-px bg-border"
                            />
                            <button
                                type="button"
                                aria-label="More reactions"
                                aria-expanded={isPicking}
                                onClick={() => setIsPicking(true)}
                                className={cn(
                                    'grid size-11 place-items-center rounded-full text-muted-foreground',
                                    { 'bg-accent text-foreground': isPicking },
                                )}
                            >
                                <SmilePlus aria-hidden className="size-5" />
                            </button>
                        </div>
                        <div
                            data-sheet-part
                            className="max-h-[30dvh] max-w-[85%] overflow-hidden"
                        >
                            {preview}
                        </div>
                        {body !== '' && !isPicking && (
                            <button
                                type="button"
                                onClick={() => {
                                    void navigator.clipboard
                                        .writeText(body)
                                        .then(() => toast.success('Copied.'));
                                    close();
                                }}
                                data-sheet-part
                                className="flex w-max items-center gap-2 rounded-lg border bg-popover px-3 py-2 text-sm shadow-sm"
                            >
                                <Copy aria-hidden className="size-4" />
                                Copy text
                            </button>
                        )}
                    </div>
                    {isPicking && (
                        <PickerSheet onCollapse={() => setIsPicking(false)}>
                            <EmojiPicker
                                usage={usage}
                                reacted={reacted}
                                onPick={react}
                                isSheet
                            />
                        </PickerSheet>
                    )}
                </DialogPrimitive.Content>
            </DialogPrimitive.Portal>
        </DialogPrimitive.Root>
    );
}

/**
 * How far the sheet is pulled down before letting go hides it.
 */
const collapseDistance = 80;

/**
 * The emoji sheet from the bottom of the screen. Pulling its handle down, or tapping it, hides the sheet again and
 * goes back to the quick reactions.
 */
function PickerSheet({
    onCollapse,
    children,
}: {
    onCollapse: () => void;
    children: ReactNode;
}) {
    const [offset, setOffset] = useState(0);
    const [isDragging, setIsDragging] = useState(false);
    const start = useRef<number | null>(null);

    const release = () => {
        if (start.current === null) {
            return;
        }

        start.current = null;
        setIsDragging(false);

        if (offset >= collapseDistance) {
            onCollapse();
        }

        setOffset(0);
    };

    return (
        <div
            data-sheet-part
            style={
                offset > 0
                    ? { transform: `translateY(${offset}px)` }
                    : undefined
            }
            className={cn(
                '-mx-3 mt-auto flex h-[min(60dvh,28rem)] flex-col rounded-t-2xl border-t bg-popover pb-[env(safe-area-inset-bottom)] shadow-[0_-12px_30px_-16px_rgb(0_0_0/0.45)] motion-safe:animate-in motion-safe:slide-in-from-bottom',
                { 'transition-transform duration-200': !isDragging },
            )}
            data-test="emoji-sheet"
        >
            <button
                type="button"
                aria-label="Hide emoji picker"
                onPointerDown={(event) => {
                    event.currentTarget.setPointerCapture(event.pointerId);
                    start.current = event.clientY;
                    setIsDragging(true);
                }}
                onPointerMove={(event) => {
                    if (start.current !== null) {
                        setOffset(Math.max(0, event.clientY - start.current));
                    }
                }}
                onPointerUp={() => {
                    if (offset < 4) {
                        start.current = null;
                        setIsDragging(false);
                        onCollapse();

                        return;
                    }

                    release();
                }}
                onPointerCancel={release}
                onClick={(event) => {
                    if (event.detail === 0) {
                        onCollapse();
                    }
                }}
                className="flex h-7 w-full shrink-0 touch-none items-center justify-center"
                data-test="emoji-sheet-handle"
            >
                <span aria-hidden className="h-1 w-9 rounded-full bg-border" />
            </button>
            {children}
        </div>
    );
}
