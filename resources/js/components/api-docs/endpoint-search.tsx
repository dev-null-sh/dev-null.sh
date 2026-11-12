import { router } from '@inertiajs/react';
import * as DialogPrimitive from '@radix-ui/react-dialog';
import { BookOpen, Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { KeyboardEvent } from 'react';
import ApiDocsController from '@/actions/App/Http/Controllers/ApiDocsController';
import {
    EndpointPath,
    MethodChip,
    endpointAnchor,
    revealEndpoint,
} from '@/components/api-docs/endpoint';
import { searchApiDocs } from '@/components/api-docs/search';
import type { ApiDocsResult } from '@/components/api-docs/search';
import { DialogOverlay, DialogPortal } from '@/components/ui/dialog';
import { isTyping } from '@/hooks/use-keyboard-shortcuts';
import { hasModKey, useKeyLabels } from '@/lib/keyboard';
import { cn } from '@/lib/utils';
import type { ApiDocsIndexEntry, ApiDocsSection } from '@/types';

const RESULTS_ID = 'api-docs-search-results';

/**
 * ⌘K or Ctrl+K from anywhere, or / when the reader is not typing somewhere else.
 */
export function isSearchShortcut(event: {
    key: string;
    metaKey: boolean;
    ctrlKey: boolean;
    altKey: boolean;
    target: EventTarget | null;
}): boolean {
    if (hasModKey(event)) {
        return event.key.toLowerCase() === 'k';
    }

    return event.key === '/' && !event.altKey && !isTyping(event.target);
}

/**
 * The result an arrow key moves to, round the ends, or null for any other key.
 */
export function moveActive(
    position: number,
    key: string,
    count: number,
): number | null {
    const step = { ArrowDown: 1, ArrowUp: -1 }[key];

    if (step === undefined || count === 0) {
        return null;
    }

    return (Math.min(position, count - 1) + step + count) % count;
}

/**
 * What the reader typed and the result they are on, kept while the search closes so its contents stay still through
 * the exit, and started afresh each time it opens.
 */
export function useSearchQuery(isOpen: boolean) {
    const [query, setQuery] = useState('');
    const [activeIndex, setActiveIndex] = useState(0);
    const [wasOpen, setWasOpen] = useState(isOpen);

    if (isOpen !== wasOpen) {
        setWasOpen(isOpen);

        if (isOpen) {
            setQuery('');
            setActiveIndex(0);
        }
    }

    const type = (value: string) => {
        setQuery(value);
        setActiveIndex(0);
    };

    return { query, type, activeIndex, setActiveIndex };
}

function resultId(result: ApiDocsResult): string {
    return `api-docs-search-${result.kind}-${result.id}`;
}

/**
 * Puts the reader where they picked, as if they had tabbed there: on an endpoint's toggle, or on a guide's first
 * section. The page has already scrolled there, so focus does not scroll it again.
 */
export function focusResult(result: ApiDocsResult): void {
    const selector =
        result.kind === 'endpoint'
            ? `#${endpointAnchor(result.endpoint)} > button`
            : 'main article h2 a';

    document
        .querySelector<HTMLElement>(selector)
        ?.focus({ preventScroll: true });
}

/**
 * Where focus goes once the search has closed: to what was picked on this page, nowhere yet for a page still
 * arriving, since it takes focus once there, or back to the Search button when nothing was picked. The dialog has no
 * trigger of its own, so Radix alone would leave focus on the page's body.
 */
export function handOffFocus(
    pick: { result: ApiDocsResult; isHere: boolean } | null,
    returnFocus: () => void,
): void {
    if (pick === null) {
        returnFocus();

        return;
    }

    if (pick.isHere) {
        focusResult(pick.result);
    }
}

type Visit = (url: string, options: { onSuccess: () => void }) => void;

/**
 * Goes to a guide, or to an endpoint opened at its anchor, and says whether that stays on the page. An endpoint of
 * the page already shown is opened in place, as a link to it would, even when the hash already points at it, and the
 * search closes at once. Another page closes it only once it has arrived: a visit that fails or is cancelled leaves
 * the search open, as typed, with focus in it. Closing says which it was.
 */
export function goToResult(
    result: ApiDocsResult,
    current: string,
    close: (isHere: boolean) => void,
    visit: Visit = (url, options) => router.visit(url, options),
    reveal: (anchor: string) => void = revealEndpoint,
): boolean {
    const arrive = {
        onSuccess: () => {
            close(false);
            window.requestAnimationFrame(() => focusResult(result));
        },
    };

    if (result.kind === 'guide') {
        visit(ApiDocsController.show(result.section.slug).url, arrive);

        return false;
    }

    const anchor = endpointAnchor(result.endpoint);

    if (result.section.slug === current) {
        close(true);
        reveal(anchor);

        return true;
    }

    visit(
        `${ApiDocsController.show(result.section.slug).url}#${anchor}`,
        arrive,
    );

    return false;
}

/**
 * The field and what it finds: a combobox over a listbox of guides and endpoints, the active one on the Accent.
 */
export function SearchPanel({
    query,
    results,
    activeIndex,
    onType,
    onActivate,
    onPick,
}: {
    query: string;
    results: ApiDocsResult[];
    activeIndex: number;
    onType: (query: string) => void;
    onActivate: (index: number) => void;
    onPick: (result: ApiDocsResult) => void;
}) {
    const keys = useKeyLabels();
    const activeResult = results[Math.min(activeIndex, results.length - 1)];

    const onKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
        if (event.nativeEvent.isComposing || results.length === 0) {
            return;
        }

        const next = moveActive(activeIndex, event.key, results.length);

        if (next !== null) {
            event.preventDefault();
            onActivate(next);
        }

        if (event.key === 'Enter' && activeResult) {
            event.preventDefault();
            onPick(activeResult);
        }
    };

    return (
        <>
            <div className="flex items-center gap-3 border-b px-4">
                <Search
                    aria-hidden
                    className="size-5 shrink-0 text-muted-foreground"
                />
                <input
                    autoFocus
                    maxLength={100}
                    value={query}
                    onChange={(event) => onType(event.target.value)}
                    onKeyDown={onKeyDown}
                    placeholder="Search endpoints and guides"
                    aria-label="Search the API docs"
                    role="combobox"
                    aria-expanded={results.length > 0}
                    aria-controls={results.length > 0 ? RESULTS_ID : undefined}
                    aria-activedescendant={
                        activeResult ? resultId(activeResult) : undefined
                    }
                    aria-autocomplete="list"
                    autoComplete="off"
                    spellCheck={false}
                    className="h-14 min-w-0 flex-1 bg-transparent text-[17px] outline-none placeholder:text-muted-foreground focus-visible:outline-none"
                />
                <kbd className="hidden rounded-sm border border-b-2 bg-muted px-1.5 py-0.5 font-mono text-xs text-muted-foreground sm:block">
                    esc
                </kbd>
            </div>

            {results.length === 0 ? (
                <p
                    role="status"
                    className="truncate px-5 py-4 font-mono text-[13px] text-muted-foreground"
                >
                    fatal: nothing matches '{query}'
                </p>
            ) : (
                <ul
                    id={RESULTS_ID}
                    role="listbox"
                    aria-label="Search results"
                    className="max-h-[min(30rem,60dvh)] overflow-y-auto overscroll-contain p-2"
                >
                    {results.map((result, position) => {
                        const isActive = result === activeResult;

                        return (
                            <li
                                key={resultId(result)}
                                id={resultId(result)}
                                role="option"
                                aria-selected={isActive}
                                onMouseMove={() => onActivate(position)}
                                onClick={() => onPick(result)}
                                className={cn(
                                    'flex cursor-pointer items-center gap-3 rounded-lg px-3 py-2.5',
                                    isActive && 'bg-accent',
                                )}
                            >
                                {result.kind === 'guide' ? (
                                    <>
                                        <BookOpen
                                            aria-hidden
                                            className="size-4 shrink-0 text-muted-foreground"
                                        />
                                        <span className="min-w-0 flex-1 truncate text-sm">
                                            <span className="font-medium">
                                                {result.section.title}
                                            </span>
                                            <span className="ml-2 text-muted-foreground">
                                                {result.section.description}
                                            </span>
                                        </span>
                                    </>
                                ) : (
                                    <>
                                        <MethodChip
                                            method={result.endpoint.method}
                                        />
                                        <span className="flex min-w-0 flex-1 flex-col">
                                            <EndpointPath
                                                path={result.endpoint.path}
                                            />
                                            <span className="truncate text-xs text-muted-foreground">
                                                {result.endpoint.summary}
                                            </span>
                                        </span>
                                        <span className="hidden shrink-0 font-mono text-xs text-muted-foreground sm:block">
                                            {result.section.slug}
                                        </span>
                                    </>
                                )}
                                <kbd
                                    aria-hidden
                                    className={cn(
                                        'shrink-0 font-mono text-xs text-muted-foreground opacity-0',
                                        isActive && 'opacity-100',
                                    )}
                                >
                                    ↵
                                </kbd>
                            </li>
                        );
                    })}
                </ul>
            )}

            <div className="hidden items-center gap-4 border-t bg-muted/40 px-4 py-2 text-xs text-muted-foreground sm:flex">
                <span className="flex items-center gap-1.5">
                    <kbd className="font-mono">↑↓</kbd> to move
                </span>
                <span className="flex items-center gap-1.5">
                    <kbd className="font-mono">↵</kbd> to open
                </span>
                <span className="ml-auto flex items-center gap-1.5">
                    <kbd className="font-mono">{keys.combo(keys.mod, 'K')}</kbd>{' '}
                    anywhere in the docs
                </span>
            </div>
        </>
    );
}

