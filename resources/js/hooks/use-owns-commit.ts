import { usePage } from '@inertiajs/react';

/**
 * Whether the signed-in user owns a commit: one from them, or from an organization they can post as.
 */
export function useOwnsCommit(): (commit: {
    author: { id: string };
}) => boolean {
    const { auth } = usePage().props;

    return (commit) =>
        commit.author.id === auth.user?.id ||
        auth.organizations.some(
            (organization) => organization.id === commit.author.id,
        );
}
