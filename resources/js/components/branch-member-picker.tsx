import { Search, X } from 'lucide-react';
import {
    type KeyboardEvent,
    useEffect,
    useId,
    useRef,
    useState,
} from 'react';
import BranchMemberCandidateController from '@/actions/App/Http/Controllers/BranchMemberCandidateController';
import { Spinner } from '@/components/ui/spinner';
import { UserAvatar } from '@/components/user-avatar';
import { cn } from '@/lib/utils';
import type { User } from '@/types';

const searchDebounce = 120;

type Candidate = { user: User; reason: string | null };

type Found = { term: string; candidates: Candidate[] };

/**
 * Search-styled picker for inviting someone from the maintainer's follow graph onto a branch.
 */
export function BranchMemberPicker({
    branch,
    disabled = false,
    name = 'members[]',
    onPickedChange,
}: {
    branch: string;
    disabled?: boolean;
    name?: string;
    onPickedChange?: (username: string | null) => void;
}) {
    const listId = useId();
    const rootRef = useRef<HTMLDivElement>(null);
    const [query, setQuery] = useState('');
    const [open, setOpen] = useState(false);
    const [found, setFound] = useState<Found | null>(null);
    const [loading, setLoading] = useState(false);
    const [activeIndex, setActiveIndex] = useState(0);
    const [picked, setPicked] = useState<User | null>(null);
    const term = query.trim().replace(/^@/u, '');

    useEffect(() => {
        if (!open || picked !== null) {
            return;
        }

        const controller = new AbortController();
        const timeout = window.setTimeout(() => {
            setLoading(true);
            void fetchCandidates(branch, term, controller.signal)
                .then((candidates) => {
                    setFound({ term, candidates });
                    setActiveIndex(0);
                })
                .catch(() => undefined)
                .finally(() => setLoading(false));
        }, term === '' ? 0 : searchDebounce);

        return () => {
            window.clearTimeout(timeout);
            controller.abort();
        };
    }, [branch, open, picked, term]);

    useEffect(() => {
        if (!open) {
            return;
        }

        const onPointerDown = (event: PointerEvent) => {
            if (
                rootRef.current !== null &&
                !rootRef.current.contains(event.target as Node)
            ) {
                setOpen(false);
            }
        };

        document.addEventListener('pointerdown', onPointerDown);

        return () => document.removeEventListener('pointerdown', onPointerDown);
    }, [open]);

    const results =
        open && picked === null && found?.term === term
            ? found.candidates
            : null;
    const selectable =
        results?.filter((candidate) => candidate.reason === null) ?? [];
    const active = selectable[activeIndex] ?? null;

    const pick = (user: User) => {
        setPicked(user);
        onPickedChange?.(user.username);
        setQuery('');
        setFound(null);
        setOpen(false);
    };

    const clear = () => {
        setPicked(null);
        onPickedChange?.(null);
        setQuery('');
        setFound(null);
        setOpen(true);
    };

    const onKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
        if (event.nativeEvent.isComposing) {
            return;
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            setOpen(false);

            return;
        }

        if (selectable.length === 0) {
            return;
        }

        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            setOpen(true);
            setActiveIndex((index) => {
                const next =
                    event.key === 'ArrowDown'
                        ? index + 1
                        : index - 1 + selectable.length;

                return next % selectable.length;
            });

            return;
        }

        if (event.key === 'Enter' && active !== null) {
            event.preventDefault();
            pick(active.user);
        }
    };

    return (
        <div ref={rootRef} className="relative min-w-0 flex-1 sm:max-w-xs">
            {picked !== null ? (
                <div className="flex h-11 items-center gap-2.5 rounded-lg border bg-card px-3">
                    <input type="hidden" name={name} value={picked.username} />
                    <UserAvatar user={picked} className="size-7 shrink-0" />
                    <div className="min-w-0 flex-1">
                        <p className="truncate text-sm font-semibold leading-tight">
                            {picked.name}
                        </p>
                        <p className="truncate font-mono text-[12px] text-muted-foreground">
                            @{picked.username}
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={clear}
                        disabled={disabled}
                        aria-label="Clear selection"
                        className="grid size-7 shrink-0 place-items-center rounded-full text-muted-foreground transition-colors hover:bg-accent hover:text-foreground disabled:opacity-60"
                    >
                        <X className="size-4" />
                    </button>
                </div>
            ) : (
                <label className="relative flex h-11 items-center gap-2.5 rounded-lg border bg-card px-4 text-muted-foreground transition-colors focus-within:border-ring focus-within:text-foreground">
                    <Search className="size-4 shrink-0" aria-hidden />
                    <span className="sr-only">Find people</span>
                    <input
                        value={query}
                        onChange={(event) => {
                            setQuery(event.target.value);
                            setOpen(true);
                        }}
                        onFocus={() => setOpen(true)}
                        onKeyDown={onKeyDown}
                        disabled={disabled}
                        placeholder="Search"
                        autoComplete="off"
                        spellCheck={false}
                        role="combobox"
                        aria-expanded={open && results !== null}
                        aria-controls={listId}
                        aria-autocomplete="list"
                        aria-activedescendant={
                            active ? `${listId}-${active.user.id}` : undefined
                        }
                        className="h-full min-w-0 flex-1 bg-transparent text-[15px] text-foreground outline-none placeholder:text-muted-foreground disabled:opacity-60"
                        data-test="branch-member-search"
                    />
                    {loading && <Spinner className="size-4 shrink-0" />}
                </label>
            )}

            {open && picked === null && (
                <ul
                    id={listId}
                    role="listbox"
                    aria-label="People"
                    className="absolute top-[calc(100%+0.35rem)] z-20 max-h-64 w-full overflow-y-auto overscroll-contain rounded-lg border bg-popover py-1 shadow-md"
                    data-test="branch-member-candidates"
                >
                    {results === null ? (
                        <li className="px-3 py-6 text-center text-sm text-muted-foreground">
                            Searching…
                        </li>
                    ) : results.length === 0 ? (
                        <li className="px-3 py-6 text-center font-mono text-[13px] text-muted-foreground">
                            {term === ''
                                ? 'Nobody in your follow graph yet'
                                : `fatal: nothing matches '${term}'`}
                        </li>
                    ) : (
                        results.map((candidate) => {
                            const isDisabled = candidate.reason !== null;
                            const isActive =
                                !isDisabled &&
                                active?.user.id === candidate.user.id;

                            return (
                                <li key={candidate.user.id} role="presentation">
                                    <button
                                        type="button"
                                        id={`${listId}-${candidate.user.id}`}
                                        role="option"
                                        aria-selected={isActive}
                                        disabled={isDisabled}
                                        onMouseEnter={() => {
                                            if (!isDisabled) {
                                                const index = selectable.findIndex(
                                                    (item) =>
                                                        item.user.id ===
                                                        candidate.user.id,
                                                );

                                                if (index >= 0) {
                                                    setActiveIndex(index);
                                                }
                                            }
                                        }}
                                        onClick={() => pick(candidate.user)}
                                        className={cn(
                                            'flex w-full items-center gap-3 px-3 py-2 text-left transition-colors not-disabled:hover:bg-accent disabled:cursor-not-allowed',
                                            { 'bg-accent': isActive },
                                        )}
                                        data-test="branch-member-candidate"
                                    >
                                        <UserAvatar
                                            user={candidate.user}
                                            className={cn('size-8 shrink-0', {
                                                'opacity-50': isDisabled,
                                            })}
                                        />
                                        <span
                                            className={cn('min-w-0 flex-1', {
                                                'opacity-60': isDisabled,
                                            })}
                                        >
                                            <span className="block truncate text-sm font-semibold">
                                                {candidate.user.name}
                                            </span>
                                            <span className="flex min-w-0 items-center gap-1 font-mono text-xs text-muted-foreground">
                                                <span className="truncate">
                                                    @{candidate.user.username}
                                                </span>
                                                {isDisabled && (
                                                    <span className="truncate">
                                                        · {candidate.reason}
                                                    </span>
                                                )}
                                            </span>
                                        </span>
                                    </button>
                                </li>
                            );
                        })
                    )}
                </ul>
            )}
        </div>
    );
}

async function fetchCandidates(
    branch: string,
    term: string,
    signal: AbortSignal,
): Promise<Candidate[]> {
    const response = await fetch(
        BranchMemberCandidateController.index.url(branch, {
            query: { q: term },
        }),
        {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            cache: 'no-store',
            signal,
        },
    );

    if (!response.ok) {
        return [];
    }

    const data = (await response.json()) as { candidates: Candidate[] };

    return data.candidates;
}
