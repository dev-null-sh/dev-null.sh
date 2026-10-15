import { ImagePlus, X } from 'lucide-react';
import { useEffect, useMemo, useRef } from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

export const maxImages = 3;

const maxImageBytes = 10 * 1024 * 1024;
const acceptedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

/**
 * Keeps the files the server would accept, and tells why any others were left out.
 */
export function pickImages(
    current: File[],
    picked: File[],
): { images: File[]; error: string | null } {
    const accepted = picked.filter(
        (file) =>
            acceptedTypes.includes(file.type) && file.size <= maxImageBytes,
    );
    const images = [...current, ...accepted].slice(0, maxImages);

    if (accepted.length < picked.length) {
        return {
            images,
            error: 'Only JPEG, PNG, GIF, or WebP images up to 10 MB.',
        };
    }

    if (current.length + accepted.length > maxImages) {
        return { images, error: `Up to ${maxImages} images per commit.` };
    }

    return { images, error: null };
}

export function ImagePickerButton({
    count,
    disabled,
    onPick,
    className,
}: {
    count: number;
    disabled?: boolean;
    onPick: (files: File[]) => void;
    className?: string;
}) {
    const inputRef = useRef<HTMLInputElement>(null);

    return (
        <>
            <input
                ref={inputRef}
                type="file"
                accept={acceptedTypes.join(',')}
                multiple
                hidden
                onChange={(event) => {
                    onPick(Array.from(event.target.files ?? []));
                    event.target.value = '';
                }}
                data-test="commit-image-input"
            />
            <Button
                type="button"
                variant="ghost"
                size="icon"
                disabled={disabled || count >= maxImages}
                onClick={() => inputRef.current?.click()}
                aria-label="Attach images"
                title={`Attach up to ${maxImages} images`}
                className={cn('text-muted-foreground', className)}
                data-test="attach-images-button"
            >
                <ImagePlus className="size-5" />
            </Button>
        </>
    );
}

export function ImagePickerPreviews({
    images,
    onRemove,
}: {
    images: File[];
    onRemove: (index: number) => void;
}) {
    const urls = useMemo(
        () => images.map((image) => URL.createObjectURL(image)),
        [images],
    );

    useEffect(
        () => () => urls.forEach((url) => URL.revokeObjectURL(url)),
        [urls],
    );

    if (images.length === 0) {
        return null;
    }

    return (
        <ul className="mt-2 flex gap-2" aria-label="Attached images">
            {urls.map((url, index) => (
                <li key={url} className="relative">
                    <img
                        src={url}
                        alt={`Attached image ${index + 1}`}
                        className="size-20 rounded-md border bg-muted object-cover"
                    />
                    <button
                        type="button"
                        onClick={() => onRemove(index)}
                        aria-label={`Remove image ${index + 1}`}
                        className="absolute top-1 right-1 rounded-full bg-background/80 p-0.5 text-foreground backdrop-blur transition hover:bg-background"
                    >
                        <X className="size-3.5" />
                    </button>
                </li>
            ))}
        </ul>
    );
}
