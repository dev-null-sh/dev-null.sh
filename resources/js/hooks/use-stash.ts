import type { FocusEvent } from 'react';
import { useEffect, useSyncExternalStore } from 'react';
import {
    maxImages,
    pickImages,
    revokeObjectUrls,
} from '@/components/commit-image-picker';
import type { PollDraft } from '@/components/poll-editor';
import type { StoredImages } from '@/lib/image-storage';
import {
    deleteStoredImages,
    readStoredImages,
    writeStoredImages,
} from '@/lib/image-storage';
import type { IssueKind } from '@/lib/issue-commands';
import { stashableBody } from '@/lib/sensitive-text';
import { readStorage, storedKeys, writeStorage } from '@/lib/storage';

export type UseStashReturn = {
    readonly body: string;
    readonly images: File[];
    readonly descriptions: string[];
    readonly isRestored: boolean;
    readonly dropCount: number;
    readonly updateBody: (body: string) => void;
    readonly updateImages: (images: File[], descriptions?: string[]) => void;
    readonly drop: () => void;
};

/**
 * The rest of a commit draft, besides its body and images. It is never stored, so it is lost on reload.
 */
export type DraftParts = {
    poll: PollDraft | null;
    issueKind: IssueKind | null;
    isScheduling: boolean;
    scheduledAt: string;
    timezone: string;
    linkCard: string | null | undefined;
    isOpeningIssue: boolean;
};

const noParts: DraftParts = {
    poll: null,
    issueKind: null,
    isScheduling: false,
    scheduledAt: '',
    timezone: '',
    linkCard: undefined,
    isOpeningIssue: false,
};

const listeners = new Set<() => void>();
const pending = new Map<string, string>();
/**
 * Every composer on a key edits the same draft, so the log composer shows what the dialog typed without remounting.
 * A body with a secret lives only here, never in storage. Once no composer shows the key, it is read from storage
 * again, so a draft changed in another tab is not overwritten.
 */
const drafts = new Map<string, string>();
const restored = new Map<string, boolean>();
const parts = new Map<string, Partial<DraftParts>>();
const drops = new Map<string, number>();
const composers = new Map<string, number>();
const images = new Map<string, StoredImages>();
const restoredImageKeys = new Set<string>();
const noImages: StoredImages = { images: [], descriptions: [] };
let timeout: ReturnType<typeof setTimeout> | undefined;
let isListeningForPageHide = false;
let isLoadingImages = false;

const prefixFor = (userId: string): string => `stash:${userId}:`;

const keyFor = (userId: string, name: string): string =>
    `${prefixFor(userId)}${name}`;

const read = (key: string): string =>
    pending.has(key) ? (pending.get(key) ?? '') : readStorage(key);

const draftFor = (key: string): string =>
    drafts.has(key) ? (drafts.get(key) ?? '') : read(key);

const imagesFor = (key: string): StoredImages => images.get(key) ?? noImages;

/**
 * A draft found when the first composer on the key mounts is restored. The first edit fixes that answer, so text
 * typed after mount never counts as restored once it is flushed to storage.
 */
const isRestoredDraft = (key: string): boolean =>
    restored.get(key) ?? (read(key) !== '' || imagesFor(key).images.length > 0);

const fixRestored = (key: string, next?: boolean): void => {
    if (composers.has(key)) {
        restored.set(key, next ?? isRestoredDraft(key));
    }
};

const writeDraft = (key: string, next: string): void => {
    if (!composers.has(key)) {
        return;
    }

    fixRestored(key);
    drafts.set(key, next);
    notify();
};

const useDraftImages = (key: string) => {
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

    return {
        draftImages: draftImages.images,
        descriptions: draftImages.descriptions,
        isImagesRestored,
    };
};

const notify = (): void => listeners.forEach((listener) => listener());

