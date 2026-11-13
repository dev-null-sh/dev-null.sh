import { useCallback, useSyncExternalStore } from 'react';
import { readStorage, writeStorage } from '@/lib/storage';

const listeners = new Map<string | null, Set<() => void>>();
const keyFor = (userId: string): string => `recent-searches:${userId}`;

function subscribe(userId: string | null, listener: () => void) {
    const userListeners = listeners.get(userId) ?? new Set();
    userListeners.add(listener);
    listeners.set(userId, userListeners);
    const onStorage = (event: StorageEvent) => {
        if (
            event.key === null ||
            (userId !== null && event.key === keyFor(userId))
        ) {
            listener();
        }
    };
    window.addEventListener('storage', onStorage);

    return () => {
        userListeners.delete(listener);
        if (userListeners.size === 0) {
            listeners.delete(userId);
        }
        window.removeEventListener('storage', onStorage);
    };
}

function parse(value: string): string[] {
    try {
        const terms: unknown = JSON.parse(value);

        return Array.isArray(terms)
            ? terms
                  .filter((term): term is string => typeof term === 'string')
                  .slice(0, 3)
            : [];
    } catch {
        return [];
    }
}

export function rememberSearch(userId: string | null, query: string): void {
    const term = query.trim();

    if (userId === null || term === '' || term.length > 100) {
        return;
    }

    const previous = parse(readStorage(keyFor(userId)));
    const recent = [
        term,
        ...previous.filter(
            (entry) => entry.toLowerCase() !== term.toLowerCase(),
        ),
    ].slice(0, 3);
    writeStorage(keyFor(userId), JSON.stringify(recent));
    listeners.get(userId)?.forEach((listener) => listener());
}

export function clearRecentSearches(userId: string): void {
    writeStorage(keyFor(userId), '');
    listeners.get(userId)?.forEach((listener) => listener());
}

export function useRecentSearches(userId: string | null): string[] {
    const subscribeToUser = useCallback(
        (listener: () => void) => subscribe(userId, listener),
        [userId],
    );
    const stored = useSyncExternalStore(
        subscribeToUser,
        () => (userId === null ? '' : readStorage(keyFor(userId))),
        () => '',
    );

    return parse(stored);
}
