import { usePage } from '@inertiajs/react';
import { Search, X } from 'lucide-react';
import type { ReactNode } from 'react';
import { useEffect, useRef, useState } from 'react';
import {
    ConversationList,
    ConversationListSkeleton,
} from '@/components/chat/conversation-list';
import { CandidateRow } from '@/components/chat/org';
import { useChat } from '@/hooks/use-chat';
import {
    loadMoreConversations,
    loadTab,
    searchCandidates,
    searchConversations,
} from '@/lib/chat';
import type { Candidate, ChatTarget } from '@/lib/chat';
import { conversationsMatching } from '@/lib/conversation-search';
import { cn } from '@/lib/utils';
import type { ConversationSummary, InboxTab, User } from '@/types';

type Tab = InboxTab;

const tabs: { name: Tab; label: string }[] = [
    { name: 'open', label: 'Open' },
    { name: 'fetched', label: 'Fetched' },
    { name: 'stashed', label: 'Stashed' },
];

type Found = {
    term: string;
    tab: Tab;
    conversations: ConversationSummary[];
    developers: Candidate[];
};

const debounce = 150;

/**
 * The inbox with its search, its fetched requests, and its stash, shared by the Remotes page and the dock. Each tab
 * loads more as it scrolls to the end, so long inboxes stay quick.
 */
