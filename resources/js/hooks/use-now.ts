import { useSyncExternalStore } from 'react';

const listeners = new Set<() => void>();
let now = Date.now();
let interval: ReturnType<typeof setInterval> | undefined;

const subscribe = (listener: () => void) => {
    listeners.add(listener);

    if (interval === undefined) {
        now = Date.now();
        interval = setInterval(() => {
            now = Date.now();
            listeners.forEach((notify) => notify());
        }, 30_000);
    }

    return () => {
        listeners.delete(listener);

        if (listeners.size === 0) {
            clearInterval(interval);
            interval = undefined;
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
