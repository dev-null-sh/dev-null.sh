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

export function useNow(): Date {
    const timestamp = useSyncExternalStore(
        subscribe,
        () => now,
        () => now,
    );

    return new Date(timestamp);
}
