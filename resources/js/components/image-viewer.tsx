import * as DialogPrimitive from '@radix-ui/react-dialog';
import { ChevronLeft, ChevronRight, Trash2, X } from 'lucide-react';
import type { ReactNode } from 'react';
import { useEffect, useRef, useState } from 'react';
import { cn } from '@/lib/utils';

export type ViewerImage = {
    url: string;
    alt: string;
    details?: string;
};

const swipeThreshold = 50;
const openEvent = 'devnull:view-images';

type OpenRequest = { images: ViewerImage[]; index: number };

/**
 * Opens the shared viewer from anywhere, without the caller holding any state.
 */
export function viewImages(images: ViewerImage[], index: number): void {
    window.dispatchEvent(
        new CustomEvent<OpenRequest>(openEvent, { detail: { images, index } }),
    );
}

/**
 * The one viewer that posted images open in, mounted once for the whole app.
 */
export function ImageViewerHost() {
    const [request, setRequest] = useState<OpenRequest | null>(null);

    useEffect(() => {
        const open = (event: Event) =>
            setRequest((event as CustomEvent<OpenRequest>).detail);

        window.addEventListener(openEvent, open);

        return () => window.removeEventListener(openEvent, open);
    }, []);

    return (
        <ImageViewer
            images={request?.images ?? []}
            index={request?.index ?? null}
            onIndexChange={(index) =>
                setRequest((current) =>
                    index === null || current === null
                        ? null
                        : { ...current, index },
                )
            }
        />
    );
}

/**
 * A focused look at images: dims the page instead of reflowing it, fills the screen on phones, and steps through
 * images with the arrows, ←/→, or a swipe. Esc, the close button, or a click beside the image returns to the page.
 */
