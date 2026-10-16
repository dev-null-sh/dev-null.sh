import { ImagePlus, X } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { ImageViewer } from '@/components/image-viewer';
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

/**
 * The first validation error the server gave for the images, whichever image it was about.
 */
export function imageErrorFrom(
    errors: Record<string, string | undefined>,
): string | undefined {
    const key = Object.keys(errors).find(
        (name) => name === 'images' || name.startsWith('images.'),
    );

    return key ? errors[key] : undefined;
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
    const [viewing, setViewing] = useState<number | null>(null);
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
        <>
            <ul className="mt-2 flex gap-2" aria-label="Attached images">
                {urls.map((url, index) => (
                    <li key={url} className="relative">
                        <button
                            type="button"
                            onClick={() => setViewing(index)}
                            aria-label={`Preview image ${index + 1}`}
                            title="Preview"
                            className="block cursor-zoom-in overflow-hidden rounded-md border bg-muted transition focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            data-test="attached-image-preview-button"
                        >
                            <img
                                src={url}
                                alt={`Attached image ${index + 1}`}
                                className="size-20 object-cover transition-transform duration-200 motion-safe:hover:scale-105"
                            />
                        </button>
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

            <ImageViewer
                images={images.map((image, index) => ({
                    url: urls[index],
                    alt: `Attached image ${index + 1}`,
                    details: `${image.name} · ${formatBytes(image.size)}`,
                }))}
                index={viewing}
                onIndexChange={setViewing}
                onRemove={(index) => {
                    onRemove(index);
                    setViewing(
                        images.length <= 1
                            ? null
                            : Math.min(index, images.length - 2),
                    );
                }}
            />
        </>
    );
}

function formatBytes(bytes: number): string {
    if (bytes < 1024 * 1024) {
        return `${Math.max(1, Math.round(bytes / 1024))} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}
