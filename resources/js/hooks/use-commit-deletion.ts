import { usePage } from '@inertiajs/react';
import { createContext, useContext } from 'react';
import type { Commit } from '@/types';

/**
 * The repo the viewer maintains on the page, whose commits they can delete whoever wrote them.
 */
export const MaintainedRepoContext = createContext<string | null>(null);

export function useIsDeletable(
    commit: Pick<Commit, 'user_id' | 'repo_id'>,
): boolean {
    const { auth } = usePage().props;
    const maintainedRepoId = useContext(MaintainedRepoContext);

    return (
        commit.user_id === auth.user?.id ||
        (!!commit.repo_id && commit.repo_id === maintainedRepoId)
    );
}
