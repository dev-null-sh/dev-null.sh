import { router, usePage } from '@inertiajs/react';
import { setCookie } from '@/hooks/use-appearance';

/**
 * Whether to suggest people to follow is kept in a cookie, like the theme, so the server can skip the suggestions too.
 */
export function useWhoToFollow() {
    const { showsWhoToFollow } = usePage().props;

    const rememberShowsWhoToFollow = (shows: boolean): void => {
        setCookie('who_to_follow', String(shows));
    };

    const setShowsWhoToFollow = (shows: boolean): void => {
        rememberShowsWhoToFollow(shows);
        router.reload({ only: ['showsWhoToFollow', 'suggestedUsers'] });
    };

    return {
        showsWhoToFollow,
        rememberShowsWhoToFollow,
        setShowsWhoToFollow,
    } as const;
}
