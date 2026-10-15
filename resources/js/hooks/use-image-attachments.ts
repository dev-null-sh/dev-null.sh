import type { ClipboardEvent, DragEvent } from 'react';
import { useState } from 'react';
import { pickImages } from '@/components/commit-image-picker';

export type UseImageAttachmentsReturn = {
    readonly error: string | null;
    readonly attach: (files: File[]) => void;
    readonly remove: (index: number) => void;
    readonly clear: () => void;
    readonly onPaste: (event: ClipboardEvent<HTMLElement>) => void;
    readonly onDragOver: (event: DragEvent<HTMLElement>) => void;
    readonly onDrop: (event: DragEvent<HTMLElement>) => void;
};

const hasFiles = (dataTransfer: DataTransfer): boolean =>
    Array.from(dataTransfer.types).includes('Files');

/**
 * Attaches images to a commit being written, picked, pasted, or dropped.
 */
export function useImageAttachments(
    images: File[],
    onChange: (images: File[]) => void,
): UseImageAttachmentsReturn {
    const [error, setError] = useState<string | null>(null);

    const attach = (files: File[]) => {
        if (files.length === 0) {
            return;
        }

        const picked = pickImages(images, files);

        onChange(picked.images);
        setError(picked.error);
    };

    return {
        error,
        attach,
        remove: (index) => {
            onChange(images.filter((_, position) => position !== index));
            setError(null);
        },
        clear: () => {
            onChange([]);
            setError(null);
        },
        onPaste: (event) => {
            const files = Array.from(event.clipboardData.files);

            if (files.length > 0) {
                event.preventDefault();
                attach(files);
            }
        },
        onDragOver: (event) => {
            if (hasFiles(event.dataTransfer)) {
                event.preventDefault();
            }
        },
        onDrop: (event) => {
            if (hasFiles(event.dataTransfer)) {
                event.preventDefault();
                attach(Array.from(event.dataTransfer.files));
            }
        },
    };
}