/**
 * The API docs' search: every guide and endpoint, found by the words of their method, path, title and summary,
 * as people search does, from ⌘K or / anywhere in the docs.
 */
export function EndpointSearch({
    open: isOpen,
    onOpenChange,
    sections,
    index,
    current,
    returnFocus,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    sections: ApiDocsSection[];
    index: ApiDocsIndexEntry[];
    current: string;
    returnFocus: () => void;
}) {
    const { query, type, activeIndex, setActiveIndex } = useSearchQuery(isOpen);
    const results = searchApiDocs(query, sections, index);
    const activeResult = results[Math.min(activeIndex, results.length - 1)];
    const activeId = activeResult ? resultId(activeResult) : undefined;

    useEffect(() => {
        if (isOpen && activeId) {
            document
                .getElementById(activeId)
                ?.scrollIntoView({ block: 'nearest' });
        }
    }, [isOpen, activeId]);

    const picked = useRef<{ result: ApiDocsResult; isHere: boolean } | null>(
        null,
    );

    const isOpenNow = useRef(isOpen);

    useEffect(() => {
        isOpenNow.current = isOpen;
    }, [isOpen]);

    // A pick is only noted once it closes the search, and only while the search is still open: one whose page never
    // arrived, or arrived after the reader closed the search, leaves no pick behind for the next close.
    const pick = (result: ApiDocsResult) => {
        goToResult(result, current, (isHere) => {
            if (isOpenNow.current) {
                picked.current = { result, isHere };
                onOpenChange(false);
            }
        });
    };

    const onCloseAutoFocus = (event: Event) => {
        const target = picked.current;
        picked.current = null;
        event.preventDefault();
        handOffFocus(target, returnFocus);
    };

    return (
        <DialogPrimitive.Root open={isOpen} onOpenChange={onOpenChange}>
            <DialogPortal>
                <DialogOverlay />
                <DialogPrimitive.Content
                    onCloseAutoFocus={onCloseAutoFocus}
                    className="fixed top-[12vh] left-1/2 z-50 w-full max-w-[calc(100%-2rem)] -translate-x-1/2 overflow-hidden rounded-lg border bg-popover text-popover-foreground shadow-[0_24px_48px_-16px_rgb(0_0_0/0.4)] duration-200 motion-safe:data-[state=closed]:animate-out motion-safe:data-[state=closed]:fade-out-0 motion-safe:data-[state=closed]:zoom-out-95 motion-safe:data-[state=open]:animate-in motion-safe:data-[state=open]:fade-in-0 motion-safe:data-[state=open]:zoom-in-95 sm:max-w-xl"
                >
                    <DialogPrimitive.Title className="sr-only">
                        Search the API docs
                    </DialogPrimitive.Title>
                    <DialogPrimitive.Description className="sr-only">
                        Find a guide, or an endpoint by its method, path or what
                        it does.
                    </DialogPrimitive.Description>

                    <SearchPanel
                        query={query}
                        results={results}
                        activeIndex={activeIndex}
                        onType={type}
                        onActivate={setActiveIndex}
                        onPick={pick}
                    />
                </DialogPrimitive.Content>
            </DialogPortal>
        </DialogPrimitive.Root>
    );
}
