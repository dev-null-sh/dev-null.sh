import { useSyncExternalStore } from 'react';

type Update<T> = T | ((current: T) => T);

const values = new Map<string, unknown>();
const listeners = new Set<() => void>();

const subscribe = (callback: () => void) => {
    listeners.add(callback);

    return () => listeners.delete(callback);
};

export function useSharedState<T extends boolean | number>(
    key: string,
    initialValue: T,
): [T, (update: Update<T>) => void] {
    const read = (): T =>
        values.has(key) ? (values.get(key) as T) : initialValue;

    const value = useSyncExternalStore(subscribe, read, () => initialValue);

    const setValue = (update: Update<T>) => {
        values.set(key, typeof update === 'function' ? update(read()) : update);
        listeners.forEach((listener) => listener());
    };

    return [value, setValue];
}
