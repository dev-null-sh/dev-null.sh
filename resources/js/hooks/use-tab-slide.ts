import { router } from '@inertiajs/react';
import { useEffect } from 'react';
import LogController from '@/actions/App/Http/Controllers/LogController';
import { settingsTabs } from '@/layouts/settings/tabs';
import { clearTabSlide } from '@/lib/utils';

const tabGroups = [
    [
        LogController.show('following').url,
        LogController.show('latest').url,
        LogController.show('trending').url,
    ],
    settingsTabs.map((tab) => tab.href.url),
];

/**
 * Which way the content under the tabs slides on a visit from one tab to another of the same group, or `null` when the
 * visit does not move between such tabs.
 */
export function tabSlide(from: string, to: string): 'forward' | 'back' | null {
    const tabs = tabGroups.find(
        (urls) => urls.includes(from) && urls.includes(to),
    );

    if (tabs === undefined || from === to) {
        return null;
    }

    return tabs.indexOf(to) > tabs.indexOf(from) ? 'forward' : 'back';
}

/**
 * Tells the view transition which way the content under the tabs slides when a visit goes from one tab to another.
 */
export function useTabSlide(): void {
    useEffect(() => {
        return router.on('before', (event) => {
            const { visit } = event.detail;

            if (visit.prefetch) {
                return;
            }

            // Deferred props load while the tab slides, so only an animated partial reload stops it...
            if (visit.only.length > 0 || visit.except.length > 0) {
                if (visit.viewTransition) {
                    clearTabSlide();
                }

                return;
            }

            const slide = tabSlide(
                window.location.pathname,
                visit.url.pathname,
            );

            if (slide === null) {
                clearTabSlide();

                return;
            }

            document.documentElement.dataset.tabSlide = slide;
        });
    }, []);
}
