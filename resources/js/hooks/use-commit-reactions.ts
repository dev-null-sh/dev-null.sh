import { useEffect, useSyncExternalStore } from 'react';
import { createReactionCache } from '@/lib/commit-reaction-cache';
import type { ReactionState } from '@/lib/commit-reaction-cache';
import { readStorage, writeStorage } from '@/lib/storage';

export const commitReactionCache = createReactionCache(
    { read: readStorage, write: writeStorage },
    async (commitId) => {
        const response = await fetch(`/commits/${commitId}/my-reaction`, {
            headers: { Accept: 'application/json' },
            signal: AbortSignal.timeout(10_000),
        });
        if (!response.ok) throw new Error('Could not refresh reactions');
        return response.json() as Promise<ReactionState>;
    },
);

export function useCommitReactions(
    userId: string | undefined,
    commitId: string,
    fallback: ReactionState,
) {
    const cached = useSyncExternalStore(
        (listener) => commitReactionCache.subscribe(listener),
        () => (userId ? commitReactionCache.read(userId, commitId) : undefined),
        () => undefined,
    );
    useEffect(() => {
        if (!userId) return;
        const refresh = () => {
            void commitReactionCache.refresh(userId, commitId);
        };
        refresh();
        window.addEventListener('focus', refresh);
        window.addEventListener('online', refresh);
        return () => {
            window.removeEventListener('focus', refresh);
            window.removeEventListener('online', refresh);
        };
    }, [userId, commitId]);
    return cached ?? fallback;
}
