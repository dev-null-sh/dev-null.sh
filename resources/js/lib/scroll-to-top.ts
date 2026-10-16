const event = 'devnull:scroll-to-top';

/**
 * Clicking the nav item for the page you are already on asks that page to go back to the top.
 */
export function requestScrollToTop(): void {
    window.dispatchEvent(new Event(event));
}

export function onScrollToTopRequest(callback: () => void): () => void {
    window.addEventListener(event, callback);

    return () => window.removeEventListener(event, callback);
}
