import type { ClipboardEvent, DragEvent } from 'react';
import { useLayoutEffect, useRef, useState, useSyncExternalStore } from 'react';
import { maxImages, pickImages } from '@/components/commit-image-picker';
import {
    cancelImageRead,
    isReadingImages,
    readImagesForDraft,
    subscribeImageReads,
} from '@/lib/draft-image-reads';
import { needsConversion, PickedImageError } from '@/lib/read-picked-images';

export type UseImageAttachmentsReturn = {
    readonly images: File[];
    readonly descriptions: string[];
    readonly error: string | null;
    readonly clearError: () => void;
    readonly reading: boolean;
    readonly isReading: () => boolean;
    readonly pick: (files: File[]) => Promise<void>;
    readonly attach: (files: File[]) => void;
    readonly describe: (index: number, description: string) => void;
    readonly remove: (index: number) => void;
    readonly clear: () => void;
    readonly onPaste: (event: ClipboardEvent<HTMLElement>) => void;
    readonly isDraggingFiles: boolean;
    readonly dropzone: {
        readonly onDragEnter: (event: DragEvent<HTMLElement>) => void;
        readonly onDragOver: (event: DragEvent<HTMLElement>) => void;
        readonly onDragLeave: (event: DragEvent<HTMLElement>) => void;
        readonly onDrop: (event: DragEvent<HTMLElement>) => void;
    };
};

const hasFiles = (dataTransfer: DataTransfer): boolean =>
    Array.from(dataTransfer.types).includes('Files');

/**
 * Keeps the images attached to a commit being written, picked, pasted, or dropped, and the description written for each.
 */
export function useImageAttachments(
    images: File[],
    descriptions: string[],
    onChange: (images: File[], descriptions: string[]) => void,
    draftKey: string,
    disabled = false,
): UseImageAttachmentsReturn {
    const [error, setError] = useState<string | null>(null);
    const [dragDepth, setDragDepth] = useState(0);

    const attach = (files: File[]) => {
        if (disabled || files.length === 0) {
            return;
        }

        const picked = pickImages(images, files);

        onChange(
            picked.images,
            picked.images.map((_, index) => descriptions[index] ?? ''),
        );
        setError(picked.error);
    };

    // Async reads must merge through the latest committed attachment state.
    const latestAttach = useRef(attach);
    const readOwner = useRef(Symbol('image read owner'));
    useLayoutEffect(() => {
        latestAttach.current = attach;
    });
    useLayoutEffect(() => {
        const owner = readOwner.current;
        return () => cancelImageRead(draftKey, owner);
    }, [draftKey]);
    const reading = useSyncExternalStore(
        subscribeImageReads,
        () => isReadingImages(draftKey),
        () => false,
    );

    const pick = async (files: File[]) => {
        const available = Math.max(0, maxImages - images.length);
        if (disabled || files.length === 0) return;

        setError(null);
        await readImagesForDraft(draftKey, files, {
            capacity: available,
            onReady: (prepared) => {
                latestAttach.current(prepared);
            },
            onError: (error) => {
                setError(
                    error instanceof PickedImageError
                        ? error.message
                        : 'Could not read this image. Try selecting it again, or choose Browse in the photo picker.',
                );
            },
            owner: readOwner.current,
        });
    };

    // Dropped and pasted images attach at once, unless one of them is a HEIC or
    // DNG photo or over the size limit: then all of them go through the reader
    // that picked images use, which converts those, one read at a time.
    const add = (files: File[]) => {
        if (disabled) return;

        if (!files.some(needsConversion)) {
            attach(files);
        } else if (isReadingImages(draftKey)) {
            setError('Still adding images. Try again once they appear.');
        } else {
            void pick(files);
        }
    };

    return {
        images,
        descriptions,
        error,
        clearError: () => setError(null),
        reading,
        isReading: () => isReadingImages(draftKey),
        pick,
        attach,
        describe: (index, description) => {
            if (disabled) return;

            onChange(
                images,
                images.map((_, position) =>
                    position === index
                        ? description
                        : (descriptions[position] ?? ''),
                ),
            );
        },
        remove: (index) => {
            if (disabled) return;

            onChange(
                images.filter((_, position) => position !== index),
                descriptions.filter((_, position) => position !== index),
            );
            setError(null);
        },
        clear: () => {
            cancelImageRead(draftKey);
            onChange([], []);
            setError(null);
        },
        onPaste: (event) => {
            const files = Array.from(event.clipboardData.files);

            if (files.length > 0) {
                event.preventDefault();
                add(files);
            }
        },
        isDraggingFiles: dragDepth > 0,
        dropzone: {
            onDragEnter: (event) => {
                if (hasFiles(event.dataTransfer)) {
                    event.preventDefault();
                    if (!disabled) {
                        setDragDepth((depth) => depth + 1);
                    }
                }
            },
            onDragOver: (event) => {
                if (hasFiles(event.dataTransfer)) {
                    event.preventDefault();
                    event.dataTransfer.dropEffect = disabled ? 'none' : 'copy';
                }
            },
            onDragLeave: (event) => {
                if (hasFiles(event.dataTransfer)) {
                    setDragDepth((depth) => Math.max(0, depth - 1));
                }
            },
            onDrop: (event) => {
                if (hasFiles(event.dataTransfer)) {
                    event.preventDefault();
                    setDragDepth(0);
                    add(Array.from(event.dataTransfer.files));
                }
            },
        },
    };
}
