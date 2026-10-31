import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { ComponentType } from 'react';
import { StrictMode } from 'react';
import { createRoot, hydrateRoot } from 'react-dom/client';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme, setCookie } from '@/hooks/use-appearance';
import { setRenderedAt } from '@/hooks/use-now';
import { trackAnalyticsEvents } from '@/lib/analytics';
import { trackCommitPhotoPage } from '@/lib/commit-photo';
import { initializeCommitState } from '@/lib/commit-state';
import { trackCurrentPage } from '@/lib/current-page';
import { trackFollowRequests } from '@/lib/follow-requests';
import { pageTitle } from '@/lib/page-title';
import { registerServiceWorker } from '@/lib/service-worker';
import { browserClock, clockOf, setReaderClock } from '@/lib/utils';
import { trackVisualViewport } from '@/lib/visual-viewport';
import '../css/app.css';

trackCurrentPage();
trackCommitPhotoPage();
trackVisualViewport();
trackAnalyticsEvents();

void createInertiaApp({
    title: pageTitle,
    resolve: (name) =>
        resolvePageComponent<ComponentType>(
            `./pages/${name}.tsx`,
            import.meta.glob<ComponentType>('./pages/**/*.tsx'),
        ),
    setup({ el, App, props }) {
        trackFollowRequests(props.initialPage);
        registerServiceWorker(props.initialPage.version);

        const clock = browserClock();
        setReaderClock(clock);
        // So the server renders the next pages with the reader's clock.
        setCookie('time_zone', clock.timeZone);
        setCookie('hour_cycle', clock.hourCycle);
        setCookie('date_order', clock.dateOrder);

        const app = (
            <StrictMode>
                <TooltipProvider delayDuration={150}>
                    <App {...props} />
                    <Toaster />
                </TooltipProvider>
            </StrictMode>
        );
        const serverClock = clockOf(props.initialPage.props.clock);

        // The server's page is kept when it shows times as the browser does. Until the browser has told the server
        // its clock, on a first visit, or without SSR, the page renders afresh.
        if (
            el.hasAttribute('data-server-rendered') &&
            serverClock?.timeZone === clock.timeZone &&
            serverClock.hourCycle === clock.hourCycle &&
            serverClock.dateOrder === clock.dateOrder
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
