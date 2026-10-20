import type { SearchIntent } from '@/lib/search-intent';
import type { Commit, User } from '@/types';

export type FoundDeveloper = { user: User; reason?: string | null };

export type Option =
    | { kind: 'developer'; id: string; user: User; reason?: string | null }
    | { kind: 'commit'; id: string; commit: Commit }
    | { kind: 'all'; id: string }
    | { kind: 'suggestion'; id: string; term: string };

/**
 * A picked option stays highlighted when results arrive in front of it. With none picked, or once it is gone, the
 * first option is.
 */
export function activeIndexFor(
    options: Option[],
    activeId: string | null,
): number {
    return Math.max(
        0,
        options.findIndex((option) => option.id === activeId),
    );
}

export function optionsFor(
    intent: SearchIntent,
    developers: FoundDeveloper[],
    commits: Commit[],
    commitTerm: string,
): Option[] {
    const developerOptions: Option[] = developers.map(({ user, reason }) => ({
        kind: 'developer',
        id: `developer-${user.id}`,
        user,
        reason,
    }));
    const commitOptions: Option[] = commits.map((commit) => ({
        kind: 'commit',
        id: `commit-${commit.id}`,
        commit,
    }));
    const seeAll: Option[] =
        commitTerm === '' ? [] : [{ kind: 'all', id: 'all' }];

    if (intent === 'topic') {
        return [...commitOptions, ...seeAll, ...developerOptions];
    }

    return [...developerOptions, ...commitOptions, ...seeAll];
}
