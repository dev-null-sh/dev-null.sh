import type { ClipboardEvent, DragEvent } from 'react';
import { useState } from 'react';
import { pickImages } from '@/components/commit-image-picker';

export type UseImageAttachmentsReturn = {
    readonly images: File[];
    readonly descriptions: string[];
    readonly error: string | null;
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
): UseImageAttachmentsReturn {
    const [error, setError] = useState<string | null>(null);
    const [dragDepth, setDragDepth] = useState(0);

    const attach = (files: File[]) => {
        if (files.length === 0) {
            return;
        }

        const picked = pickImages(images, files);

        onChange(
            picked.images,
            picked.images.map((_, index) => descriptions[index] ?? ''),
        );
        setError(picked.error);
    };

    return {
        images,
        descriptions,
        error,
        attach,
        describe: (index, description) => {
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
            onChange(
                images.filter((_, position) => position !== index),
                descriptions.filter((_, position) => position !== index),
            );
            setError(null);
        },
        clear: () => {
            onChange([], []);
            setError(null);
        },
        onPaste: (event) => {
            const files = Array.from(event.clipboardData.files);

            if (files.length > 0) {
                event.preventDefault();
                attach(files);
            }
        },
        isDraggingFiles: dragDepth > 0,
        dropzone: {
            onDragEnter: (event) => {
                if (hasFiles(event.dataTransfer)) {
                    event.preventDefault();
                    setDragDepth((depth) => depth + 1);
                }
            },
            onDragOver: (event) => {
                if (hasFiles(event.dataTransfer)) {
                    event.preventDefault();
                    event.dataTransfer.dropEffect = 'copy';
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
                    attach(Array.from(event.dataTransfer.files));
                }
            },
        },
    };
}
