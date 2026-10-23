import { gifProviders } from './gif-providers/index.ts';
import type { GifAttachment } from './gif-providers/types.ts';
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
