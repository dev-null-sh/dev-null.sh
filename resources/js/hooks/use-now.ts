import { useSyncExternalStore } from 'react';

const listeners = new Set<() => void>();
let now = Date.now();
let interval: ReturnType<typeof setInterval> | undefined;

function tick(): void {
    now = Date.now();
    listeners.forEach((notify) => notify());
}

function start(): void {
    if (
        interval === undefined &&
        (typeof document === 'undefined' || !document.hidden)
    ) {
        interval = setInterval(tick, 30_000);
    }
}

function visibilityChanged(): void {
    if (document.hidden) {
        clearInterval(interval);
        interval = undefined;
    } else {
        // Refresh immediately on return rather than waiting for the next half-minute tick.
        tick();
        start();
    }
}

const subscribe = (listener: () => void) => {
    const wasEmpty = listeners.size === 0;
    listeners.add(listener);

    if (wasEmpty) {
        now = Date.now();
        if (typeof document !== 'undefined') {
            document.addEventListener('visibilitychange', visibilityChanged);
        }
        start();
    }

    return () => {
        listeners.delete(listener);

        if (listeners.size === 0) {
            clearInterval(interval);
            interval = undefined;
            if (typeof document !== 'undefined') {
                document.removeEventListener(
                    'visibilitychange',
                    visibilityChanged,
                );
            }
        }
    };
};

let renderedAt: number | null = null;

/**
 * The moment the server rendered the page, which relative times count from on the server and while the browser
 * hydrates the page, so both show the same. The browser then counts from its own clock.
 */
export function setRenderedAt(timestamp: number | null): void {
    renderedAt = timestamp;
}

export function useNow(): Date {
    const timestamp = useSyncExternalStore(
        subscribe,
        () => now,
        () => renderedAt ?? now,
    );

    return new Date(timestamp);
}
