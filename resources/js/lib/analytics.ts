import { router } from '@inertiajs/react';

declare global {
    interface Window {
        fathom?: { trackEvent: (name: string) => void };
    }
}

const GUEST_VISIT_TRACKED = 'devnull:guest-visit-tracked';

/**
 * The two Fathom events behind the conversion rate of the Metrics tab: a visit by someone not signed in, once per
 * browser session, and a signup, right after an account is created. Both come from Fathom, so ad blockers hide the
 * same share of each and the rate between them holds. Fathom's deferred script runs before the app's module script,
 * so it is ready by then, unless it is blocked or left out outside production, and then nothing is sent.
 */
export function trackAnalyticsEvents(): void {
    router.on('navigate', (event) => {
        if (event.detail.page.props.auth.user) {
            return;
        }

        try {
            if (sessionStorage.getItem(GUEST_VISIT_TRACKED) !== null) {
                return;
            }

            sessionStorage.setItem(GUEST_VISIT_TRACKED, '1');
        } catch {
            return;
        }

        window.fathom?.trackEvent('guest-visit');
    });

    router.on('flash', (event) => {
        if (event.detail.flash.signedUp) {
            window.fathom?.trackEvent('signup');
        }
    });
}
