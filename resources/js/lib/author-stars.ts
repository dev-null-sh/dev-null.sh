import { refreshSharedState } from '@/hooks/use-shared-state';

export type AuthorStars = {
    user_id: string;
    total: number;
    counted_at: number;
};

const countedAt = new Map<string, number>();

export const authorStarsKey = (userId: string): string =>
    `stars_received:${userId}`;

/**
 * Shows an author's star total unless a newer count is already shown.
 * Every count carries the database time it was taken, so one that arrives late never replaces a fresher one.
 */
export function showAuthorStars(stars: AuthorStars): void {
    if (stars.counted_at <= (countedAt.get(stars.user_id) ?? -Infinity)) {
        return;
    }

    countedAt.set(stars.user_id, stars.counted_at);
    refreshSharedState(new Map([[authorStarsKey(stars.user_id), stars.total]]));
}
