import { adjustSharedState } from '@/hooks/use-shared-state';
import { commitStateKey } from '@/lib/commit-state';

/**
 * Keeps an author's profile star total in step with a star or unstar of one of their commits.
 * It only ever adds how much that commit's count changed, so responses that arrive out of order still add up.
 */
export function changeAuthorStars(authorId: string | null, starring: boolean) {
    const key =
        authorId === null ? null : commitStateKey('stars_received', authorId);
    const optimistic = starring ? 1 : -1;
    const adjust = (delta: number) => {
        if (key !== null) {
            adjustSharedState(key, delta);
        }
    };

    adjust(optimistic);

    return {
        settle: (before: number, after: number) =>
            adjust(after - before - optimistic),
        revert: () => adjust(-optimistic),
    };
}
