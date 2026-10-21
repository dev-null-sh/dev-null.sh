import { useSyncExternalStore } from 'react';
import { readStorage, writeStorage } from '@/lib/storage';

const listeners = new Set<() => void>();
const keyFor = (userId: string): string => `recent-searches:${userId}`;

function subscribe(listener: () => void) {
    listeners.add(listener);
    window.addEventListener('storage', listener);

    return () => {
        listeners.delete(listener);
        window.removeEventListener('storage', listener);
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
    listeners.forEach((listener) => listener());
}

export function clearRecentSearches(userId: string): void {
    writeStorage(keyFor(userId), '');
    listeners.forEach((listener) => listener());
}

export function useRecentSearches(userId: string | null): string[] {
    const stored = useSyncExternalStore(
        subscribe,
        () => (userId === null ? '' : readStorage(keyFor(userId))),
        () => '',
    );

    return parse(stored);
}
