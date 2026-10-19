import { router } from '@inertiajs/react';
import { useEffect } from 'react';
import FeatureController from '@/actions/App/Http/Controllers/FeatureController';
import LogController from '@/actions/App/Http/Controllers/LogController';
import OperationsController from '@/actions/App/Http/Controllers/OperationsController';
import SiteActivityController from '@/actions/App/Http/Controllers/SiteActivityController';
import TrafficController from '@/actions/App/Http/Controllers/TrafficController';
import { clearTabSlide } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editProfile } from '@/routes/user-profile';

const tabGroups = [
    [
        LogController.show('following').url,
        LogController.show('latest').url,
        LogController.show('trending').url,
    ],
    [editProfile().url, editAppearance().url],
    [
        OperationsController.show().url,
        FeatureController.index().url,
        SiteActivityController.show().url,
        TrafficController.show().url,
    ],
];

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

            const from = window.location.pathname;
            const to = visit.url.pathname;
            const tabs = tabGroups.find(
                (urls) => urls.includes(from) && urls.includes(to),
            );

            if (tabs === undefined || from === to) {
                clearTabSlide();

                return;
            }

            document.documentElement.dataset.tabSlide =
                tabs.indexOf(to) > tabs.indexOf(from) ? 'forward' : 'back';
        });
    }, []);
}
