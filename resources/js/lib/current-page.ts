import { router } from '@inertiajs/react';

// Toasts render outside the Inertia page context, so they can't call usePage().
let component = '';

export function currentPageComponent(): string {
    return component;
}

export function trackCurrentPage(): VoidFunction {
    return router.on('navigate', (event) => {
        component = event.detail.page.component;
    });
}
