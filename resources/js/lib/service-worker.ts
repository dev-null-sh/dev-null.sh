export function serviceWorkerEnabled(): boolean {
    return !document.querySelector(
        'meta[name="service-worker"][content="disabled"]',
    );
}

/**
 * Registers the service worker that makes the installed app work offline.
 *
 * The current asset version travels in the script's query string, so a deploy
 * changes the worker's URL: the browser installs the new worker on the next
 * visit, which refreshes the offline page and drops the previous build's
 * caches. Nothing is registered in development, where Vite serves the assets.
 */
export function registerServiceWorker(version: string | null): void {
    if (typeof navigator === 'undefined' || !('serviceWorker' in navigator)) {
        return;
    }

    if (!serviceWorkerEnabled()) {
        void navigator.serviceWorker
            .getRegistrations()
            .then((registrations) =>
                Promise.all(
                    registrations.map((registration) =>
                        registration.unregister(),
                    ),
                ),
            )
            .catch(() => {
                // A restricted browser may refuse access to its registrations.
            });
        return;
    }

    if (!import.meta.env.PROD) return;

    const url = new URL('/sw.js', window.location.origin);

    if (version) {
        url.searchParams.set('v', version);
    }

    const register = (): void => {
        navigator.serviceWorker
            .register(url, { scope: '/', updateViaCache: 'none' })
            .catch(() => {
                // The app works without it; a browser that refuses the worker
                // simply has no offline page.
            });
    };

    // Registering after the page has loaded keeps the worker from competing with the page's own requests...
    if (document.readyState === 'complete') {
        register();
    } else {
        window.addEventListener('load', register, { once: true });
    }
}
