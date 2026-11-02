import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { setCookie } from '@/hooks/use-appearance';

export function currentRound(): number {
    const match = document.cookie.match(/(?:^|; )who_to_follow_round=(\d+)/);

    return match ? Number(match[1]) : 0;
}

/**
 * Whether to suggest people to follow is kept in a cookie, like the theme, so the server can skip the suggestions too.
 */
export function useWhoToFollow() {
    const { showsWhoToFollow } = usePage().props;
    const [isRefreshing, setIsRefreshing] = useState(false);

    const rememberShowsWhoToFollow = (shows: boolean): void => {
        setCookie('who_to_follow', String(shows));
        router.flushAll();
    };

    const setShowsWhoToFollow = (shows: boolean): void => {
        rememberShowsWhoToFollow(shows);
        router.reload({
            only: ['showsWhoToFollow', 'suggestedUsers'],
            reset: ['suggestedUsers'],
            viewTransition: true,
        });
    };

    /**
     * Each refresh moves to the next page of today's picks. The old suggestions stay until the next ones arrive.
     */
    const refreshSuggestedUsers = (): void => {
        if (isRefreshing) {
            return;
        }

        setCookie('who_to_follow_round', String(currentRound() + 1), 1);
        router.reload({
            only: ['suggestedUsers'],
            reset: ['suggestedUsers'],
            onStart: () => setIsRefreshing(true),
            onFinish: () => setIsRefreshing(false),
        });
    };

    return {
        showsWhoToFollow,
        rememberShowsWhoToFollow,
        setShowsWhoToFollow,
        isRefreshing,
        refreshSuggestedUsers,
    } as const;
}