const loadImages = (): void => {
    if (isLoadingImages) {
        return;
    }

    isLoadingImages = true;

    void readStoredImages().then((stored) => {
        stored.forEach((draft, key) => {
            const restored = draft.images
                .map((file, index) => ({
                    file,
                    description: draft.descriptions[index] ?? '',
                }))
                .filter(({ file }) => pickImages([], [file]).images.length > 0)
                .slice(0, maxImages);

            if (!images.has(key) && restored.length > 0) {
                images.set(key, {
                    images: restored.map((entry) => entry.file),
                    descriptions: restored.map((entry) => entry.description),
                });
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
    pending.forEach((body, key) => writeStorage(key, stashableBody(body)));
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

const storeImages = (
    key: string,
    next: File[],
    descriptions: string[] = [],
): void => {
    if (next.length === 0) {
        restoredImageKeys.delete(key);
    }

    if (next.length === 0 && images.get(key)?.images.length === 0) {
        return;
    }

    revokeObjectUrls(
        imagesFor(key).images.filter((image) => !next.includes(image)),
    );

    const nextDescriptions = next.map((_, index) => descriptions[index] ?? '');
    images.set(key, { images: next, descriptions: nextDescriptions });
    writeStoredImages(key, next, nextDescriptions);
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

/**
 * Drafts of series kept their later parts there. Only the first part survives, as the commit being written.
 */
export function discardSeriesStash(userId: string): void {
    writeStorage(`${keyFor(userId, 'commit')}:later`, '');
}

export function clearStashes(userId: string): void {
    const prefix = prefixFor(userId);

    [
        ...pending.keys(),
        ...storedKeys(),
        ...drafts.keys(),
        ...restored.keys(),
        ...parts.keys(),
        ...drops.keys(),
    ]
        .filter((key) => key.startsWith(prefix))
        .forEach((key) => {
            pending.delete(key);
            drafts.delete(key);
            restored.delete(key);
            parts.delete(key);
            drops.delete(key);
            writeStorage(key, '');
        });

    [...images.keys()]
        .filter((key) => key.startsWith(prefix))
        .forEach((key) => {
            revokeObjectUrls(imagesFor(key).images);
            images.delete(key);
            restoredImageKeys.delete(key);
        });

    deleteStoredImages(prefix);
    notify();
}

/**
 * Guests never stash anything, as they sign in before they write.
 */
export function useHasStash(userId: string | null, name: string): boolean {
    const key = userId === null ? null : keyFor(userId, name);

    return useSyncExternalStore(
        subscribe,
        () =>
            key !== null &&
            (read(key) !== '' || imagesFor(key).images.length > 0),
        () => false,
    );
}

/**
 * Every composer on the key shares the part, and dropping the draft resets it.
 */
export function useDraftPart<P extends keyof DraftParts>(
    userId: string,
    name: string,
    part: P,
): [DraftParts[P], (next: DraftParts[P]) => void] {
    const key = keyFor(userId, name);
    const value = useSyncExternalStore(
        subscribe,
        () => {
            const current = parts.get(key);

            return current !== undefined && part in current
                ? (current[part] as DraftParts[P])
                : noParts[part];
        },
        () => noParts[part],
    );

    const setValue = (next: DraftParts[P]): void => {
        if (!composers.has(key)) {
            return;
        }

        parts.set(key, { ...parts.get(key), [part]: next });
        notify();
    };

    return [value, setValue];
}

export function useStash(userId: string, name: string): UseStashReturn {
    const key = keyFor(userId, name);
    const body = useSyncExternalStore(
        subscribe,
        () => draftFor(key),
        () => read(key),
    );
    const setBody = (next: string): void => writeDraft(key, next);
    const dropCount = useSyncExternalStore(
        subscribe,
        () => drops.get(key) ?? 0,
        () => 0,
    );
    const isDraftRestored = useSyncExternalStore(
        subscribe,
        () => isRestoredDraft(key),
        () => false,
    );
    const { draftImages, descriptions, isImagesRestored } = useDraftImages(key);

    useEffect(() => {
        composers.set(key, (composers.get(key) ?? 0) + 1);

        return () => {
            const left = (composers.get(key) ?? 1) - 1;

            if (left > 0) {
                composers.set(key, left);

                return;
            }

            composers.delete(key);
            drafts.delete(key);
            restored.delete(key);
            parts.delete(key);
            drops.delete(key);
        };
    }, [key]);

    useEffect(() => {
        if (body !== '' && stashableBody(body) === '') {
            writeDraft(key, body);
            discardBody(key);
        }
    }, [body, key]);

    const updateBody = (next: string): void => {
        setBody(next);

        if (next === '') {
            if (draftImages.length === 0) {
                fixRestored(key, false);
            }

            discardBody(key);

            return;
        }

        if (stashableBody(next) === '') {
            discardBody(key);
        } else {
            schedule(key, next);
        }
    };

    const updateImages = (next: File[], descriptions: string[] = []): void => {
        fixRestored(key, next.length === 0 && body === '' ? false : undefined);
        storeImages(key, next, descriptions);
    };

    const drop = (): void => {
        if (composers.has(key)) {
            drops.set(key, (drops.get(key) ?? 0) + 1);
            parts.delete(key);
            setBody('');
            restored.set(key, false);
        }

        discardBody(key);
        storeImages(key, []);
    };

    return {
        body,
        images: draftImages,
        descriptions,
        isRestored:
            (isDraftRestored || isImagesRestored) &&
            (body !== '' || draftImages.length > 0),
        dropCount,
        updateBody,
        updateImages,
        drop,
    } as const;
}
