import { GitBranch, X } from 'lucide-react';
import { type KeyboardEvent, useEffect, useRef, useState } from 'react';
import HashtagSuggestionController from '@/actions/App/Http/Controllers/HashtagSuggestionController';
import { ActionTooltip } from '@/components/action-tooltip';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { isValidBranchName, normalizeBranchName } from '@/lib/branches';
import { toastWhenThrottled } from '@/lib/throttle';
import { cn } from '@/lib/utils';

type Suggestion = {
    name: string;
    count: number;
};

const debounce = 120;
const cache = new Map<string, Suggestion[]>();

async function fetchSuggestions(
    query: string,
    signal: AbortSignal,
): Promise<Suggestion[]> {
    const cached = cache.get(query);

    if (cached) {
        return cached;
    }

    const response = await fetch(
        HashtagSuggestionController.index({ query: { q: query } }).url,
        {
            headers: { Accept: 'application/json' },
            signal,
        },
    );

    if (toastWhenThrottled(response) || !response.ok) {
        return [];
    }

    const data = (await response.json()) as { hashtags: Suggestion[] };
    cache.set(query, data.hashtags);

    return data.hashtags;
}

/**
 * Toolbar toggle for the optional branch field.
 */
export function BranchPickerButton({
    isOpen,
    hasBranch,
    disabled,
    controls,
    onClick,
    className,
}: {
    isOpen: boolean;
    hasBranch: boolean;
    disabled?: boolean;
    controls: string;
    onClick: () => void;
    className?: string;
}) {
    return (
        <ActionTooltip label={hasBranch ? 'Change branch' : 'Add branch'}>
            <Button
                type="button"
                variant="ghost"
                size="icon"
                disabled={disabled}
                aria-label={hasBranch ? 'Change branch' : 'Add branch'}
                aria-expanded={isOpen}
                aria-controls={controls}
                onClick={onClick}
                className={cn(
                    'text-muted-foreground aria-expanded:bg-primary-soft aria-expanded:text-primary aria-expanded:hover:bg-primary-soft aria-expanded:hover:text-primary',
                    {
                        'bg-primary-soft text-primary hover:bg-primary-soft hover:text-primary':
                            hasBranch && !isOpen,
                    },
                    className,
                )}
                data-test="branch-toggle"
            >
                <GitBranch className="size-5" />
            </Button>
        </ActionTooltip>
    );
}

/**
 * Branch name field shown when the toolbar toggle is open (or a branch is already set).
 */
