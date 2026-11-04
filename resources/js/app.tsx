import { createInertiaApp, router } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { ComponentType } from 'react';
import { StrictMode } from 'react';
import { createRoot, hydrateRoot } from 'react-dom/client';
import { TimeoutDialog } from '@/components/timeout-dialog';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme, setCookie } from '@/hooks/use-appearance';
import { setRenderedAt } from '@/hooks/use-now';
import { trackAnalyticsEvents } from '@/lib/analytics';
import { setServerTime } from '@/lib/commit-changes';
import { trackCommitPhotoPage } from '@/lib/commit-photo';
import { initializeCommitState } from '@/lib/commit-state';
import { trackCurrentPage } from '@/lib/current-page';
import { createEcho } from '@/lib/echo';
import { trackFollowRequests } from '@/lib/follow-requests';
import { startMobileApp } from '@/lib/mobile-app';
import { trackFeatureFlagsHistory } from '@/lib/operations-features-history';
import { pageTitle } from '@/lib/page-title';
import { trackPrefetchedPages } from '@/lib/prefetched-pages';
import {
    catchUpAfterRestore,
    connectRealtime,
    liveTransport,
    pageReadAt,
} from '@/lib/realtime';
import { registerServiceWorker } from '@/lib/service-worker';
import { browserClock, clockOf, setReaderClock } from '@/lib/utils';
import { trackVisualViewport } from '@/lib/visual-viewport';
import '../css/app.css';

trackCurrentPage();
trackCommitPhotoPage();
trackVisualViewport();
trackAnalyticsEvents();
trackFeatureFlagsHistory();
trackPrefetchedPages();
connectRealtime(createEcho());
// What a page shows was read once the visit bringing all of it started, and the first one once the browser asked for
// it. A page brought back from history or a prefetch shows what was read back then, and catches up.
pageReadAt(Date.now() - performance.now());
let visitStartedAt: number | null = null;
router.on('start', (event) => {
    const { only, except } = event.detail.visit;
    visitStartedAt =
        only.length === 0 && except.length === 0 ? Date.now() : null;
});
router.on('navigate', (event) => {
    if (event.detail.type === 'history' || event.detail.cached === true) {
        catchUpAfterRestore();
    } else if (visitStartedAt !== null) {
        pageReadAt(visitStartedAt);
    }

    visitStartedAt = null;
});
window.addEventListener('pageshow', (event) => {
    if (event.persisted) {
        catchUpAfterRestore();
    }
});

void createInertiaApp({
    title: pageTitle,
    live: liveTransport,
    resolve: (name) =>
        resolvePageComponent<ComponentType>(
            `./pages/${name}.tsx`,
            import.meta.glob<ComponentType>('./pages/**/*.tsx'),
        ),
    setup({ el, App, props }) {
        setServerTime(props.initialPage.props.now);
        trackFollowRequests(props.initialPage);
        startMobileApp();
        registerServiceWorker(props.initialPage.version);

        const clock = browserClock();
        setReaderClock(clock);
        // So the server renders the next pages with the reader's clock.
        setCookie('time_zone', clock.timeZone);
        setCookie('hour_cycle', clock.hourCycle);

        const app = (
            <StrictMode>
                <TooltipProvider delayDuration={150}>
                    <App {...props} />
                    <Toaster />
                    <TimeoutDialog />
                </TooltipProvider>
            </StrictMode>
        );
        const serverClock = clockOf(props.initialPage.props.clock);

        // The server's page is kept when it shows times as the browser does. Until the browser has told the server
        // its clock, on a first visit, or without SSR, the page renders afresh.
        if (
            el.hasAttribute('data-server-rendered') &&
            serverClock?.timeZone === clock.timeZone &&
            serverClock.hourCycle === clock.hourCycle
        ) {
            setRenderedAt(props.initialPage.props.now);
            hydrateRoot(el, app);
        } else {
            createRoot(el).render(app);
        }
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();

initializeCommitState();
