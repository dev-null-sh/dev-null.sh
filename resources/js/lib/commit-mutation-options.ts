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
            'commit',
            'pinnedCommit',
            'ancestors',
            'series',
            'hasUnmoderatedCommits',
            ...(component === 'moderation/show' ? ['commits'] : []),
        ],
    };
}
