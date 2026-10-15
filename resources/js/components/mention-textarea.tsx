import type { ComponentProps, KeyboardEvent } from 'react';
import {
    useEffect,
    useId,
    useImperativeHandle,
    useLayoutEffect,
    useRef,
    useState,
} from 'react';
import { createPortal } from 'react-dom';
import UserSearchController from '@/actions/App/Http/Controllers/UserSearchController';
import { UserAvatar } from '@/components/user-avatar';
import { emojifyInput } from '@/lib/emoji';
import { activeMention, caretRect, insertMention } from '@/lib/mentions';
import type { MentionFragment } from '@/lib/mentions';
import { cn } from '@/lib/utils';
import type { User } from '@/types';

type Props = Omit<ComponentProps<'textarea'>, 'value' | 'onChange'> & {
    value: string;
    onValueChange: (value: string) => void;
};

export function MentionTextarea({
    value,
    onValueChange,
    ref,
    onKeyDown,
    onFocus,
    onBlur,
    onSelect,
    onScroll,
    maxLength = 280,
    ...props
}: Props) {
    const textarea = useRef<HTMLTextAreaElement>(null);
    useImperativeHandle(ref, () => textarea.current!, []);
    const listId = useId();
    const composing = useRef(false);
    const [fragment, setFragment] = useState<MentionFragment | null>(null);
    const [activeIndex, setActiveIndex] = useState(0);
    const [response, setResponse] = useState<{
        query: string;
        users: User[];
        error: boolean;
    } | null>(null);
    const [position, setPosition] = useState<{
        left: number;
        top?: number;
        bottom?: number;
        width: number;
        maxHeight: number;
    } | null>(null);
    const [notice, setNotice] = useState('');
    const open =
        fragment !== null &&
        value.slice(
            fragment.start,
            fragment.start + fragment.query.length + 1,
        ) === `@${fragment.query}`;
    const query = open ? fragment.query : null;
    const users =
        query !== null && response?.query === query ? response.users : [];
    const loading = query !== null && query !== '' && response?.query !== query;
    const failed =
        query !== null && response?.query === query && response.error;

    const inspect = (
        element: Pick<
            HTMLTextAreaElement,
            'value' | 'selectionStart' | 'selectionEnd'
        >,
    ) => {
        if (composing.current) return;
        const next = activeMention(
            element.value,
            element.selectionStart,
            element.selectionEnd,
        );
        if (
            next?.start === fragment?.start &&
            next?.end === fragment?.end &&
            next?.query === fragment?.query
        )
            return;
        setFragment(next);
        setActiveIndex(0);
        setNotice('');
    };

    useEffect(() => {
        if (query === null || query === '') return;
        const controller = new AbortController();
        let current = true;
        const timeout = setTimeout(async () => {
            try {
                const result = await fetch(
                    UserSearchController.index({ query: { q: query } }).url,
                    {
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        signal: controller.signal,
                    },
                );
                if (!result.ok) throw new Error('Search failed');
                const data: { users: User[] } = await result.json();
                if (current)
                    setResponse({ query, users: data.users, error: false });
            } catch {
                if (current) setResponse({ query, users: [], error: true });
            }
        }, 120);
        return () => {
            current = false;
            clearTimeout(timeout);
            controller.abort();
        };
    }, [query, open]);

    useLayoutEffect(() => {
        if (!open) return;
        const update = () => {
            if (!textarea.current) return;
            const rect = caretRect(textarea.current);
            const viewport = window.visualViewport;
            const leftEdge = (viewport?.offsetLeft ?? 0) + 8;
            const topEdge = (viewport?.offsetTop ?? 0) + 8;
            const bottom =
                topEdge + (viewport?.height ?? window.innerHeight) - 16;
            const inputRect = textarea.current.getBoundingClientRect();
            if (inputRect.bottom < topEdge || inputRect.top > bottom) {
                setFragment(null);
                return;
            }
            const anchorTop = Math.max(topEdge, Math.min(bottom, rect.top));
            const anchorBottom = Math.max(
                topEdge,
                Math.min(bottom, rect.bottom),
            );
            const width = Math.min(
                360,
                (viewport?.width ?? window.innerWidth) - 16,
            );
            const below = bottom - anchorBottom - 6;
            const above = anchorTop - topEdge - 6;
            const flip = below < 220 && above > below;
            const height = Math.max(48, Math.min(280, flip ? above : below));
            setPosition({
                left: Math.max(
                    leftEdge,
                    Math.min(
                        rect.left,
                        leftEdge +
                            (viewport?.width ?? window.innerWidth) -
                            width -
                            16,
                    ),
                ),
                top: flip ? undefined : anchorBottom + 6,
                bottom: flip ? window.innerHeight - anchorTop + 6 : undefined,
                width,
                maxHeight: height,
            });
        };
        update();
        window.addEventListener('resize', update);
        window.addEventListener('scroll', update, true);
        window.visualViewport?.addEventListener('resize', update);
        window.visualViewport?.addEventListener('scroll', update);
        return () => {
            window.removeEventListener('resize', update);
            window.removeEventListener('scroll', update, true);
            window.visualViewport?.removeEventListener('resize', update);
            window.visualViewport?.removeEventListener('scroll', update);
        };
    }, [open, fragment?.start, fragment?.end, value]);

    useEffect(() => {
        if (open)
            document
                .getElementById(`${listId}-${activeIndex}`)
                ?.scrollIntoView({ block: 'nearest' });
    }, [activeIndex, listId, open]);

    const choose = (user: User) => {
        const element = textarea.current;
        if (!element || !fragment) return;
        const current = activeMention(
            element.value,
            element.selectionStart,
            element.selectionEnd,
        );
        if (!current || current.start !== fragment.start) return;
        const result = insertMention(
            element.value,
            current,
            user.username,
            maxLength,
        );
        if (!result) {
            setNotice(
                'This mention would exceed 280 characters. Shorten your text first.',
            );
            return;
        }
        onValueChange(result.value);
        setFragment(null);
        requestAnimationFrame(() => {
            element.focus();
            element.setSelectionRange(result.caret, result.caret);
        });
    };

    const change = (element: HTMLTextAreaElement) => {
        if (composing.current) {
            onValueChange(element.value);
            return;
        }
        const { text, caret } = emojifyInput(
            element.value,
            element.selectionStart,
        );
        onValueChange(text);
        if (text === element.value) {
            inspect(element);
            return;
        }
        inspect({ value: text, selectionStart: caret, selectionEnd: caret });
        requestAnimationFrame(() => element.setSelectionRange(caret, caret));
    };

    const keyDown = (event: KeyboardEvent<HTMLTextAreaElement>) => {
        if (
            composing.current ||
            event.nativeEvent.isComposing ||
            event.keyCode === 229
        )
            return;
        if (
            open &&
            !event.metaKey &&
            !event.ctrlKey &&
            !event.altKey &&
            !event.shiftKey
        ) {
            if (event.key === 'Escape') {
                event.preventDefault();
                event.stopPropagation();
                setFragment(null);
                return;
            }
            if (
                users.length &&
                (event.key === 'ArrowDown' || event.key === 'ArrowUp')
            ) {
                event.preventDefault();
                setActiveIndex(
                    (index) =>
                        (index +
                            (event.key === 'ArrowDown' ? 1 : -1) +
                            users.length) %
                        users.length,
                );
                return;
            }
            if (
                users[activeIndex] &&
                (event.key === 'Enter' || event.key === 'Tab')
            ) {
                event.preventDefault();
                choose(users[activeIndex]);
                return;
            }
        }
        if (
            !open &&
            (event.key === 'ArrowDown' || event.key === 'ArrowUp') &&
            activeMention(
                event.currentTarget.value,
                event.currentTarget.selectionStart,
                event.currentTarget.selectionEnd,
            )
        ) {
            event.preventDefault();
            inspect(event.currentTarget);
            return;
        }
        onKeyDown?.(event);
    };

    return (
        <>
            <textarea
                {...props}
                ref={textarea}
                value={value}
                maxLength={maxLength}
                role="combobox"
                aria-autocomplete="list"
                aria-haspopup="listbox"
                aria-expanded={open}
                aria-controls={open ? listId : undefined}
                aria-activedescendant={
                    open && users[activeIndex]
                        ? `${listId}-${activeIndex}`
                        : undefined
                }
                onChange={(event) => change(event.currentTarget)}
                onSelect={(event) => {
                    onSelect?.(event);
                    inspect(event.currentTarget);
                }}
                onFocus={(event) => {
                    onFocus?.(event);
                    inspect(event.currentTarget);
                }}
                onBlur={(event) => {
                    onBlur?.(event);
                    setFragment(null);
                }}
                onScroll={onScroll}
                onCompositionStart={() => {
                    composing.current = true;
                    setFragment(null);
                }}
                onCompositionEnd={(event) => {
                    composing.current = false;
                    change(event.currentTarget);
                }}
                onKeyDown={keyDown}
            />
            {open &&
                position &&
                createPortal(
                    <div
                        style={position}
                        className="fixed z-50 flex flex-col overflow-hidden rounded-lg border bg-popover text-popover-foreground shadow-lg"
                        data-test="mention-popup"
                    >
                        <div className="flex shrink-0 items-center justify-between border-b px-3 py-2 text-xs text-muted-foreground">
                            <span>Mention a developer</span>
                            <span
                                aria-hidden
                                className="hidden font-mono sm:inline"
                            >
                                ↑↓ enter · esc
                            </span>
                        </div>
                        <ul
                            id={listId}
                            role="listbox"
                            aria-label="Developers to mention"
                            aria-busy={loading}
                            className="min-h-0 overflow-y-auto overscroll-contain p-1"
                        >
                            {users.map((user, index) => (
                                <li
                                    key={user.id}
                                    id={`${listId}-${index}`}
                                    role="option"
                                    aria-selected={index === activeIndex}
                                    onPointerMove={() => setActiveIndex(index)}
                                    onPointerDown={(event) =>
                                        event.preventDefault()
                                    }
                                    onClick={() => choose(user)}
                                    className={cn(
                                        'flex min-h-12 cursor-pointer items-center gap-2.5 rounded-md px-2 py-2',
                                        index === activeIndex && 'bg-accent',
                                    )}
                                >
                                    <UserAvatar
                                        user={user}
                                        className="size-8 shrink-0"
                                    />
                                    <span className="min-w-0">
                                        <span className="block truncate text-sm font-semibold">
                                            {user.name}
                                        </span>
                                        <span className="block truncate font-mono text-xs text-muted-foreground">
                                            @{user.username}
                                        </span>
                                    </span>
                                </li>
                            ))}
                        </ul>
                        {(users.length === 0 || notice) && (
                            <p
                                role="status"
                                className="px-3 pb-3 text-sm text-muted-foreground"
                            >
                                {notice ||
                                    (query === ''
                                        ? 'Type a name or @handle.'
                                        : loading
                                          ? 'Searching…'
                                          : failed
                                            ? 'Search unavailable. Keep typing to try again.'
                                            : `No developers match '${query}'.`)}
                            </p>
                        )}
                    </div>,
                    document.body,
                )}
        </>
    );
}
