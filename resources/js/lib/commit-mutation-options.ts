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

// The Commits tab shows a pin in its own card, so a restored pin stays out of that list. Shipped has no
// pin card, and only a commit marked shipped belongs there. An organization's Mentions tab lists replies
// too, whatever handle they mention it with.
export function restoredCommitBelongsInFeed(
    restored: Commit,
    feed: {
        isStarred: boolean;
        isProfileReplies: boolean;
        isProfileShipped: boolean;
        profileUserId: string | null;
        mentionedOrganizationId: string | null;
    },
): boolean {
    const matchesKind = feed.isStarred
        ? restored.starred === true
        : feed.mentionedOrganizationId !== null
          ? Object.values(restored.organization_mentions ?? {}).includes(
                feed.mentionedOrganizationId,
            )
          : feed.isProfileReplies
            ? restored.parent_id !== null
            : restored.parent_id === null;
    const matchesProfile =
        feed.profileUserId === null ||
        (restored.user_id === feed.profileUserId &&
            (feed.isProfileShipped || !restored.is_pinned));

    return (
        matchesKind &&
        matchesProfile &&
        (!feed.isProfileShipped || restored.shipped === true)
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