export function ImageViewer({
    images,
    index,
    onIndexChange,
    onRemove,
}: {
    images: ViewerImage[];
    index: number | null;
    onIndexChange: (index: number | null) => void;
    onRemove?: (index: number) => void;
}) {
    const swipeStart = useRef<number | null>(null);
    const [dimensions, setDimensions] = useState<string | null>(null);
    const isOpen = index !== null && index < images.length;
    const current = isOpen ? index : 0;
    const image = images[current] as ViewerImage | undefined;
    const hasMany = images.length > 1;

    const step = (by: 1 | -1) => {
        if (!isOpen || !hasMany) {
            return;
        }

        onIndexChange((current + by + images.length) % images.length);
    };

    return (
        <DialogPrimitive.Root
            open={isOpen}
            onOpenChange={(open) => {
                if (!open) {
                    onIndexChange(null);
                }
            }}
        >
            <DialogPrimitive.Portal>
                <DialogPrimitive.Overlay className="fixed inset-0 z-50 bg-black/85 backdrop-blur-sm data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:animate-in data-[state=open]:fade-in-0 motion-reduce:animate-none" />
                <DialogPrimitive.Content
                    onKeyDown={(event) => {
                        if (event.key === 'ArrowRight') {
                            event.preventDefault();
                            step(1);
                        } else if (event.key === 'ArrowLeft') {
                            event.preventDefault();
                            step(-1);
                        }
                    }}
                    onPointerDown={(event) => {
                        swipeStart.current = event.clientX;
                    }}
                    onPointerUp={(event) => {
                        if (swipeStart.current === null) {
                            return;
                        }

                        const distance = event.clientX - swipeStart.current;
                        swipeStart.current = null;

                        if (
                            event.pointerType !== 'mouse' &&
                            Math.abs(distance) > swipeThreshold
                        ) {
                            step(distance < 0 ? 1 : -1);
                        }
                    }}
                    className="fixed inset-0 z-50 flex flex-col text-white outline-none data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=closed]:zoom-out-95 data-[state=open]:animate-in data-[state=open]:fade-in-0 data-[state=open]:zoom-in-95 motion-reduce:animate-none sm:inset-6 md:inset-10"
                    data-test="image-viewer"
                >
                    <DialogPrimitive.Title className="sr-only">
                        Image {current + 1} of {images.length}
                    </DialogPrimitive.Title>
                    <DialogPrimitive.Description className="sr-only">
                        Use the arrow keys to switch images, and Escape to
                        close.
                    </DialogPrimitive.Description>

                    <header className="flex items-center gap-3 px-4 pt-[max(1rem,env(safe-area-inset-top))] pb-3 font-mono text-[13px] text-white/70">
                        <span
                            className="text-white tabular-nums"
                            data-test="image-viewer-position"
                        >
                            {current + 1}/{images.length}
                        </span>
                        <span className="min-w-0 truncate">
                            {[image?.details, dimensions]
                                .filter(Boolean)
                                .join(' · ')}
                        </span>
                        <DialogPrimitive.Close
                            className="ml-auto rounded-full p-2 text-white/80 transition hover:bg-white/10 hover:text-white focus-visible:ring-2 focus-visible:ring-white/60 focus-visible:outline-none"
                            aria-label="Close"
                        >
                            <X className="size-5" />
                        </DialogPrimitive.Close>
                    </header>

                    <div
                        className="relative flex min-h-0 flex-1 items-center justify-center px-2 sm:px-16"
                        onClick={(event) => {
                            if (event.target === event.currentTarget) {
                                onIndexChange(null);
                            }
                        }}
                    >
                        {image && (
                            <img
                                key={image.url}
                                src={image.url}
                                alt={image.alt}
                                onLoad={(event) =>
                                    setDimensions(
                                        `${event.currentTarget.naturalWidth}×${event.currentTarget.naturalHeight}`,
                                    )
                                }
                                draggable={false}
                                className="max-h-full max-w-full rounded-md object-contain shadow-2xl select-none motion-safe:animate-in motion-safe:duration-200 motion-safe:fade-in-0 motion-safe:zoom-in-[0.97]"
                                data-test="image-viewer-image"
                            />
                        )}

                        {hasMany && (
                            <>
                                <ViewerArrow
                                    label="Previous image"
                                    onClick={() => step(-1)}
                                    className="left-2 sm:left-3"
                                >
                                    <ChevronLeft className="size-5" />
                                </ViewerArrow>
                                <ViewerArrow
                                    label="Next image"
                                    onClick={() => step(1)}
                                    className="right-2 sm:right-3"
                                >
                                    <ChevronRight className="size-5" />
                                </ViewerArrow>
                            </>
                        )}
                    </div>

                    <footer className="flex min-h-14 items-center justify-center gap-4 px-4 pt-3 pb-[max(1rem,env(safe-area-inset-bottom))]">
                        {hasMany && (
                            <div className="flex gap-1.5" aria-hidden>
                                {images.map((dot, position) => (
                                    <span
                                        key={dot.url}
                                        className={cn(
                                            'h-1.5 rounded-full bg-white/40 transition-all duration-200',
                                            position === current
                                                ? 'w-4 bg-white'
                                                : 'w-1.5',
                                        )}
                                    />
                                ))}
                            </div>
                        )}
                        {onRemove && (
                            <button
                                type="button"
                                onClick={() => onRemove(current)}
                                className="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-sm text-white/80 transition hover:bg-white/10 hover:text-white focus-visible:ring-2 focus-visible:ring-white/60 focus-visible:outline-none"
                                data-test="image-viewer-remove"
                            >
                                <Trash2 className="size-4" />
                                Remove
                            </button>
                        )}
                    </footer>
                </DialogPrimitive.Content>
            </DialogPrimitive.Portal>
        </DialogPrimitive.Root>
    );
}

function ViewerArrow({
    label,
    onClick,
    className,
    children,
}: {
    label: string;
    onClick: () => void;
    className?: string;
    children: ReactNode;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            aria-label={label}
            title={label}
            className={cn(
                'absolute top-1/2 hidden -translate-y-1/2 rounded-full bg-black/50 p-2.5 text-white/90 backdrop-blur transition hover:bg-black/70 hover:text-white focus-visible:ring-2 focus-visible:ring-white/60 focus-visible:outline-none sm:inline-flex',
                className,
            )}
        >
            {children}
        </button>
    );
}
