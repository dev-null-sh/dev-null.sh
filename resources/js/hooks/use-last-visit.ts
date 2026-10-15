import { useEffect, useState } from 'react';
import { readStorage, writeStorage } from '@/lib/storage';
import type { Commit, Log } from '@/types';

const keyFor = (userId: string, log: Log): string =>
    `last-visit:${userId}:${log}`;

export function useLastVisit(
    userId: string,
    log: Log,
    commits: Commit[],
): string | null {
    const key = keyFor(userId, log);
    const [lastVisitAt] = useState(() => readStorage(key));
    const newestAt = commits[0]?.created_at;

    useEffect(() => {
        if (newestAt !== undefined) {
            writeStorage(key, newestAt);
        }
    }, [key, newestAt]);

    if (lastVisitAt === '') {
        return null;
    }

    const firstSeen = commits.findIndex(
        (commit) => Date.parse(commit.created_at) <= Date.parse(lastVisitAt),
    );
    const isNewFromOthers = commits
        .slice(0, Math.max(firstSeen, 0))
        .some((commit) => commit.user_id !== userId);

    return isNewFromOthers ? commits[firstSeen].id : null;
}
