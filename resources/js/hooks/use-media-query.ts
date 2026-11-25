import { useSyncExternalStore } from 'react';

type MediaQueryStore = {
    subscribe: (listener: () => void) => () => void;
    snapshot: () => boolean;
};

const stores = new Map<string, MediaQueryStore>();
const serverSnapshot = () => false;
const noSubscription = () => () => {};

function storeFor(query: string): MediaQueryStore {
    if (typeof window === 'undefined') {
        return { subscribe: noSubscription, snapshot: serverSnapshot };
    }

    const previous = stores.get(query);
    if (previous) {
        return previous;
    }

    const media = window.matchMedia(query);
    const store = {
        subscribe: (listener: () => void) => {
            media.addEventListener('change', listener);
            return () => media.removeEventListener('change', listener);
        },
        snapshot: () => media.matches,
    };

    stores.set(query, store);
    return store;
}

export function useMediaQuery(query: string): boolean {
    const store = storeFor(query);

    return useSyncExternalStore(
        store.subscribe,
        store.snapshot,
        serverSnapshot,
    );
}
