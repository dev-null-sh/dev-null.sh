import type { Commit, DeletedCommit } from '@/types';

// Keep loaded feed pages and cursors; moderation reloads its queue.
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
            'continuation',
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

export function restoreFeedCommit(
    rows: Commit[],
    restored: Commit,
    order: 'latest' | 'ranked' | 'starred',
): Commit[] {
    const updated = updateQuotedCommits(rows, restored).filter(
        (commit) => commit.id !== restored.id,
    );
    const compare = (a: Commit, b: Commit) => {
        if (order === 'starred') {
            return (
                (b.starred_order ?? '').localeCompare(a.starred_order ?? '') ||
                b.id.localeCompare(a.id)
            );
        }
        if (order === 'ranked') {
            return b.hot_score - a.hot_score || b.id.localeCompare(a.id);
        }
        return (
            b.created_at.localeCompare(a.created_at) || b.id.localeCompare(a.id)
        );
    };
    const index = updated.findIndex((commit) => compare(restored, commit) < 0);
    updated.splice(index === -1 ? updated.length : index, 0, restored);
    return updated;
}
