import type { FocusEvent } from 'react';
import { useState, useSyncExternalStore } from 'react';
import { pickImages } from '@/components/commit-image-picker';
import {
    deleteStoredImages,
    readStoredImages,
    writeStoredImages,
} from '@/lib/image-storage';
import { readStorage, storedKeys, writeStorage } from '@/lib/storage';

export type UseStashReturn = {
    readonly body: string;
    readonly images: File[];
    readonly isRestored: boolean;
    readonly updateBody: (body: string) => void;
    readonly updateImages: (images: File[]) => void;
    readonly drop: () => void;
};

const listeners = new Set<() => void>();
const pending = new Map<string, string>();
const images = new Map<string, File[]>();
const restoredImageKeys = new Set<string>();
const noImages: File[] = [];
let timeout: ReturnType<typeof setTimeout> | undefined;
let isListeningForPageHide = false;
let isLoadingImages = false;

const prefixFor = (userId: string): string => `stash:${userId}:`;

const keyFor = (userId: string, name: string): string =>
    `${prefixFor(userId)}${name}`;

const read = (key: string): string =>
    pending.has(key) ? (pending.get(key) ?? '') : readStorage(key);

const imagesFor = (key: string): File[] => images.get(key) ?? noImages;

const notify = (): void => listeners.forEach((listener) => listener());

const loadImages = (): void => {
    if (isLoadingImages) {
        return;
    }

    isLoadingImages = true;

    void readStoredImages().then((stored) => {
        stored.forEach((files, key) => {
            const restored = pickImages([], files).images;

            if (!images.has(key) && restored.length > 0) {
                images.set(key, restored);
                restoredImageKeys.add(key);
            }
        });

        notify();
    });
};

const subscribe = (callback: () => void) => {
    loadImages();
    listeners.add(callback);

    return () => listeners.delete(callback);
};

const flush = (): void => {
    clearTimeout(timeout);
    pending.forEach((body, key) => writeStorage(key, body));
    pending.clear();
    notify();
};

const schedule = (key: string, body: string): void => {
    if (!isListeningForPageHide) {
        window.addEventListener('pagehide', flush);
        isListeningForPageHide = true;
    }

    pending.set(key, body);
    clearTimeout(timeout);
    timeout = setTimeout(flush, 300);
};

const discardBody = (key: string): void => {
    pending.delete(key);
    writeStorage(key, '');
    notify();
};

const storeImages = (key: string, next: File[]): void => {
    if (next.length === 0) {
        restoredImageKeys.delete(key);
    }

    if (next.length === 0 && images.get(key)?.length === 0) {
        return;
    }

    images.set(key, next);
    writeStoredImages(key, next);
    notify();
};

export function moveCaretToEnd(event: FocusEvent<HTMLTextAreaElement>): void {
    const textarea = event.currentTarget;

    if (textarea.selectionStart === 0 && textarea.selectionEnd === 0) {
        textarea.setSelectionRange(
            textarea.value.length,
            textarea.value.length,
        );
    }
}

export function clearStashes(userId: string): void {
    const prefix = prefixFor(userId);

    [...pending.keys(), ...storedKeys()]
        .filter((key) => key.startsWith(prefix))
        .forEach((key) => {
            pending.delete(key);
            writeStorage(key, '');
        });

    [...images.keys()]
        .filter((key) => key.startsWith(prefix))
        .forEach((key) => {
            images.delete(key);
            restoredImageKeys.delete(key);
        });

    deleteStoredImages(prefix);
    notify();
}

export function useHasStash(userId: string, name: string): boolean {
    const key = keyFor(userId, name);

    return useSyncExternalStore(
        subscribe,
        () => read(key) !== '' || imagesFor(key).length > 0,
        () => false,
    );
}

export function useStash(userId: string, name: string): UseStashReturn {
    const key = keyFor(userId, name);
    const [body, setBody] = useState(() => read(key));
    const draftImages = useSyncExternalStore(
        subscribe,
        () => imagesFor(key),
        () => noImages,
    );
    const isImagesRestored = useSyncExternalStore(
        subscribe,
        () => restoredImageKeys.has(key),
        () => false,
    );
    const [isDraftRestored, setIsDraftRestored] = useState(
        () => body !== '' || draftImages.length > 0,
    );

    const updateBody = (next: string): void => {
        setBody(next);

        if (next === '') {
            if (draftImages.length === 0) {
                setIsDraftRestored(false);
            }

            discardBody(key);

            return;
        }

        schedule(key, next);
    };

    const updateImages = (next: File[]): void => {
        if (next.length === 0 && body === '') {
            setIsDraftRestored(false);
        }

        storeImages(key, next);
    };

    const drop = (): void => {
        setBody('');
        setIsDraftRestored(false);
        discardBody(key);
        storeImages(key, []);
    };

    return {
        body,
        images: draftImages,
        isRestored:
            (isDraftRestored || isImagesRestored) &&
            (body !== '' || draftImages.length > 0),
        updateBody,
        updateImages,
        drop,
    } as const;
}
