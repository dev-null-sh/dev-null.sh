import { Link, router, usePage } from '@inertiajs/react';
import * as DialogPrimitive from '@radix-ui/react-dialog';
import type { LucideIcon } from 'lucide-react';
import {
    ArrowRight,
    Bug,
    History,
    Lightbulb,
    Radio,
    Search,
    Sparkles,
    Square,
    SquarePen,
} from 'lucide-react';
import type { KeyboardEvent, ReactNode } from 'react';
import { useEffect, useRef, useState } from 'react';
import CommitController from '@/actions/App/Http/Controllers/CommitController';
import ProfileSearchController from '@/actions/App/Http/Controllers/ProfileSearchController';
import SearchController from '@/actions/App/Http/Controllers/SearchController';
import { OrganizationAvatar } from '@/components/organization-avatar';
import { DialogOverlay, DialogPortal } from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { UserAvatar } from '@/components/user-avatar';
import { useNow } from '@/hooks/use-now';
import {
    clearRecentSearches,
    rememberSearch,
    useRecentSearches,
} from '@/hooks/use-recent-searches';
import { signIn } from '@/lib/auth';
import { searchCandidates } from '@/lib/chat';
import {
    searchCommits,
    suggestedQueries,
    TooManySearches,
} from '@/lib/commit-search';
import { useKeyLabels } from '@/lib/keyboard';
import type { SearchAction } from '@/lib/search-actions';
import { searchActions } from '@/lib/search-actions';
import {
    awaitsDeveloperName,
    searchesCommits,
    searchIntent,
} from '@/lib/search-intent';
import { cn, formatRelativeTime, laneColor } from '@/lib/utils';
import { show } from '@/routes/user';
import type { Commit, Organization, User } from '@/types';

const developerDebounce = 120;

/**
 * Longer than for developers: each commit search pays for an embedding, so it waits for a pause in typing.
 */
const commitDebounce = 350;

const commitLimit = 5;

type Found<T> = { term: string; items: T[]; throttled?: boolean };

type Option =
    | { kind: 'action'; id: string; action: SearchAction }
    | { kind: 'developer'; id: string; user: User; reason?: string | null }
    | { kind: 'organization'; id: string; organization: Organization }
    | { kind: 'commit'; id: string; commit: Commit }
    | { kind: 'all'; id: string; needsSignIn: boolean }
    | { kind: 'suggestion'; id: string; term: string }
    | { kind: 'recent'; id: string; term: string };

const suggestionOptions: Option[] = suggestedQueries.map((term, index) => ({
    kind: 'suggestion',
    id: `suggestion-${index}`,
    term,
}));

/**
 * Searches developers, organizations and commits. Guests only search developers and organizations: each commit search pays for AI, so they're asked to
 * sign in for those instead. Picking a developer for a new conversation narrows it to developers, says why
 * anyone can't be messaged, and hands the pick back instead of opening their profile. Given `onAction`, it also offers
 * to write a commit, open an issue or go live when the search names one, above what it found.
 */
