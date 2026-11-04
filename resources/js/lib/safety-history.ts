import { router } from '@inertiajs/react';

/** Mute and block lists can change from profile menus while settings are cached in history. */
export function trackSafetyHistory(): VoidFunction {
    return router.on('navigate', ({ detail: { type, page } }) => {
        if (type !== 'history' || page.component !== 'safety/edit') {
            return;
        }

        router.reload({
            only: ['muted', 'blocked'],
            preserveScroll: true,
        });
    });
}
