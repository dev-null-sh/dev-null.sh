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
    const timestamp = (commit: Commit): string => {
        if (log === 'trending') {
            return commit.created_at;
        }

        return commit.approved_at ?? commit.created_at;
    };
    const newestAt = commits[0] ? timestamp(commits[0]) : undefined;

    useEffect(() => {
        if (newestAt !== undefined) {
            writeStorage(key, newestAt);
        }
    }, [key, newestAt]);

    if (lastVisitAt === '') {
        return null;
    }

    const firstSeen = commits.findIndex(
        (commit) => Date.parse(timestamp(commit)) <= Date.parse(lastVisitAt),
    );
    const isNewFromOthers = commits
        .slice(0, Math.max(firstSeen, 0))
        .some((commit) => commit.user_id !== userId);

    return isNewFromOthers ? commits[firstSeen].id : null;
}
