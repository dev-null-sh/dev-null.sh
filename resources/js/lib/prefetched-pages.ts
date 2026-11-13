import type { Page } from '@inertiajs/core';
import { router } from '@inertiajs/react';

export function trackPrefetchedPages(): VoidFunction {
    const stopPreloading = router.on('prefetched', (event) => {
        const { response } = event.detail;

        if (!('x-inertia' in response.headers)) {
            return;
        }

        const page: Page = JSON.parse(response.data);

        void router.resolveComponent(page.component);
    });

    const stopFlushing = router.on('finish', (event) => {
        const { visit } = event.detail;

        if (visit.method !== 'get' && visit.completed) {
            router.flushAll();
        }
    });

    return () => {
        stopPreloading();
        stopFlushing();
    };
}
