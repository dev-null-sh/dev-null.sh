import { useMemo, useSyncExternalStore } from 'react';
import { gifProviders } from './gif-providers.ts';
import type { GifAttachment, GifProviderId } from './gif-providers.ts';
import { readStorage, writeStorage } from './storage.ts';

const gifKey = (key: string): string => `${key}:gif`;

/** A stashed GIF is tiny JSON in localStorage; images are Files in IndexedDB. */
export function readStoredGif(key: string): GifAttachment | null {
    const raw = readStorage(gifKey(key));

    if (raw === '') {
        return null;
    }

    try {
        const parsed = JSON.parse(raw) as Partial<GifAttachment>;

        return typeof parsed.id === 'string' &&
            typeof parsed.url === 'string' &&
            typeof parsed.pageUrl === 'string' &&
            typeof parsed.provider === 'string' &&
            parsed.provider in gifProviders
            ? (parsed as GifAttachment)
            : null;
    } catch {
        return null;
    }
}

export function writeStoredGif(key: string, gif: GifAttachment | null): void {
    writeStorage(gifKey(key), gif === null ? '' : JSON.stringify(gif));
}

export type GifProviderKey = {
    provider: GifProviderId;
    key: string;
};

type Cached = { raw: string; value: GifProviderKey | null };

const cache = new Map<string, Cached>();
const listeners = new Map<string, Set<() => void>>();

const storageKey = (userId: string): string => `gif-provider:${userId}`;

function parse(raw: string): GifProviderKey | null {
    if (raw === '') {
        return null;
    }

    let parsed: unknown;

    try {
        parsed = JSON.parse(raw);
    } catch {
        return null;
    }

    if (typeof parsed !== 'object' || parsed === null) {
        return null;
    }

    const candidate = parsed as { provider?: unknown; key?: unknown };

    if (
        typeof candidate.provider !== 'string' ||
        !Object.hasOwn(gifProviders, candidate.provider) ||
        typeof candidate.key !== 'string' ||
        candidate.key.trim() === '' ||
        candidate.key.length > 255
    ) {
        return null;
    }

    return {
        provider: candidate.provider as GifProviderId,
        key: candidate.key.trim(),
    };
}

export function readGifProviderKey(userId: string): GifProviderKey | null {
    const raw = readStorage(storageKey(userId));
    const cached = cache.get(userId);

    if (cached !== undefined && cached.raw === raw) {
        return cached.value;
    }

    const value = parse(raw);
    cache.set(userId, { raw, value });

    return value;
}

function notify(userId: string): void {
    listeners.get(userId)?.forEach((listener) => listener());
}

export function writeGifProviderKey(
    userId: string,
    value: GifProviderKey,
): boolean {
    const key = value.key.trim();

    if (key === '' || key.length > 255) {
        return false;
    }

    writeStorage(
        storageKey(userId),
        JSON.stringify({ provider: value.provider, key }),
    );
    cache.delete(userId);

    const stored = readGifProviderKey(userId);

    if (
        stored === null ||
        stored.provider !== value.provider ||
        stored.key !== key
    ) {
        return false;
    }

    notify(userId);

    return true;
}

export function clearGifProviderKey(userId: string): void {
    writeStorage(storageKey(userId), '');
    cache.delete(userId);
    notify(userId);
}

export function subscribeGifProviderKey(
    userId: string,
    listener: () => void,
): () => void {
    const key = storageKey(userId);
    const subscribed = listeners.get(userId) ?? new Set<() => void>();

    subscribed.add(listener);
    listeners.set(userId, subscribed);

    const onStorage = (event: StorageEvent): void => {
        if (event.key !== null && event.key !== key) {
            return;
        }

        if (
            event.storageArea !== undefined &&
            event.storageArea !== null &&
            event.storageArea !== window.localStorage
        ) {
            return;
        }

        cache.delete(userId);
        listener();
    };

    if (typeof window !== 'undefined') {
        window.addEventListener('storage', onStorage);
    }

    return () => {
        subscribed.delete(listener);

        if (subscribed.size === 0) {
            listeners.delete(userId);
        }

        if (typeof window !== 'undefined') {
            window.removeEventListener('storage', onStorage);
        }
    };
}

const getServerSnapshot = (): null => null;

export function useGifProviderKey(
    userId: string | null,
): GifProviderKey | null {
    const subscribe = useMemo(
        () => (listener: () => void) =>
            userId === null
                ? () => undefined
                : subscribeGifProviderKey(userId, listener),
        [userId],
    );
    const getSnapshot = useMemo(
        () => () => (userId === null ? null : readGifProviderKey(userId)),
        [userId],
    );

    return useSyncExternalStore(subscribe, getSnapshot, getServerSnapshot);
}
