import { Head, router, usePage } from '@inertiajs/react';
import { ArrowRight, History, Search, Sparkles, X } from 'lucide-react';
import type { FormEvent } from 'react';
import { useEffect, useRef, useState } from 'react';
import SearchController from '@/actions/App/Http/Controllers/SearchController';
import { SearchResults } from '@/components/commit-search-results';
import { rememberSearch, useRecentSearches } from '@/hooks/use-recent-searches';
import AppLayout from '@/layouts/app-layout';
import { searchCommits, suggestedQueries } from '@/lib/commit-search';
import type { Commit } from '@/types';

const skeletonDelay = 300;

type Results = { term: string; commits: Commit[] };

export default function Index({ query }: { query: string }) {
    const userId = usePage().props.auth.user?.id ?? null;
    const recentSearches = useRecentSearches(userId);
    const [draft, setDraft] = useState(query);
    const inputRef = useRef<HTMLInputElement>(null);
    const { results, isWaiting } = useCommitSearch(query);

    const search = (term: string) => {
        router.replace({
            url: SearchController.index.url({
                query: term === '' ? {} : { q: term },
            }),
            props: (props: { query: string }) => ({ ...props, query: term }),
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => rememberSearch(userId, term),
        });
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        search(draft.trim());
    };

    const pick = (term: string) => {
        setDraft(term);
        search(term);
    };

    const clear = () => {
        setDraft('');
        search('');
        inputRef.current?.focus();
    };

    return (
        <>
            <Head title={query === '' ? 'Search' : `${query} · Search`} />

            <header className="sticky top-0 z-10 border-b bg-card px-4 py-3 sm:px-6">
                <h1 className="sr-only">Search</h1>
                <form role="search" onSubmit={submit} className="relative">
                    <Search
                        aria-hidden
                        className="pointer-events-none absolute top-1/2 left-4 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <input
                        ref={inputRef}
                        type="search"
                        autoFocus={query === ''}
                        maxLength={100}
                        value={draft}
                        onChange={(event) => setDraft(event.target.value)}
                        placeholder="Search commits by meaning"
                        aria-label="Search commits"
                        autoComplete="off"
                        className="h-11 w-full rounded-lg border bg-background pr-11 pl-10 text-base outline-none placeholder:text-muted-foreground focus-visible:border-ring md:text-[15px] [&::-webkit-search-cancel-button]:hidden"
                        data-test="search-input"
                    />
                    <button
                        type="button"
                        onClick={clear}
                        aria-label="Clear search"
                        className="absolute top-1/2 right-2 flex size-7 -translate-y-1/2 items-center justify-center rounded-md text-muted-foreground transition-[color,opacity] hover:text-foreground disabled:pointer-events-none disabled:opacity-0"
                        disabled={draft === ''}
                    >
                        <X className="size-4" />
                    </button>
                </form>
            </header>

            {query !== '' && (
                <SearchResults results={results} isWaiting={isWaiting} />
            )}
            {recentSearches.length > 0 && (
                <Suggestions onPick={pick} recent={recentSearches} />
            )}
            <Suggestions onPick={pick} exclude={query} />
        </>
    );
}

Index.layout = AppLayout;

/**
 * Keeps showing the previous results while the next ones load, and only reaches for a skeleton when there is nothing
 * to show yet and the wait is long enough to notice.
 */
function useCommitSearch(query: string) {
    const [results, setResults] = useState<Results | null>(null);
    const [isWaiting, setIsWaiting] = useState(false);

    useEffect(() => {
        if (query === '') {
            return;
        }

        const controller = new AbortController();
        const timeout = setTimeout(() => setIsWaiting(true), skeletonDelay);

        searchCommits(query, undefined, controller.signal)
            .then((commits) => setResults({ term: query, commits }))
            .catch(() => {
                if (!controller.signal.aborted) {
                    setResults({ term: query, commits: [] });
                }
            })
            .finally(() => {
                if (!controller.signal.aborted) {
                    setIsWaiting(false);
                    clearTimeout(timeout);
                }
            });

        return () => {
            controller.abort();
            clearTimeout(timeout);
        };
    }, [query]);

    return { results, isWaiting };
}

function Suggestions({
    onPick,
    exclude,
    recent,
}: {
    onPick: (term: string) => void;
    exclude?: string;
    recent?: string[];
}) {
    const visible =
        recent ?? suggestedQueries.filter((term) => term !== exclude);
    const headingId = recent ? 'recent-searches' : 'search-suggestions';

    return (
        <section aria-labelledby={headingId} className="px-4 py-6 sm:px-6">
            <h2
                id={headingId}
                className="mb-3 flex items-center gap-2 font-semibold"
            >
                {recent ? (
                    <History
                        aria-hidden
                        className="size-4 text-muted-foreground"
                    />
                ) : (
                    <Sparkles aria-hidden className="size-4 text-primary" />
                )}
                {recent ? 'Recent searches' : 'Try searching for…'}
            </h2>
            <ul className="space-y-2">
                {visible.map((term) => (
                    <li key={term}>
                        <button
                            type="button"
                            onClick={() => onPick(term)}
                            className="group flex w-full items-center justify-between gap-3 rounded-lg border bg-background px-4 py-2.5 text-left text-sm transition-colors hover:border-input hover:bg-accent"
                        >
                            <span className="min-w-0 truncate" title={term}>
                                {term}
                            </span>
                            <ArrowRight
                                aria-hidden
                                className="size-4 shrink-0 text-muted-foreground transition-transform group-hover:translate-x-0.5 group-hover:text-foreground"
                            />
                        </button>
                    </li>
                ))}
            </ul>
        </section>
    );
}
