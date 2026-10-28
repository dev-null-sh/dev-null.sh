import type { CancelToken, ReloadOptions } from '@inertiajs/core';
import { router, usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { setCookie } from '@/hooks/use-appearance';
import { useSharedState } from '@/hooks/use-shared-state';
import { backgroundPollOptions } from '@/lib/background-poll';
import { withViewTransition } from '@/lib/utils';

// Counts the reloads that changing the settings sends, so only the latest one brings back a hidden block, and holds
// the one still waiting for its answer, so a newer choice can cancel it.
let latestSettingsReload = 0;
let settingsReloadInFlight: CancelToken | null = null;

export function currentRound(): number {
    const match = document.cookie.match(/(?:^|; )who_to_follow_round=(\d+)/);

    return match ? Number(match[1]) : 0;
}

/**
 * Whether to suggest people to follow is kept in a cookie, like the theme, so the server can skip the suggestions too.
 *
 * Hiding them from the block itself only writes the cookie, so the block stays put with an undo until the next visit
 * leaves it out. The aside, the log and the settings share that, and choosing to show suggestions in the settings
 * brings the block back at once.
 */
export function useWhoToFollow() {
    const { showsWhoToFollow, whoToFollowCount, refreshesWhoToFollow } =
        usePage().props;
    const [isHidden, setIsHidden] = useSharedState<boolean>(
        'who-to-follow-hidden',
        false,
    );
    const [isRefreshing, setIsRefreshing] = useSharedState<boolean>(
        'who-to-follow-refreshing',
        false,
    );

    // Once a visit leaves the suggestions out, there's no block left to undo from.
    useEffect(() => {
        if (!showsWhoToFollow && isHidden) {
            setIsHidden(false);
        }
    }, [showsWhoToFollow, isHidden, setIsHidden]);

    const rememberShowsWhoToFollow = (shows: boolean): void => {
        setCookie('who_to_follow', String(shows));
        router.flushAll();
    };

    const hideWhoToFollow = (hides: boolean): void => {
        rememberShowsWhoToFollow(!hides);
        setIsHidden(hides);
    };

    /**
     * Reloads run side by side, so a newer choice cancels the one still waiting: otherwise an older answer that lands
     * last would put the count from before back on the page. A block hidden from its own menu stays hidden until the
     * latest reload is through, so neither the suggestions nor the count from before show in between, and then comes
     * back the way the reload's own changes do.
     */
    const reloadSettings = (options: ReloadOptions): void => {
        const reload = ++latestSettingsReload;
        const wasHidden = isHidden;

        settingsReloadInFlight?.cancel();
        router.reload({
            ...options,
            viewTransition: true,
            onCancelToken: (token) => {
                settingsReloadInFlight = token;
            },
            onFinish: () => {
                if (reload !== latestSettingsReload) {
                    return;
                }

                settingsReloadInFlight = null;

                if (wasHidden) {
                    void withViewTransition(() => setIsHidden(false));
                }
            },
        });
    };

    const setShowsWhoToFollow = (shows: boolean): void => {
        rememberShowsWhoToFollow(shows);
        reloadSettings({
            only: ['showsWhoToFollow', 'suggestedUsers'],
            reset: ['suggestedUsers'],
        });
    };

    /**
     * Choosing how many to suggest also brings the suggestions back if they were hidden.
     */
    const setWhoToFollowCount = (count: number): void => {
        setCookie('who_to_follow_count', String(count));
        rememberShowsWhoToFollow(true);
        reloadSettings({
            only: ['showsWhoToFollow', 'whoToFollowCount', 'suggestedUsers'],
            reset: ['suggestedUsers'],
        });
    };

    const setRefreshesWhoToFollow = (refreshes: boolean): void => {
        setCookie('who_to_follow_auto_refresh', String(refreshes));
        router.flushAll();
        router.replaceProp('refreshesWhoToFollow', refreshes);
    };

    /**
     * Each refresh moves to the next page of today's picks. The old suggestions stay until the next ones arrive. One
     * made in the background neither holds up nor cancels a visit, and a gateway error doesn't come up for it.
     */
    const refreshSuggestedUsers = (inBackground = false): void => {
        if (isRefreshing) {
            return;
        }

        setCookie('who_to_follow_round', String(currentRound() + 1), 1);

        const options: ReloadOptions = {
            only: ['suggestedUsers'],
            reset: ['suggestedUsers'],
            async: inBackground,
            onStart: () => setIsRefreshing(true),
            onFinish: () => setIsRefreshing(false),
        };

        router.reload(
            inBackground ? backgroundPollOptions(options)() : options,
        );
    };

    return {
        showsWhoToFollow: showsWhoToFollow && !isHidden,
        isHidden,
        hideWhoToFollow,
        setShowsWhoToFollow,
        whoToFollowCount,
        setWhoToFollowCount,
        refreshesWhoToFollow,
        setRefreshesWhoToFollow,
        isRefreshing,
        refreshSuggestedUsers,
    } as const;
}
