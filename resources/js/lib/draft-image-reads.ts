import { readPickedImages } from './read-picked-images';

type ImageReadListener = () => void;

// Each read has its own identity, even when one composer starts several reads.
type ImageRead = {
    readonly owner?: symbol;
};

type ImageReadOptions = {
    readonly capacity?: number;
    readonly onReady: (files: File[]) => void;
    readonly onError: (error: unknown) => void;
    readonly owner?: symbol;
};

const reads = new Map<string, ImageRead>();
const listeners = new Set<ImageReadListener>();

function notifyListeners(): void {
    listeners.forEach((listener) => listener());
}

export function subscribeImageReads(listener: ImageReadListener): () => void {
    listeners.add(listener);

    return () => listeners.delete(listener);
}

export function isReadingImages(key: string): boolean {
    return reads.has(key);
}

export function cancelImageRead(key: string, owner?: symbol): void {
    const read = reads.get(key);

    if (!read || (owner !== undefined && read.owner !== owner)) return;

    reads.delete(key);

    notifyListeners();
}

export function cancelImageReads(prefix: string): void {
    let changed = false;

    for (const key of reads.keys()) {
        if (key.startsWith(prefix)) {
            reads.delete(key);
            changed = true;
        }
    }

    if (changed) notifyListeners();
}

export async function readImagesForDraft(
    key: string,
    files: File[],
    { onReady, onError, owner, capacity }: ImageReadOptions,
): Promise<void> {
    if (reads.has(key)) return;

    const read = { owner };
    reads.set(key, read);
    notifyListeners();

    try {
        const { images, errors } = await readPickedImages(files, capacity);
        if (reads.get(key) !== read) return;

        if (images.length > 0) onReady(images);
        if (errors.length > 0 && reads.get(key) === read) onError(errors[0]);
    } finally {
        if (reads.get(key) === read) {
            reads.delete(key);
            notifyListeners();
        }
    }
}