export function SearchDialog({
    open,
    onOpenChange,
    onPickDeveloper,
    excludeId,
    onAction,
    isLive = false,
    pickerTitle = 'New remote',
    pickerPlaceholder = 'Message a developer',
    pickerScope = 'remote',
    onCloseAutoFocus,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onPickDeveloper?: (user: User) => void;
    excludeId?: string;
    onAction?: (action: SearchAction) => void;
    isLive?: boolean;
    pickerTitle?: string;
    pickerPlaceholder?: string;
    pickerScope?: 'remote' | 'share';
    onCloseAutoFocus?: (event: Event) => void;
}) {
    const userId = usePage().props.auth.user?.id ?? null;
    const recentSearches = useRecentSearches(userId);
    const keys = useKeyLabels();
    const [query, setQuery] = useState('');
    const input = useRef<HTMLInputElement>(null);
    const [activeIndex, setActiveIndex] = useState(0);
    const isPicking = onPickDeveloper !== undefined;
    const isMember = userId !== null;

    const term = query.trim();
    const developerTerm = term.replace(/^@/u, '');
    const intent = isPicking ? 'developer' : searchIntent(term);
    const commitQuery = searchesCommits(intent) ? term : '';
    const commitTerm = isMember ? commitQuery : '';

    const developers = useDebouncedSearch<FoundDeveloper | FoundOrganization>(
        developerTerm,
        developerDebounce,
        isPicking
            ? (value, signal) =>
                  searchCandidates(value, { for: pickerScope }, signal)
            : searchDevelopers,
    );
    const commits = useDebouncedSearch(
        commitTerm,
        commitDebounce,
        (value, signal) => searchCommits(value, commitLimit, signal),
    );

    const visibleDevelopers = developers
        .resultsFor(developerTerm)
        .filter(
            (found): found is FoundDeveloper =>
                'user' in found && found.user.id !== excludeId,
        );
    const visibleOrganizations = developers
        .resultsFor(developerTerm)
        .filter((found): found is FoundOrganization => 'organization' in found);
    const visibleCommits = commits.resultsFor(commitTerm);
    const results = optionsFor(
        intent,
        intent === 'topic' ? visibleDevelopers.slice(0, 2) : visibleDevelopers,
        intent === 'topic'
            ? visibleOrganizations.slice(0, 2)
            : visibleOrganizations,
        visibleCommits,
        commitQuery,
        !isMember,
        onAction && !isPicking ? searchActions(term, { isLive }) : [],
    );
    const isLoading = developers.isLoading || commits.isLoading;
    const hasSearched =
        developers.hasSearched(developerTerm) &&
        (commitTerm === '' || commits.hasSearched(commitTerm));
    const isEmpty = term === '';
    const isAwaitingDeveloperName =
        awaitsDeveloperName(term) || (isPicking && isEmpty);
    const isThrottled =
        developers.isThrottled(developerTerm) ||
        commits.isThrottled(commitTerm);
    const foundNothing =
        !isEmpty &&
        !isAwaitingDeveloperName &&
        hasSearched &&
        !isLoading &&
        results.length === 0;
    const recentOptions: Option[] = recentSearches.map((recent, index) => ({
        kind: 'recent',
        id: `recent-${index}`,
        term: recent,
    }));
    const options =
        isPicking || !(isEmpty || foundNothing)
            ? results
            : [...recentOptions, ...suggestionOptions];

    useEffect(() => {
        if (open && !isPicking && hasSearched && !isLoading && !isThrottled) {
            rememberSearch(userId, term);
        }
    }, [open, isPicking, hasSearched, isLoading, isThrottled, userId, term]);

    const type = (value: string) => {
        setQuery(value);
        setActiveIndex(0);
    };

    const close = () => {
        onOpenChange(false);
        setQuery('');
        setActiveIndex(0);
    };

    const openOption = (option: Option) => {
        if (option.kind === 'suggestion' || option.kind === 'recent') {
            type(option.term);

            return;
        }

        close();

        if (option.kind === 'all' && option.needsSignIn) {
            signIn();

            return;
        }

        if (option.kind === 'action') {
            onAction?.(option.action);

            return;
        }

        if (option.kind === 'developer' && onPickDeveloper) {
            onPickDeveloper(option.user);

            return;
        }

        router.visit(hrefFor(option, term));
    };

    const onKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
        if (event.nativeEvent.isComposing || options.length === 0) {
            return;
        }

        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            const step = event.key === 'ArrowDown' ? 1 : -1;
            setActiveIndex(
                (index) => (index + step + options.length) % options.length,
            );
        }

        if (event.key === 'Enter') {
            event.preventDefault();
            const option = options[Math.min(activeIndex, options.length - 1)];

            if (option.kind !== 'developer' || !option.reason) {
                openOption(option);
            }
        }
    };

    const activeOption = options[activeIndex];
    const activeOptionId = activeOption
        ? `search-${activeOption.id}`
        : undefined;

    useEffect(() => {
        if (open && activeOptionId) {
            const option = document.getElementById(activeOptionId);

            if (activeIndex === 0) {
                option?.parentElement?.scrollTo({ top: 0 });
            } else {
                option?.scrollIntoView({ block: 'nearest' });
            }
        }
    }, [open, activeOptionId, activeIndex]);

    return (
        <DialogPrimitive.Root
            open={open}
            onOpenChange={(isOpen) => (isOpen ? onOpenChange(true) : close())}
        >
            <DialogPortal>
                <DialogOverlay />
                <DialogPrimitive.Content
                    onCloseAutoFocus={onCloseAutoFocus}
                    className="fixed top-[12vh] left-1/2 z-50 w-full max-w-[calc(100%-2rem)] -translate-x-1/2 overflow-hidden rounded-lg border bg-popover text-popover-foreground shadow-[0_24px_48px_-16px_rgb(0_0_0/0.4)] duration-200 motion-safe:data-[state=closed]:animate-out motion-safe:data-[state=closed]:fade-out-0 motion-safe:data-[state=closed]:zoom-out-95 motion-safe:data-[state=open]:animate-in motion-safe:data-[state=open]:fade-in-0 motion-safe:data-[state=open]:zoom-in-95 sm:max-w-xl"
                    data-test="search"
                >
                    <DialogPrimitive.Title className="sr-only">
                        {isPicking ? pickerTitle : 'Search'}
                    </DialogPrimitive.Title>
                    <DialogPrimitive.Description className="sr-only">
                        {isPicking
                            ? 'Find a developer by name or handle to message.'
                            : 'Find developers and organizations by name or handle, or commits by what they are about.'}
                    </DialogPrimitive.Description>

                    <div className="flex items-center gap-3 border-b px-4">
                        <Search className="size-5 shrink-0 text-muted-foreground" />
                        <input
                            ref={input}
                            autoFocus
                            maxLength={100}
                            value={query}
                            onChange={(event) => type(event.target.value)}
                            onKeyDown={onKeyDown}
                            placeholder={
                                isPicking
                                    ? pickerPlaceholder
                                    : 'Search developers or commits'
                            }
                            role="combobox"
                            aria-expanded={options.length > 0}
                            aria-controls="search-results"
                            aria-activedescendant={activeOptionId}
                            aria-autocomplete="list"
                            autoComplete="off"
                            spellCheck={false}
                            className="h-14 min-w-0 flex-1 bg-transparent text-[17px] outline-none placeholder:text-muted-foreground focus-visible:outline-none"
                            data-test="search-input"
                        />
                        <Spinner
                            aria-hidden
                            className={cn(
                                'size-4 text-muted-foreground transition-opacity',
                                isLoading ? 'opacity-100' : 'opacity-0',
                            )}
                        />
                        <kbd className="hidden rounded-sm border border-b-2 bg-muted px-1.5 py-0.5 font-mono text-xs text-muted-foreground sm:block">
                            esc
                        </kbd>
                    </div>

                    {isThrottled ? (
                        <p
                            role="status"
                            className="truncate px-5 pt-4 font-mono text-[13px] text-muted-foreground"
                        >
                            error: too many searches, give it a few seconds
                        </p>
                    ) : (
                        foundNothing && (
                            <p
                                role="status"
                                className="truncate px-5 pt-4 font-mono text-[13px] text-muted-foreground"
                                title={`fatal: nothing matches '${term}'`}
                            >
                                fatal: nothing matches '{term}'
                            </p>
                        )
                    )}

                    {options.length > 0 ? (
                        <ul
                            id="search-results"
                            role="listbox"
                            aria-label="Search results"
                            className="max-h-[min(30rem,60dvh)] overflow-y-auto overscroll-contain p-2"
                        >
                            {options.map((option, index) => (
                                <ResultOption
                                    key={option.id}
                                    option={option}
                                    term={term}
                                    heading={
                                        option.kind === 'recent' &&
                                        index === 0 ? (
                                            <span className="flex items-center justify-between gap-3">
                                                {headings.recent}
                                                <button
                                                    type="button"
                                                    aria-label="Clear recent searches"
                                                    className="rounded-sm px-1.5 py-1 font-sans text-xs font-medium text-muted-foreground transition-colors hover:bg-accent hover:text-foreground focus-visible:outline-2 focus-visible:outline-primary"
                                                    onClick={() => {
                                                        if (userId !== null)
                                                            clearRecentSearches(
                                                                userId,
                                                            );
                                                        setActiveIndex(0);
                                                        input.current?.focus();
                                                    }}
                                                >
                                                    Clear
                                                </button>
                                            </span>
                                        ) : (
                                            headingBefore(options, index)
                                        )
                                    }
                                    isActive={index === activeIndex}
                                    onHover={() => setActiveIndex(index)}
                                    onOpen={close}
                                    onPick={() => openOption(option)}
                                    onPickDeveloper={
                                        onPickDeveloper &&
                                        ((user) => {
                                            close();
                                            onPickDeveloper(user);
                                        })
                                    }
                                />
                            ))}
                        </ul>
                    ) : (
                        <div
                            role="status"
                            className="px-5 py-8 text-center text-sm text-muted-foreground"
                        >
                            {isAwaitingDeveloperName
                                ? 'Type a name or handle to find a developer'
                                : 'Searching…'}
                        </div>
                    )}

                    <div className="hidden items-center gap-4 border-t bg-muted/40 px-4 py-2 text-xs text-muted-foreground sm:flex">
                        <span className="flex items-center gap-1.5">
                            <kbd className="font-mono">↑↓</kbd> to move
                        </span>
                        <span className="flex items-center gap-1.5">
                            <kbd className="font-mono">↵</kbd>{' '}
                            {isPicking ? 'to message' : 'to open'}
                        </span>
                        {!isPicking && (
                            <span className="flex items-center gap-1.5">
                                <kbd className="font-mono">@</kbd> for
                                developers
                            </span>
                        )}
                        <span className="ml-auto flex items-center gap-1.5">
                            <kbd className="font-mono">
                                {keys.combo(keys.mod, 'K')}
                            </kbd>{' '}
                            anywhere
                        </span>
                    </div>
                </DialogPrimitive.Content>
            </DialogPortal>
        </DialogPrimitive.Root>
    );
}