export function BranchPicker({
    id,
    value,
    onChange,
    name,
    error,
    disabled,
    className,
    autoFocus = false,
}: {
    id: string;
    value: string;
    onChange: (value: string) => void;
    name?: string;
    error?: string;
    disabled?: boolean;
    className?: string;
    autoFocus?: boolean;
}) {
    const listId = `${id}-suggestions`;
    const inputRef = useRef<HTMLInputElement>(null);
    const listRef = useRef<HTMLUListElement>(null);
    const [isOpen, setIsOpen] = useState(false);
    const [activeIndex, setActiveIndex] = useState(0);
    const [fetched, setFetched] = useState<{
        query: string;
        suggestions: Suggestion[];
    } | null>(null);

    const query = normalizeBranchName(value) ?? '';
    const suggestionQuery = query.length >= 2 ? query : null;
    const suggestions =
        suggestionQuery === null
            ? []
            : (cache.get(suggestionQuery) ??
              (fetched?.query === suggestionQuery ? fetched.suggestions : []));
    const isSearching =
        suggestionQuery !== null &&
        !cache.has(suggestionQuery) &&
        fetched?.query !== suggestionQuery;
    const showList =
        isOpen &&
        suggestionQuery !== null &&
        (suggestions.length > 0 || isSearching);
    const isInvalid =
        value.trim() !== '' &&
        !isValidBranchName(normalizeBranchName(value) ?? '');

    useEffect(() => {
        if (autoFocus) {
            inputRef.current?.focus();
        }
    }, [autoFocus]);

    useEffect(() => {
        if (suggestionQuery === null || cache.has(suggestionQuery)) {
            return;
        }

        const controller = new AbortController();
        const timeout = setTimeout(
            () =>
                fetchSuggestions(suggestionQuery, controller.signal)
                    .then((next) =>
                        setFetched({
                            query: suggestionQuery,
                            suggestions: next,
                        }),
                    )
                    .catch(() => undefined),
            debounce,
        );

        return () => {
            clearTimeout(timeout);
            controller.abort();
        };
    }, [suggestionQuery]);

    useEffect(() => {
        setActiveIndex(0);
    }, [suggestionQuery]);

    useEffect(() => {
        listRef.current
            ?.querySelector('[aria-selected="true"]')
            ?.scrollIntoView({ block: 'nearest' });
    }, [activeIndex]);

    const select = (branch: string) => {
        onChange(branch);
        setIsOpen(false);
        inputRef.current?.focus();
    };

    const onKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
        if (!showList || event.nativeEvent.isComposing) {
            return;
        }

        if (
            (event.key === 'ArrowDown' || event.key === 'ArrowUp') &&
            suggestions.length > 0
        ) {
            event.preventDefault();
            const step = event.key === 'ArrowDown' ? 1 : -1;
            setActiveIndex(
                (index) =>
                    (index + step + suggestions.length) % suggestions.length,
            );

            return;
        }

        if (
            (event.key === 'Enter' || event.key === 'Tab') &&
            !event.metaKey &&
            !event.ctrlKey &&
            !event.altKey &&
            !event.shiftKey &&
            suggestions[activeIndex]
        ) {
            event.preventDefault();
            select(suggestions[activeIndex].name);

            return;
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            setIsOpen(false);
        }
    };

    return (
        <div
            id={id}
            className={cn('relative', className)}
            data-test="branch-picker"
        >
            <div
                className={cn(
                    'flex items-center gap-1.5 rounded-md border bg-background px-2 py-1 focus-within:border-ring focus-within:ring-[3px] focus-within:ring-ring/50',
                    {
                        'border-destructive': error || isInvalid,
                    },
                )}
            >
                <GitBranch
                    className="size-3.5 shrink-0 text-muted-foreground"
                    aria-hidden
                />
                <label htmlFor={`${id}-input`} className="sr-only">
                    Branch
                </label>
                <input
                    ref={inputRef}
                    id={`${id}-input`}
                    type="text"
                    name={name || undefined}
                    value={value}
                    disabled={disabled}
                    autoComplete="off"
                    spellCheck={false}
                    maxLength={50}
                    placeholder="Branch name"
                    aria-invalid={error || isInvalid ? true : undefined}
                    aria-autocomplete="list"
                    aria-controls={showList ? listId : undefined}
                    aria-expanded={showList}
                    aria-activedescendant={
                        showList && suggestions[activeIndex]
                            ? `${id}-option-${suggestions[activeIndex].name}`
                            : undefined
                    }
                    onChange={(event) => {
                        onChange(event.target.value);
                        setIsOpen(true);
                    }}
                    onFocus={() => setIsOpen(true)}
                    onBlur={() => {
                        window.setTimeout(() => setIsOpen(false), 100);
                    }}
                    onKeyDown={onKeyDown}
                    className="min-w-0 flex-1 bg-transparent font-mono text-[13px] outline-none placeholder:text-muted-foreground"
                    data-test="branch-input"
                />
                {value !== '' && (
                    <button
                        type="button"
                        disabled={disabled}
                        onClick={() => {
                            onChange('');
                            inputRef.current?.focus();
                        }}
                        className="rounded p-0.5 text-muted-foreground hover:bg-accent hover:text-foreground"
                        aria-label="Clear branch"
                    >
                        <X className="size-3.5" />
                    </button>
                )}
            </div>
            {showList && (
                <div
                    className="absolute inset-x-0 top-full z-50 mt-1 overflow-hidden rounded-xl border bg-popover text-popover-foreground shadow-[0_12px_32px_-12px_rgb(0_0_0/0.35)]"
                    onMouseDown={(event) => event.preventDefault()}
                >
                    {suggestions.length > 0 ? (
                        <ul
                            ref={listRef}
                            id={listId}
                            role="listbox"
                            aria-label="Branch suggestions"
                            className="max-h-48 overflow-y-auto p-1.5"
                        >
                            {suggestions.map((suggestion, index) => (
                                <li
                                    key={suggestion.name}
                                    id={`${id}-option-${suggestion.name}`}
                                    role="option"
                                    aria-selected={index === activeIndex}
                                    onMouseMove={() => setActiveIndex(index)}
                                    onClick={() => select(suggestion.name)}
                                    className={cn(
                                        'flex cursor-pointer items-center gap-2 rounded-lg px-2.5 py-1.5 font-mono text-sm',
                                        {
                                            'bg-accent': index === activeIndex,
                                        },
                                    )}
                                    data-test="branch-suggestion"
                                >
                                    <GitBranch
                                        className="size-3.5 shrink-0 text-primary"
                                        aria-hidden
                                    />
                                    <span className="min-w-0 flex-1 truncate text-primary">
                                        {suggestion.name}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <div
                            role="status"
                            className="px-4 py-3 text-sm text-muted-foreground"
                        >
                            <span className="flex items-center gap-2">
                                <Spinner className="size-3.5" />
                                Searching…
                            </span>
                        </div>
                    )}
                </div>
            )}
            {error && (
                <p className="mt-1 text-[13px] text-destructive">{error}</p>
            )}
        </div>
    );
}
