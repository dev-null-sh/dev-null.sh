import type { ClipboardEvent, DragEvent } from 'react';
import { useEffect, useRef, useState } from 'react';
import { pickImages } from '@/components/commit-image-picker';
import { convertGif } from '@/lib/gif/convert';

export type UseImageAttachmentsReturn = {
    readonly images: File[];
    readonly descriptions: string[];
    readonly posters: (File | null)[];
    readonly converting: string | null;
    readonly cancel: () => void;
    readonly error: string | null;
    readonly attach: (files: File[]) => void;
    readonly describe: (index: number, description: string) => void;
    readonly remove: (index: number) => void;
    readonly clear: () => void;
    readonly onPaste: (event: ClipboardEvent<HTMLElement>) => void;
    readonly onDragOver: (event: DragEvent<HTMLElement>) => void;
    readonly onDrop: (event: DragEvent<HTMLElement>) => void;
};

const hasFiles = (dataTransfer: DataTransfer): boolean =>
    Array.from(dataTransfer.types).includes('Files');

/**
 * Keeps the images attached to a commit being written, picked, pasted, or dropped, and the description written for each.
 */
export function useImageAttachments(
    images: File[],
    descriptions: string[],
    onChange: (
        images: File[],
        descriptions: string[],
        posters?: (File | null)[],
    ) => void,
    posters: (File | null)[] = [],
): UseImageAttachmentsReturn {
    const [error, setError] = useState<string | null>(null);
    const [converting, setConverting] = useState<string | null>(null);
    const active = useRef<AbortController | null>(null);
    const draft = useRef({ images, descriptions, posters, onChange });
    useEffect(() => {
        draft.current = { images, descriptions, posters, onChange };
    }, [images, descriptions, posters, onChange]);
    useEffect(
        () => () => {
            const controller = active.current;
            active.current = null;
            controller?.abort();
        },
        [],
    );
    const cancel = () => {
        active.current?.abort();
        active.current = null;
        setConverting(null);
    };

    const attach = (files: File[]) => {
        if (files.length === 0 || active.current) {
            return;
        }

        const picked = pickImages(images, files);

        setError(picked.error);
        const additions = picked.images.slice(images.length);
        const controller = new AbortController();
        active.current = controller;
        setConverting('Preparing attachments…');
        void (async () => {
            for (const original of additions) {
                if (controller.signal.aborted) break;
                try {
                    const signature = await original.slice(0, 6).text();
                    const result =
                        signature === 'GIF87a' || signature === 'GIF89a'
                            ? await convertGif(
                                  original,
                                  controller.signal,
                                  setConverting,
                              )
                            : { file: original, poster: undefined };
                    if (controller.signal.aborted) break;
                    const current = draft.current;
                    const next = {
                        ...current,
                        images: [...current.images, result.file],
                        descriptions: current.images
                            .map(
                                (_, index) => current.descriptions[index] ?? '',
                            )
                            .concat(''),
                        posters: current.images
                            .map((_, index) => current.posters[index] ?? null)
                            .concat(result.poster ?? null),
                    };
                    draft.current = next;
                    current.onChange(
                        next.images,
                        next.descriptions,
                        next.posters,
                    );
                } catch (error) {
                    if (!controller.signal.aborted)
                        setError(
                            error instanceof Error
                                ? error.message
                                : 'Unable to prepare attachment.',
                        );
                }
            }
        })().finally(() => {
            if (active.current === controller) {
                active.current = null;
                setConverting(null);
            }
        });
    };

    return {
        images,
        descriptions,
        posters,
        converting,
        cancel,
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
                posters,
            );
        },
        remove: (index) => {
            onChange(
                images.filter((_, position) => position !== index),
                descriptions.filter((_, position) => position !== index),
                posters.filter((_, position) => position !== index),
            );
            setError(null);
        },
        clear: () => {
            cancel();
            onChange([], [], []);
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
