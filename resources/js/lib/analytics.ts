import { router } from '@inertiajs/react';
import { login } from '@/routes';

declare global {
    interface Window {
        fathom?: { trackEvent: (name: string) => void };
    }
}

const GUEST_VISIT_TRACKED = 'devnull:guest-visit-tracked';

/**
 * The Fathom events behind the conversion rate of the Metrics tab: a visit by someone not signed in, once per browser
 * session, a tap on a link to sign in, and a signup, right after an account is created. A tap without a signup is
 * someone who gave up at GitHub. All come from Fathom, so ad blockers hide the same share of each and the rates
 * between them hold. Fathom's deferred script runs before the app's module script, so it is ready by then, unless it
 * is blocked or left out outside production, and then nothing is sent.
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

    document.addEventListener('click', (event) => {
        const link =
            event.target instanceof Element
                ? event.target.closest('a[href]')
                : null;

        if (
            link instanceof HTMLAnchorElement &&
            link.origin === window.location.origin &&
            link.pathname === login().url
        ) {
            trackSignInTap();
        }
    });

    router.on('flash', (event) => {
        if (event.detail.flash.signedUp) {
            window.fathom?.trackEvent('signup');
        }
    });
}

/**
 * Sent before leaving for GitHub, which Fathom survives as it sends events with a beacon.
 */
export function trackSignInTap(): void {
    window.fathom?.trackEvent('signin-click');
}
