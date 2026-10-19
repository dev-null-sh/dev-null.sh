export type ReactionDestinations = { public: boolean; repositories: string[] };
export type ReactionRepository = {
    id: string;
    repository: string;
    private: boolean;
};
export type ReactionState = {
    reactions: ({ emoji: string } & ReactionDestinations)[];
    repositories: ReactionRepository[];
};

type Entry = {
    state?: ReactionState;
    version: number;
    pending: boolean;
    checked: number;
    loading?: Promise<void>;
};

/** Account-scoped, stale-while-revalidate storage. Reads and clicks never await a GET. */
export function createReactionCache(
    storage: {
        read: (key: string) => string;
        write: (key: string, value: string) => void;
    },
    request: (commitId: string) => Promise<ReactionState>,
) {
    const entries = new Map<string, Entry>();
    const listeners = new Set<() => void>();
    const keyFor = (userId: string, commitId: string) =>
        `commit-reactions:v1:${userId}:${commitId}`;
    const entryFor = (key: string): Entry => {
        let entry = entries.get(key);
        if (!entry) {
            entry = { version: 0, pending: false, checked: 0 };
            try {
                const saved = JSON.parse(storage.read(key)) as ReactionState;
                if (
                    Array.isArray(saved.reactions) &&
                    Array.isArray(saved.repositories)
                )
                    entry.state = saved;
            } catch {
                /* An empty or unavailable cache falls back to page data. */
            }
            entries.set(key, entry);
        }
        return entry;
    };
    const save = (key: string, entry: Entry, state: ReactionState) => {
        entry.state = state;
        storage.write(key, JSON.stringify(state));
        listeners.forEach((listener) => listener());
    };
    return {
        subscribe(listener: () => void) {
            listeners.add(listener);
            return () => {
                listeners.delete(listener);
            };
        },
        read(userId: string, commitId: string) {
            return entryFor(keyFor(userId, commitId)).state;
        },
        refresh(userId: string, commitId: string): Promise<void> {
            const key = keyFor(userId, commitId);
            const entry = entryFor(key);
            if (entry.pending || Date.now() - entry.checked < 30_000)
                return Promise.resolve();
            if (entry.loading) return entry.loading;
            const version = entry.version;
            entry.loading = request(commitId)
                .then((state) => {
                    // A request started before a click must never undo that click.
                    if (entry.version !== version) return;
                    entry.checked = Date.now();
                    save(key, entry, state);
                })
                .catch(() => {
                    /* Keep the usable cached state on network failure. */
                })
                .finally(() => {
                    entry.loading = undefined;
                });
            return entry.loading;
        },
        begin(
            userId: string,
            commitId: string,
            fallback: ReactionState,
            optimistic?: {
                emoji?: string;
                destinations?: ReactionDestinations;
            },
        ) {
            const key = keyFor(userId, commitId);
            const entry = entryFor(key);
            entry.version++;
            const previous = entry.state ?? fallback;
            entry.pending = true;
            const apply = (change: {
                emoji?: string;
                destinations?: ReactionDestinations;
            }) => {
                const remaining = change.emoji
                    ? previous.reactions.filter((r) => r.emoji !== change.emoji)
                    : [];
                save(key, entry, {
                    ...previous,
                    reactions:
                        change.emoji && change.destinations
                            ? [
                                  {
                                      emoji: change.emoji,
                                      ...change.destinations,
                                  },
                                  ...remaining,
                              ]
                            : remaining,
                });
            };
            if (optimistic) apply(optimistic);
            return (change?: {
                emoji?: string;
                destinations?: ReactionDestinations;
            }) => {
                entry.pending = false;
                if (change) apply(change);
                else if (optimistic) save(key, entry, previous);
                entry.checked = 0;
            };
        },
    };
}
