import type { Commit, DeletedCommit } from '@/types';

// Keep loaded feed pages and cursors; moderation must regroup the remaining series parts.
export function commitMutationOptions(component: string) {
    return {
        preserveScroll: true,
        preserveState: true,
        reset: component === 'moderation/show' ? ['commits'] : [],
        only: [
            'auth',
            'user',
            'activity',
            'streaks',
            'achievements',
            'commit',
            'pinnedCommit',
            'ancestors',
            'series',
            'hasUnmoderatedCommits',
            ...(component === 'moderation/show' ? ['commits'] : []),
        ],
    };
}

export function updateQuotedCommits(
    rows: Commit[],
    changed: Commit | DeletedCommit,
): Commit[] {
    return rows.map((commit) =>
        commit.quoted_commit_id === changed.id
            ? { ...commit, quoted_commit: changed }
            : commit,
    );
}
