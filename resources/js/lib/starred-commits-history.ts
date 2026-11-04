import { router } from '@inertiajs/react';

type PhotoBackground = { backgroundUrl?: string };

/** Starring or unstarring elsewhere can leave the cached starred list stale. */
export function trackStarredCommitsHistory(): VoidFunction {
    return router.on('navigate', ({ detail: { type, page } }) => {
        if (type !== 'history' || page.component !== 'starred/index') {
            return;
        }

        const photo = page.props.photo as PhotoBackground | undefined;

        queueMicrotask(() => {
            // Drop old-cursor pagination requests before resetting the feed.
            router.cancelAll({ async: true, prefetch: false, sync: false });
            router.visit(photo?.backgroundUrl ?? page.url, {
                only: ['commits'],
                reset: ['commits'],
                preserveState: true,
                preserveScroll: false,
                preserveUrl: photo?.backgroundUrl !== undefined,
            });
        });
    });
}
