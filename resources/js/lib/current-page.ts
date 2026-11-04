import type { VisitOptions } from '@inertiajs/core';
import { router } from '@inertiajs/react';

// Toasts render outside the Inertia page context, so they can't call usePage().
let component = '';

const historyReloads: Record<
    string,
    Pick<VisitOptions, 'only' | 'reset' | 'preserveScroll'>
> = {
    'device/index': { only: ['devices'], preserveScroll: true },
    'safety/edit': { only: ['muted', 'blocked'], preserveScroll: true },
    'organization-member/index': {
        only: ['organization', 'role', 'members'],
        preserveScroll: true,
    },
    'operations/features': { only: ['features'], preserveScroll: true },
    'operations/emails': { only: ['recipients'], preserveScroll: true },
    'starred/index': {
        only: ['commits'],
        reset: ['commits'],
        preserveScroll: false,
    },
    'moderation/show': {
        only: ['commits', 'hasUnmoderatedCommits'],
        reset: ['commits'],
        preserveScroll: false,
    },
};

export function currentPageComponent(): string {
    return component;
}

export function trackCurrentPage(): VoidFunction {
    return router.on('navigate', (event) => {
        component = event.detail.page.component;

        if (event.detail.type !== 'history') {
            return;
        }

        const options = historyReloads[component];
        if (options) {
            router.visit(event.detail.page.url, {
                ...options,
                method: 'get',
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
    });
}
