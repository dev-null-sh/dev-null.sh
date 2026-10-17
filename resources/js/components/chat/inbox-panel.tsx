import { Search, X } from 'lucide-react';
import type { ReactNode } from 'react';
import { useEffect, useState } from 'react';
import UserSearchController from '@/actions/App/Http/Controllers/UserSearchController';
import { ConversationList } from '@/components/chat/conversation-list';
import { UserAvatar } from '@/components/user-avatar';
import { useChat } from '@/hooks/use-chat';
import { loadArchived, searchConversations } from '@/lib/chat';
import { cn, laneColor } from '@/lib/utils';
import type { ConversationSummary, User } from '@/types';

type Tab = 'messages' | 'archived';

type Found = {
    term: string;
    tab: Tab;
    conversations: ConversationSummary[];
    developers: User[];
};

const debounce = 150;

/**
 * The inbox with its search and its archive, shared by the Remotes page and the dock.
 */
export function InboxPanel({
    user,
    activeUsername,
    onOpen,
    onLeaveActive,
    onBlocked,
    isCompact = false,
    empty,
}: {
    user: User;
    activeUsername?: string | null;
    onOpen: (partner: User) => void;
    onLeaveActive?: () => void;
    onBlocked?: () => void;
    isCompact?: boolean;
    empty: ReactNode;
}) {
    const { conversations, archived } = useChat();
    const [tab, setTab] = useState<Tab>('messages');
    const [query, setQuery] = useState('');
    const [found, setFound] = useState<Found | null>(null);
    const term = query.trim().replace(/^@/u, '');

    useEffect(() => {
        if (term === '') {
            return;
        }

        const controller = new AbortController();
        const timeout = window.setTimeout(() => {
            void Promise.all([
                searchConversations(
                    term,
                    tab === 'archived',
                    controller.signal,
                ),
                tab === 'messages'
                    ? searchDevelopers(term, controller.signal)
                    : Promise.resolve([]),
            ])
                .then(([matches, developers]) =>
                    setFound({
                        term,
                        tab,
                        conversations: matches,
                        developers: developers.filter(
                            (developer) =>
                                developer.id !== user.id &&
                                !matches.some(
                                    (match) =>
                                        match.partner.id === developer.id,
                                ),
                        ),
                    }),
                )
                .catch(() => undefined);
        }, debounce);

        return () => {
            window.clearTimeout(timeout);
            controller.abort();
        };
        // Archiving, blocking, and new messages change what matches, so the search follows the inbox.
    }, [term, tab, user.id, conversations, archived]);

    const showTab = (next: Tab) => {
        setTab(next);

        if (next === 'archived') {
            void loadArchived();
        }
    };

    const results =
        found !== null && found.term === term && found.tab === tab
            ? found
            : null;
    const isSearching = term !== '';
    const list = isSearching
        ? (results?.conversations ?? null)
        : tab === 'archived'
          ? archived
          : conversations;

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
                            'w-full min-w-0 rounded-full border bg-card pr-9 pl-9 text-base outline-none placeholder:text-muted-foreground focus-visible:border-primary focus-visible:outline-none md:text-sm [&::-webkit-search-cancel-button]:hidden',
                            isCompact ? 'h-8' : 'h-9',
                        )}
                        data-test="conversation-search"
                    />
                    {query !== '' && (
                        <button
                            type="button"
                            aria-label="Clear search"
                            onClick={() => setQuery('')}
                            className="absolute right-2 grid size-6 place-items-center rounded-full text-muted-foreground hover:bg-accent hover:text-foreground"
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
                {(['messages', 'archived'] as const).map((name) => (
                    <button
                        key={name}
                        type="button"
                        role="tab"
                        aria-selected={tab === name}
                        onClick={() => showTab(name)}
                        className={cn(
                            'flex flex-1 justify-center font-medium text-muted-foreground transition-colors hover:bg-accent hover:text-foreground focus-visible:-outline-offset-2',
                            isCompact ? 'text-sm' : 'text-[15px]',
                            { 'text-foreground': tab === name },
                        )}
                        data-test={`conversation-tab-${name}`}
                    >
                        <span
                            className={cn(
                                'relative',
                                isCompact ? 'py-2.5' : 'py-3',
                            )}
                        >
                            {name === 'messages' ? 'Open' : 'Stashed'}
                            {tab === name && (
                                <span
                                    aria-hidden
                                    className="absolute inset-x-0 bottom-0 h-[3px] rounded-full bg-primary"
                                />
                            )}
                        </span>
                    </button>
                ))}
            </div>

            <div
                role="tabpanel"
                className="min-h-0 flex-1 [scrollbar-width:thin] overflow-y-auto overscroll-contain"
            >
                <ConversationList
                    conversations={list}
                    user={user}
                    activeUsername={activeUsername}
                    onOpen={onOpen}
                    onLeaveActive={onLeaveActive}
                    onBlocked={onBlocked}
                    isCompact={isCompact}
                    empty={
                        isSearching ? (
                            results !== null &&
                            results.developers.length === 0 && (
                                <p
                                    className="truncate px-4 py-8 text-center font-mono text-[13px] text-muted-foreground"
                                    title={`fatal: nothing matches '${term}'`}
                                >
                                    fatal: nothing matches '{term}'
                                </p>
                            )
                        ) : tab === 'archived' ? (
                            <p className="px-4 py-10 text-center font-mono text-[13px] text-muted-foreground">
                                nothing stashed
                            </p>
                        ) : (
                            empty
                        )
                    }
                />

                {isSearching &&
                    results !== null &&
                    results.developers.length > 0 && (
                        <section aria-label="Start a conversation">
                            <h3 className="px-4 pt-3 pb-1 font-mono text-xs text-muted-foreground">
                                Start a conversation
                            </h3>
                            <ul>
                                {results.developers.map((developer) => (
                                    <li key={developer.id}>
                                        <button
                                            type="button"
                                            onClick={() => onOpen(developer)}
                                            className={cn(
                                                'flex w-full items-center gap-3 text-left transition-colors hover:bg-accent',
                                                isCompact
                                                    ? 'px-3.5 py-2'
                                                    : 'px-4 py-2.5',
                                            )}
                                        >
                                            <UserAvatar
                                                user={developer}
                                                className={
                                                    isCompact
                                                        ? 'size-8'
                                                        : 'size-9'
                                                }
                                            />
                                            <span className="min-w-0">
                                                <span className="block truncate text-sm font-semibold">
                                                    {developer.name}
                                                </span>
                                                <span className="flex min-w-0 items-center gap-1 font-mono text-xs text-muted-foreground">
                                                    <span
                                                        aria-hidden
                                                        className="size-1.5 shrink-0 rounded-full"
                                                        style={{
                                                            background:
                                                                laneColor(
                                                                    developer.lane,
                                                                ),
                                                        }}
                                                    />
                                                    <span className="truncate">
                                                        @{developer.username}
                                                    </span>
                                                </span>
                                            </span>
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        </section>
                    )}
            </div>
        </div>
    );
}

async function searchDevelopers(
    term: string,
    signal: AbortSignal,
): Promise<User[]> {
    const response = await fetch(
        UserSearchController.index.url({ query: { q: term } }),
        {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            signal,
        },
    );

    if (!response.ok) {
        return [];
    }

    return ((await response.json()) as { users: User[] }).users.slice(0, 5);
}
