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

type RestoredPage = {
    component: string;
    url: string;
    photoBackgroundUrl?: string;
};

function refreshRestoredPage(page: RestoredPage): void {
    const options = historyReloads[page.component];

    if (!options) {
        return;
    }

    const photoBackgroundUrl =
        page.component === 'starred/index'
            ? page.photoBackgroundUrl
            : undefined;

    // Refresh the starred feed behind an open photo without replacing its URL.
    router.visit(photoBackgroundUrl ?? page.url, {
        ...options,
        method: 'get',
        async: true,
        preserveState: true,
        preserveUrl: photoBackgroundUrl !== undefined,
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
    let page: RestoredPage | null = null;

    const stop = router.on('navigate', (event) => {
        component = event.detail.page.component;
        const photo = event.detail.page.props.photo as
            | { backgroundUrl?: string }
            | undefined;
        page = {
            component,
            url: event.detail.page.url,
            photoBackgroundUrl: photo?.backgroundUrl,
        };

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
