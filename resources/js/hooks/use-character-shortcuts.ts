import { useSyncExternalStore } from 'react';
import { readStorage, writeStorage } from '@/lib/storage';

const storageKey = 'character-shortcuts';
const listeners = new Set<() => void>();
let current: boolean | undefined;

export function characterShortcutsEnabled(): boolean {
    if (typeof window === 'undefined') {
        return true;
    }

    return current ?? readStorage(storageKey) !== 'false';
}

const notify = (): void => listeners.forEach((listener) => listener());

function onStorage(event: StorageEvent): void {
    if (event.key === storageKey || event.key === null) {
        current = undefined;
        notify();
    }
}

function subscribe(listener: () => void): () => void {
    if (listeners.size === 0) {
        window.addEventListener('storage', onStorage);
    }

    listeners.add(listener);

    return () => {
        listeners.delete(listener);

        if (listeners.size === 0) {
            window.removeEventListener('storage', onStorage);
        }
    };
}

export function setCharacterShortcutsEnabled(enabled: boolean): void {
    current = enabled;
    writeStorage(storageKey, String(enabled));
    notify();
}

export function useCharacterShortcuts(): boolean {
    return useSyncExternalStore(
        subscribe,
        characterShortcutsEnabled,
        () => true,
    );
}