export function InboxPanel({
    user,
    activeKey,
    onOpen,
    onLeaveActive,
    onBlocked,
    isCompact = false,
    empty,
}: {
    user: User;
    activeKey?: string | null;
    onOpen: (target: ChatTarget) => void;
    onLeaveActive?: () => void;
    onBlocked?: () => void;
    isCompact?: boolean;
    empty: ReactNode;
}) {
    const {
        conversations,
        fetched,
        archived,
        hasMore,
        isLoadingMore,
        requests,
    } = useChat();
    const { auth } = usePage().props;
    const visibleTabs = tabs.filter(
        ({ name }) => name !== 'fetched' || auth.remotes?.requests === true,
    );
    const [tab, setTab] = useState<Tab>('open');
    const end = useRef<HTMLDivElement>(null);
    const [query, setQuery] = useState('');
    const [found, setFound] = useState<Found | null>(null);
    const term = query.trim().replace(/^@+/u, '');

    useEffect(() => {
        if (term === '') {
            return;
        }

        const controller = new AbortController();
        const timeout = window.setTimeout(() => {
            void Promise.all([
                searchConversations(term, tab, controller.signal),
                tab === 'open'
                    ? searchCandidates(
                          term,
                          { for: 'remote' },
                          controller.signal,
                      ).then((candidates) => candidates.slice(0, 5))
                    : Promise.resolve([]),
            ])
                .then(([matches, developers]) =>
                    setFound({
                        term,
                        tab,
                        conversations: matches,
                        developers,
                    }),
                )
                .catch(() => undefined);
        }, debounce);

        return () => {
            window.clearTimeout(timeout);
            controller.abort();
        };
        // Stashing, merging, blocking, and new messages change what matches, so the search follows the inbox.
    }, [term, tab, user.id, conversations, fetched, archived]);

    const isSearching = term !== '';

    useEffect(() => {
        const element = end.current;

        if (element === null || isSearching || !hasMore[tab]) {
            return;
        }

        const observer = new IntersectionObserver(
            ([entry]) => {
                if (entry?.isIntersecting) {
                    void loadMoreConversations(tab);
                }
            },
            { rootMargin: '200px' },
        );

        observer.observe(element);

        return () => observer.disconnect();
    }, [tab, isSearching, hasMore]);

    const showTab = (next: Tab) => {
        setTab(next);

        if (next !== 'open') {
            void loadTab(next);
        }
    };

    const loaded = { open: conversations, fetched, stashed: archived }[tab];
    const earlier = found?.tab === tab ? found : null;
    const results = earlier?.term === term ? earlier : null;
    const list =
        !isSearching || (loaded === null && earlier === null)
            ? loaded
            : conversationsMatching(
                  term,
                  [...(loaded ?? []), ...(earlier?.conversations ?? [])],
                  results?.conversations,
              );
    const developers = (results?.developers ?? []).filter(
        (developer) =>
            !list?.some(
                (conversation) =>
                    conversation.partner?.id === developer.user.id,
            ),
    );

    return (
        <div className="flex min-h-0 flex-1 flex-col">
            <div
                className={cn(
                    'shrink-0 space-y-2',
                    isCompact ? 'px-3 pb-2' : 'px-4 pb-2',
                )}
            >
                <label className="relative flex items-center">
                    <span className="sr-only">Search conversations</span>
                    <Search
                        aria-hidden
                        className="pointer-events-none absolute left-3 size-4 text-muted-foreground"
                    />
                    <input
                        type="search"
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        onKeyDown={(event) => {
                            if (event.key === 'Escape' && query !== '') {
                                event.stopPropagation();
                                setQuery('');
                            }
                        }}
                        maxLength={100}
                        placeholder="Search names or @handles"
                        autoComplete="off"
                        spellCheck={false}
                        className={cn(
                            'w-full min-w-0 rounded-md border bg-card px-9 text-base outline-none placeholder:text-muted-foreground focus-visible:border-primary focus-visible:outline-none md:text-sm [&::-webkit-search-cancel-button]:hidden',
                            isCompact ? 'h-8' : 'h-9',
                        )}
                        data-test="conversation-search"
                    />
                    {query !== '' && (
                        <button
                            type="button"
                            aria-label="Clear search"
                            onClick={() => setQuery('')}
                            className="absolute right-2 grid size-6 place-items-center rounded-md text-muted-foreground hover:bg-accent hover:text-foreground"
                        >
                            <X className="size-3.5" />
                        </button>
                    )}
                </label>
            </div>

            <div
                role="tablist"
                aria-label="Conversations"
                className="flex shrink-0 border-b"
            >
                {visibleTabs.map(({ name, label }) => (
                    <button
                        key={name}
                        type="button"
                        role="tab"
                        aria-selected={tab === name}
                        onClick={() => showTab(name)}
                        className={cn(
                            'relative flex flex-1 justify-center font-medium text-muted-foreground transition-colors hover:bg-accent hover:text-foreground focus-visible:-outline-offset-2',
                            isCompact ? 'text-sm' : 'text-[15px]',
                            { 'text-foreground': tab === name },
                        )}
                        data-test={`conversation-tab-${name}`}
                    >
                        <span className={cn(isCompact ? 'py-2.5' : 'py-3')}>
                            {label}
                            {name === 'fetched' && (requests ?? 0) > 0 && (
                                <span
                                    title={`${requests} waiting`}
                                    className="ml-1.5 inline-flex min-w-5 justify-center rounded-sm bg-primary px-1.5 font-mono text-[11px] leading-5 text-primary-foreground tabular-nums"
                                >
                                    <span aria-hidden>
                                        {(requests ?? 0) > 99
                                            ? '99+'
                                            : requests}
                                    </span>
                                    <span className="sr-only">
                                        {requests} waiting
                                    </span>
                                </span>
                            )}
                        </span>
                        {tab === name && (
                            <span
                                aria-hidden
                                className="absolute inset-x-0 bottom-0 h-[3px] bg-primary"
                            />
                        )}
                    </button>
                ))}
            </div>

            <div
                role="tabpanel"
                className="min-h-0 flex-1 scrollbar-thin overflow-y-auto overscroll-contain"
            >
                <ConversationList
                    conversations={list}
                    user={user}
                    activeKey={activeKey}
                    onOpen={onOpen}
                    onLeaveActive={onLeaveActive}
                    onBlocked={onBlocked}
                    isCompact={isCompact}
                    empty={
                        isSearching ? (
                            results !== null &&
                            developers.length === 0 && (
                                <p
                                    className="truncate px-4 py-8 text-center font-mono text-[13px] text-muted-foreground"
                                    title={`fatal: nothing matches '${term}'`}
                                >
                                    fatal: nothing matches '{term}'
                                </p>
                            )
                        ) : tab === 'stashed' ? (
                            <p className="px-4 py-10 text-center font-mono text-[13px] text-muted-foreground">
                                nothing stashed
                            </p>
                        ) : tab === 'fetched' ? (
                            <p className="px-4 py-10 text-center font-mono text-[13px] text-muted-foreground">
                                no requests fetched
                            </p>
                        ) : (
                            empty
                        )
                    }
                />

                {isSearching && results === null && list !== null && (
                    <ConversationListSkeleton isCompact={isCompact} />
                )}

                {!isSearching && (
                    <div
                        ref={end}
                        aria-hidden
                        className="flex h-10 items-center justify-center"
                    >
                        {isLoadingMore[tab] && (
                            <span className="size-4 animate-spin rounded-full border-2 border-muted-foreground/40 border-t-transparent" />
                        )}
                    </div>
                )}

                {developers.length > 0 && (
                    <section aria-label="Start a conversation">
                        <h3 className="px-4 pt-3 pb-1 font-mono text-xs text-muted-foreground">
                            Start a conversation
                        </h3>
                        <ul>
                            {developers.map(({ user: developer, reason }) => (
                                <li key={developer.id}>
                                    <CandidateRow
                                        user={developer}
                                        reason={reason}
                                        onToggle={() =>
                                            onOpen({
                                                kind: 'direct',
                                                partner: developer,
                                            })
                                        }
                                    />
                                </li>
                            ))}
                        </ul>
                    </section>
                )}
            </div>
        </div>
    );
}
