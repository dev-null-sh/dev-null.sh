import { router } from '@inertiajs/react';

/** Feature switches can be changed by another admin while the page is cached. */
export function trackFeatureFlagsHistory(): VoidFunction {
    return router.on('navigate', ({ detail: { type, page } }) => {
        if (type !== 'history' || page.component !== 'operations/features') {
            return;
        }

        router.reload({
            only: ['features'],
            preserveScroll: true,
        });
    });
}
