// Keep loaded feed pages and cursors; the deletion/restoration flash updates its rows.
export const commitMutationOptions = {
    preserveScroll: true,
    preserveState: true,
    only: [
        'auth',
        'user',
        'activity',
        'streaks',
        'commit',
        'ancestors',
        'hasUnmoderatedCommits',
    ],
};
