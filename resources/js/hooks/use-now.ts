import { useSyncExternalStore } from 'react';

const listeners = new Set<() => void>();
let now = Date.now();
let interval: ReturnType<typeof setInterval> | undefined;

const tick = () => {
    now = Date.now();
    listeners.forEach((notify) => notify());
};

const start = () => {
    if (interval === undefined) {
        now = Date.now();
        interval = setInterval(tick, 30_000);
    }
};

const onVisibilityChange = () => {
    if (document.visibilityState === 'hidden') {
        clearInterval(interval);
        interval = undefined;
    } else if (listeners.size > 0) {
        tick();
        start();
    }
};

const subscribe = (listener: () => void) => {
    listeners.add(listener);

    if (listeners.size === 1) {
        if (typeof document !== 'undefined') {
            document.addEventListener('visibilitychange', onVisibilityChange);
        }

        if (
            typeof document === 'undefined' ||
            document.visibilityState !== 'hidden'
        ) {
            start();
        }
    }

    return () => {
        listeners.delete(listener);

        if (listeners.size === 0) {
            clearInterval(interval);
            interval = undefined;
            if (typeof document !== 'undefined') {
                document.removeEventListener(
                    'visibilitychange',
                    onVisibilityChange,
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