/**
 * Remembers which term the results belong to, so stale results never show under a newer term, and so an empty
 * result can be told apart from one that has not arrived yet.
 */
function useDebouncedSearch<T>(
    term: string,
    debounce: number,
    search: (term: string, signal: AbortSignal) => Promise<T[]>,
) {
    const [found, setFound] = useState<Found<T>>({ term: '', items: [] });
    const [isLoading, setIsLoading] = useState(false);

    useEffect(() => {
        if (term === '') {
            return;
        }

        const controller = new AbortController();
        const timeout = setTimeout(() => {
            setIsLoading(true);

            search(term, controller.signal)
                .then((items) => setFound({ term, items }))
                .catch((error: unknown) => {
                    if (controller.signal.aborted) {
                        return;
                    }

                    // A failed search counts as an empty one, so the dialog never waits on it forever.
                    setFound({
                        term,
                        items: [],
                        throttled: error instanceof TooManySearches,
                    });
                })
                .finally(() => {
                    if (!controller.signal.aborted) {
                        setIsLoading(false);
                    }
                });
        }, debounce);

        return () => {
            clearTimeout(timeout);
            controller.abort();
            setIsLoading(false);
        };
        // The search function is recreated every render; only the term should restart it.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [term, debounce]);

    return {
        isLoading,
        resultsFor: (current: string) =>
            current !== '' && current === found.term ? found.items : [],
        hasSearched: (current: string) => current === found.term,
        isThrottled: (current: string) =>
            current !== '' &&
            current === found.term &&
            found.throttled === true,
    };
}

type FoundDeveloper = { user: User; reason?: string | null };

type FoundOrganization = { organization: Organization };

async function searchRemoteCandidates(
    term: string,
    signal: AbortSignal,
): Promise<FoundDeveloper[]> {
    return searchCandidates(term, { for: 'remote' }, signal);
}

/**
 * Organizations come back with the developers, after them, so both arrive together.
 */
function searchDevelopers(
    term: string,
    signal: AbortSignal,
): Promise<Array<FoundDeveloper | FoundOrganization>> {
    return fetch(ProfileSearchController.index({ query: { q: term } }).url, {
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        signal,
    })
        .then((response) => {
            if (response.status === 429) {
                throw new TooManySearches();
            }

            if (!response.ok) {
                throw new Error(`Developer search failed: ${response.status}`);
            }

            return response.json();
        })
        .then((data: { users: User[]; organizations: Organization[] }) => [
            ...data.users.map((user) => ({ user })),
            ...data.organizations.map((organization) => ({ organization })),
        ]);
}

function optionsFor(
    intent: ReturnType<typeof searchIntent>,
    developers: FoundDeveloper[],
    organizations: FoundOrganization[],
    commits: Commit[],
    commitTerm: string,
    needsSignIn: boolean,
    actions: SearchAction[],
): Option[] {
    const actionOptions: Option[] = actions.map((action) => ({
        kind: 'action',
        id: `action-${action}`,
        action,
    }));
    const developerOptions: Option[] = developers.map(({ user, reason }) => ({
        kind: 'developer',
        id: `developer-${user.id}`,
        user,
        reason,
    }));
    const organizationOptions: Option[] = organizations.map(
        ({ organization }) => ({
            kind: 'organization',
            id: `organization-${organization.handle}`,
            organization,
        }),
    );
    const commitOptions: Option[] = commits.map((commit) => ({
        kind: 'commit',
        id: `commit-${commit.id}`,
        commit,
    }));
    const seeAll: Option[] =
        commitTerm === '' ? [] : [{ kind: 'all', id: 'all', needsSignIn }];

    if (intent === 'topic') {
        return [
            ...actionOptions,
            ...commitOptions,
            ...seeAll,
            ...developerOptions,
            ...organizationOptions,
        ];
    }

    return [
        ...actionOptions,
        ...developerOptions,
        ...organizationOptions,
        ...commitOptions,
        ...seeAll,
    ];
}

const headings: Record<Option['kind'], ReactNode> = {
    action: 'Actions',
    developer: 'Developers',
    organization: 'Organizations',
    commit: 'Most relevant commits',
    all: null,
    recent: (
        <span className="flex items-center gap-1.5">
            <History aria-hidden className="size-3.5" />
            Recent searches
        </span>
    ),
    suggestion: (
        <span className="flex items-center gap-1.5">
            <Sparkles aria-hidden className="size-3.5 text-primary" />
            Try searching for…
        </span>
    ),
};

function headingBefore(options: Option[], index: number): ReactNode {
    const kind = options[index].kind;

    if (options[index - 1]?.kind === kind) {
        return null;
    }

    return headings[kind];
}

function hrefFor(
    option: Exclude<Option, { kind: 'action' | 'suggestion' | 'recent' }>,
    term: string,
) {
    if (option.kind === 'developer') {
        return show(option.user);
    }

    if (option.kind === 'organization') {
        return show(option.organization);
    }

    if (option.kind === 'commit') {
        return CommitController.show({
            user: option.commit.author,
            commit: option.commit,
        });
    }

    return SearchController.index({ query: { q: term } });
}

function ResultOption({
    option,
    term,
    heading,
    isActive,
    onHover,
    onOpen,
    onPick,
    onPickDeveloper,
}: {
    option: Option;
    term: string;
    heading: ReactNode;
    isActive: boolean;
    onHover: () => void;
    onOpen: () => void;
    onPick: () => void;
    onPickDeveloper?: (user: User) => void;
}) {
    const className = cn(
        'flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left disabled:cursor-not-allowed',
        { 'bg-accent': isActive },
    );
    const content = (
        <>
            <OptionContent option={option} />
            <kbd
                aria-hidden
                className={cn(
                    'shrink-0 font-mono text-xs text-muted-foreground opacity-0',
                    { 'opacity-100': isActive },
                )}
            >
                ↵
            </kbd>
        </>
    );

    return (
        <>
            {heading && (
                <li
                    role="presentation"
                    className="px-3 pt-2 pb-1 font-mono text-xs text-muted-foreground first:pt-1"
                >
                    {heading}
                </li>
            )}
            <li
                id={`search-${option.id}`}
                role="option"
                aria-selected={isActive}
            >
                {option.kind === 'action' ||
                option.kind === 'suggestion' ||
                option.kind === 'recent' ||
                (option.kind === 'all' && option.needsSignIn) ||
                (option.kind === 'developer' && onPickDeveloper) ? (
                    <button
                        type="button"
                        tabIndex={-1}
                        disabled={
                            option.kind === 'developer' && !!option.reason
                        }
                        onMouseMove={onHover}
                        onClick={() =>
                            option.kind === 'developer'
                                ? onPickDeveloper?.(option.user)
                                : onPick()
                        }
                        className={className}
                    >
                        {content}
                    </button>
                ) : (
                    <Link
                        href={hrefFor(option, term)}
                        tabIndex={-1}
                        onMouseMove={onHover}
                        onClick={onOpen}
                        className={className}
                    >
                        {content}
                    </Link>
                )}
            </li>
        </>
    );
}

function OptionContent({ option }: { option: Option }): ReactNode {
    if (option.kind === 'action') {
        return <ActionResult action={option.action} />;
    }

    if (option.kind === 'developer') {
        return <DeveloperResult user={option.user} reason={option.reason} />;
    }

    if (option.kind === 'organization') {
        return <OrganizationResult organization={option.organization} />;
    }

    if (option.kind === 'commit') {
        return <CommitResult commit={option.commit} />;
    }

    if (option.kind === 'suggestion' || option.kind === 'recent') {
        return (
            <span className="flex min-w-0 flex-1 items-center justify-between gap-3 text-sm">
                <span className="truncate" title={option.term}>
                    {option.term}
                </span>
                <ArrowRight
                    aria-hidden
                    className="size-4 shrink-0 text-muted-foreground"
                />
            </span>
        );
    }

    return (
        <span className="flex min-w-0 flex-1 items-center gap-2 text-sm text-primary">
            {option.needsSignIn
                ? 'Sign in to search commits'
                : 'See all matching commits'}
            <ArrowRight aria-hidden className="size-4" />
        </span>
    );
}

const actionLabels: Record<
    SearchAction,
    { label: string; icon: LucideIcon; command?: string }
> = {
    commit: { label: 'Write a commit', icon: SquarePen },
    feature: {
        label: 'Request a feature',
        icon: Lightbulb,
        command: '/feature',
    },
    bug: { label: 'Report a bug', icon: Bug, command: '/bug' },
    'go-live': { label: 'Go live', icon: Radio },
    'end-stream': { label: 'End stream', icon: Square },
};

function ActionResult({ action }: { action: SearchAction }) {
    const { label, icon: Icon, command } = actionLabels[action];

    return (
        <span className="flex min-w-0 flex-1 items-center gap-3 text-sm">
            <Icon
                aria-hidden
                className="size-4 shrink-0 text-muted-foreground"
            />
            <span className="truncate">{label}</span>
            {command && (
                <span className="ml-auto shrink-0 font-mono text-[13px] text-muted-foreground">
                    {command}
                </span>
            )}
        </span>
    );
}

function DeveloperResult({
    user,
    reason,
}: {
    user: User;
    reason?: string | null;
}) {
    return (
        <>
            <UserAvatar
                user={user}
                className={cn('size-9 shrink-0', { 'opacity-50': reason })}
            />
            <span className={cn('min-w-0 flex-1', { 'opacity-60': reason })}>
                <span className="flex items-baseline gap-1.5">
                    <span className="truncate font-semibold">{user.name}</span>
                    <Handle user={user} />
                </span>
                {reason ? (
                    <span
                        className="block truncate font-mono text-xs text-muted-foreground"
                        title={reason}
                        data-test="candidate-reason"
                    >
                        can't push: {reason}
                    </span>
                ) : (
                    user.bio && (
                        <span className="block truncate text-sm text-muted-foreground">
                            {user.bio}
                        </span>
                    )
                )}
            </span>
        </>
    );
}

function OrganizationResult({ organization }: { organization: Organization }) {
    return (
        <>
            <OrganizationAvatar
                organization={organization}
                className="size-9 shrink-0"
            />
            <span className="min-w-0 flex-1">
                <span className="flex items-baseline gap-1.5">
                    <span
                        className="truncate font-semibold"
                        title={organization.name}
                    >
                        {organization.name}
                    </span>
                    <span
                        className="min-w-0 shrink truncate font-mono text-[13px] text-muted-foreground"
                        title={`@${organization.handle}`}
                    >
                        @{organization.handle}
                    </span>
                </span>
                {organization.bio && (
                    <span
                        className="block truncate text-sm text-muted-foreground"
                        title={organization.bio}
                    >
                        {organization.bio}
                    </span>
                )}
            </span>
        </>
    );
}

function CommitResult({ commit }: { commit: Commit }) {
    const now = useNow();

    return (
        <>
            <UserAvatar
                user={commit.author}
                className="size-9 shrink-0 self-start"
            />
            <span className="min-w-0 flex-1">
                <span className="flex items-baseline gap-1.5 text-sm">
                    <Handle user={commit.author} />
                    <span aria-hidden className="text-muted-foreground">
                        ·
                    </span>
                    <time
                        dateTime={commit.created_at}
                        className="shrink-0 font-mono text-[13px] text-muted-foreground"
                    >
                        {formatRelativeTime(commit.created_at, now)}
                    </time>
                </span>
                <span
                    className="line-clamp-2 text-sm text-pretty"
                    title={commit.body}
                >
                    {commit.body}
                </span>
            </span>
        </>
    );
}

function Handle({ user }: { user: User }) {
    return (
        <span className="flex min-w-0 shrink items-center gap-1 font-mono text-[13px] text-muted-foreground">
            <span
                aria-hidden
                className="size-1.5 shrink-0 rounded-full"
                style={{ background: laneColor(user.lane) }}
            />
            <span className="truncate">@{user.handle}</span>
        </span>
    );
}
