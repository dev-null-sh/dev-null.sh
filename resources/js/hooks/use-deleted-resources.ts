import { usePage } from '@inertiajs/react';
import { useSyncExternalStore } from 'react';
import { toast } from 'sonner';
import type { DeletedPage } from '@/components/deleted-page-dialog';
import type { CommitPhoto } from '@/lib/commit-photo';
import { deletedResources } from '@/lib/deleted-resources';
import type { Commit } from '@/types';

export function useIsCommitDeleted(id: string | null): boolean {
    return useSyncExternalStore(
        deletedResources.subscribe,
        () => id !== null && deletedResources.hasCommit(id),
        () => false,
    );
}

export function useIsPageDeleted(url: string): boolean {
    return useSyncExternalStore(
        deletedResources.subscribe,
        () => deletedResources.hasPage(url),
        () => false,
    );
}

/**
 * The commit the page is about: the photo open on top of it, or the commit it shows.
 */
function usePageCommit(): { id: string | null; isPhoto: boolean } {
    const { component, props } = usePage<{
        commit?: Commit;
        photo?: CommitPhoto;
    }>();

    if (props.photo) {
        return { id: props.photo.commit.id, isPhoto: true };
    }

    return {
        id: component === 'commit/show' ? (props.commit?.id ?? null) : null,
        isPhoto: false,
    };
}

/**
 * The page's own commit when there is one, otherwise the page itself.
 */
export function usePageDeletion(): {
    deletedPage: DeletedPage | null;
    markPageDeleted: () => void;
} {
    const path = usePage().url.split('?')[0];
    const { id: commitId, isPhoto } = usePageCommit();
    const isCommitDeleted = useIsCommitDeleted(commitId);
    const isPageDeleted = useIsPageDeleted(path);

    const deletedPage: DeletedPage | null =
        commitId !== null
            ? isCommitDeleted
                ? { type: isPhoto ? 'photo' : 'commit', id: commitId }
                : null
            : isPageDeleted
              ? { type: 'page', path }
              : null;

    return {
        deletedPage,
        markPageDeleted: () =>
            commitId !== null
                ? deletedResources.markCommit(commitId)
                : deletedResources.markPage(path),
    };
}

/**
 * Marks a commit an action found deleted, turning its actions off for good. A commit elsewhere on the page says why in
 * a toast, while the page's own commit is covered by the deleted page dialog.
 */
export function useMarkCommitDeleted(): (commitId: string) => void {
    const { id: pageCommitId } = usePageCommit();

    return (commitId) => {
        if (
            commitId !== pageCommitId &&
            !deletedResources.hasCommit(commitId)
        ) {
            toast.error('This commit is no longer available.');
        }

        deletedResources.markCommit(commitId);
    };
}

/**
 * Actions on a commit 404 once it was deleted, or once it is no longer visible, as when it was rejected or its author
 * blocked the viewer: the two can't be told apart.
 */
export function useCommitNotFound(): (
    commitId: string,
    response: { status: number },
) => boolean {
    const markCommitDeleted = useMarkCommitDeleted();

    return (commitId, { status }) => {
        if (status !== 404) {
            return false;
        }

        markCommitDeleted(commitId);

        return true;
    };
}
