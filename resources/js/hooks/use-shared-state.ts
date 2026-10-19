import { useSyncExternalStore } from 'react';

export type SharedValue = boolean | number | string | null;

type Update<T> = T | ((current: T) => T);

const values = new Map<string, SharedValue>();
const listeners = new Set<() => void>();

const subscribe = (callback: () => void) => {
    listeners.add(callback);

    return () => listeners.delete(callback);
};

const notify = (): void => listeners.forEach((listener) => listener());

export function refreshSharedState(fresh: Map<string, SharedValue>): void {
    let hasChanged = false;

    fresh.forEach((value, key) => {
        if (values.get(key) !== value) {
            values.set(key, value);
            hasChanged = true;
        }
    });

    if (hasChanged) {
        notify();
    }
}

export function useSharedState<T extends SharedValue>(
    key: string,
    initialValue: T,
): [T, (update: Update<T>) => void] {
    const read = (): T =>
        values.has(key) ? (values.get(key) as T) : initialValue;

    const value = useSyncExternalStore(subscribe, read, () => initialValue);

    const setValue = (update: Update<T>) => {
        values.set(key, typeof update === 'function' ? update(read()) : update);
        notify();
    };

    return [value, setValue];
}
