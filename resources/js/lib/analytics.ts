import { router } from '@inertiajs/react';
import { login } from '@/routes';

declare global {
    interface Window {
        fathom?: { trackEvent: (name: string) => void };
    }
}

const GUEST_VISIT_TRACKED = 'devnull:guest-visit-tracked';

const FROM_JOIN_LINK = 'devnull:from-join-link';

/**
 * The Fathom events behind the conversion rate of the Metrics tab: a visit by someone not signed in, once per browser
 * session, a tap on a link to sign in, and a signup, right after an account is created. A tap without a signup is
 * someone who gave up at GitHub. A guest on a phone can also ask for a link by email, which counts when it's sent, when
 * it's opened, from the fragment the link carries, and when it ends in a signup in the same tab, which is remembered
 * there since the visit after GitHub is a new one for Fathom. All come from Fathom, so ad blockers hide the same share
 * of each and the rates between them hold. Fathom's deferred script runs before the app's module script, so it is ready
 * by then, unless it is blocked or left out outside production, and then nothing is sent.
 */
export function trackAnalyticsEvents(): void {
    const url = new URL(window.location.href);

    if (url.hash === '#join-link') {
        window.fathom?.trackEvent('join-link-visit');

        try {
            sessionStorage.setItem(FROM_JOIN_LINK, '1');
        } catch {
            // Without storage, a signup from the link counts as any other.
        }

        // Out of the address bar before Inertia reads it, so a copied link doesn't count again...
        url.hash = '';
        window.history.replaceState(window.history.state, '', url);
    }

    router.on('navigate', (event) => {
        // A browser driven by a program, like a scraper's, isn't a prospect, and Fathom only leaves it out of pageviews...
        if (event.detail.page.props.auth.user || navigator.webdriver) {
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

            if (cameFromJoinLink()) {
                window.fathom?.trackEvent('join-link-signup');
            }
        }
    });
}

/**
 * Sent before leaving for GitHub, which Fathom survives as it sends events with a beacon.
 */
export function trackSignInTap(): void {
    window.fathom?.trackEvent('signin-click');
}

/**
 * Forgotten once read: someone who signs out could sign up again in the same tab, without the link.
 */
function cameFromJoinLink(): boolean {
    try {
        const cameFromJoinLink =
            sessionStorage.getItem(FROM_JOIN_LINK) !== null;
        sessionStorage.removeItem(FROM_JOIN_LINK);

        return cameFromJoinLink;
    } catch {
        return false;
    }
}

export function trackJoinLinkSent(): void {
    window.fathom?.trackEvent('join-link-sent');
}
