import type { VisitOptions } from '@inertiajs/core';
import { router } from '@inertiajs/react';

// Toasts render outside the Inertia page context, so they can't call usePage().
let component = '';

// Live props and moderation already catch up after history restores. Only
// non-live props need a separate reload, including when returning from bfcache.
const historyReloads: Record<
    string,
    Pick<VisitOptions, 'only' | 'reset' | 'preserveScroll'>
> = {
    'operations/features': { only: ['features'], preserveScroll: true },
    'operations/emails': { only: ['recipients'], preserveScroll: true },
    'starred/index': {
        only: ['commits'],
        reset: ['commits'],
        preserveScroll: false,
    },
};

export function currentPageComponent(): string {
    return component;
}

function refreshRestoredPage(page: { component: string; url: string }): void {
    const options = historyReloads[page.component];

    if (!options) {
        return;
    }

    // Photo overlays show their own URL over a background page. Their existing
    // before-visit handler redirects async GETs to that background without closing the photo.
    router.visit(page.url, {
        ...options,
        method: 'get',
        async: true,
        preserveState: true,
        replace: true,
        onHttpException: ({ status }) => {
            if (status === 403 || status === 404) {
                window.location.reload();

                return false;
            }
        },
    });
}

export function trackCurrentPage(): VoidFunction {
    let page: { component: string; url: string } | null = null;

    const stop = router.on('navigate', (event) => {
        component = event.detail.page.component;
        page = { component, url: event.detail.page.url };

        if (event.detail.type === 'history') {
            refreshRestoredPage(page);
        }
    });

    const browserWindow = typeof window === 'undefined' ? null : window;
    const onPageShow = (event: PageTransitionEvent) => {
        if (event.persisted && page !== null) {
            refreshRestoredPage(page);
        }
    };

    browserWindow?.addEventListener('pageshow', onPageShow);

    return () => {
        stop();
        browserWindow?.removeEventListener('pageshow', onPageShow);
    };
}
