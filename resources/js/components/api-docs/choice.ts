import { useSyncExternalStore } from 'react';

/**
 * A choice the reader makes once for the whole of the docs, like a client or a view: kept in the browser, and in step
 * across every place that shows it and every tab of the docs.
 */
export function createChoice(key: string) {
    const listeners = new Set<() => void>();
    let chosen: string | null | undefined;

    const stored = (): string | null => {
        try {
            return window.localStorage.getItem(key);
        } catch {
            return null;
        }
    };

    const current = (): string | null => {
        if (chosen === undefined) {
            chosen = stored();
        }

        return chosen;
    };

    const choose = (value: string): void => {
        chosen = value;

        try {
            window.localStorage.setItem(key, value);
        } catch {
            // A private window may refuse it: the choice then lasts as long as the page.
        }

        listeners.forEach((listener) => listener());
    };

    const subscribe = (listener: () => void): (() => void) => {
        const onStorage = (event: StorageEvent) => {
            // A null key means another tab cleared the whole storage.
            if (event.key === key || event.key === null) {
                chosen = event.key === null ? null : event.newValue;
                listener();
            }
        };

        listeners.add(listener);
        window.addEventListener('storage', onStorage);

        return () => {
            listeners.delete(listener);
            window.removeEventListener('storage', onStorage);
        };
    };

    const useChosen = (): string | null =>
        useSyncExternalStore(subscribe, current, () => null);

    return { choose, useChosen };
}
