import type { MouseEvent } from 'react';
import { useState } from 'react';
import { useScrambledHash } from '@/hooks/use-scrambled-hash';
import { commitPhotoUrl, openCommitPhoto } from '@/lib/commit-photo';
import { cn } from '@/lib/utils';
import type { Commit, CommitImage } from '@/types';

export function commitImageAlt(
    image: Pick<CommitImage, 'description'>,
    index: number,
    count: number,
): string {
    if (image.description) {
        return image.description;
    }

    return count > 1
        ? `Attached image ${index + 1} of ${count}`
        : 'Attached image';
}

export type ShownImage = Pick<CommitImage, 'id' | 'url' | 'description'> &
    Partial<Pick<CommitImage, 'width' | 'height'>>;

/**
 * Tall images in a grid cell stay contained so they aren't blown up to fill the width.
 * Landscape and square images cover the cell so the collage doesn't leave empty bands.
 */
export function gridImageObjectFit(
    image: Pick<ShownImage, 'width' | 'height'>,
): 'object-contain' | 'object-cover' {
    if (
        image.width !== undefined &&
        image.height !== undefined &&
        image.height > image.width
    ) {
        return 'object-contain';
    }

    return 'object-cover';
}

export function replaceFailedImage(image: HTMLImageElement): void {
    if (image.getAttribute('src') !== '/favicon.svg') {
        image.src = '/favicon.svg';
        image.dataset.fallback = '';
    }
}

function CommitImageLoading({ hash }: { hash: string }) {
    const characters = useScrambledHash(hash);

    return (
        <div
            aria-hidden="true"
            data-test="commit-image-loading"
            className="absolute inset-0 grid animate-in place-items-center font-mono delay-300 duration-300 fill-mode-both fade-in"
        >
            <div className="text-center">
                <div className="text-[0.625rem] tracking-wide text-muted-foreground/60">
                    commit
                </div>
                <div className="text-lg tracking-wider text-muted-foreground">
                    {characters.map(({ character, isLocked }, index) => (
                        <span
                            key={index}
                            className={cn(isLocked && 'text-primary')}
                        >
                            {character}
                        </span>
                    ))}
                </div>
            </div>
        </div>
    );
}

function CommitImageThumbnail({
    image,
    alt,
    className,
    isSingle,
}: {
    image: ShownImage;
    alt: string;
    className: string;
    isSingle: boolean;
}) {
    const [isLoaded, setIsLoaded] = useState(false);

    return (
        <>
            {!isLoaded && (
                <CommitImageLoading
                    hash={image.id.replace(/[^0-9a-f]/g, '').slice(-7)}
                />
            )}
            <img
                ref={(element) => {
                    if (element?.complete && element.naturalWidth > 0) {
                        setIsLoaded(true);
                    }
                }}
                src={image.url}
                alt={alt}
                width={image.width}
                height={image.height}
                loading="lazy"
                decoding="async"
                data-single-image={isSingle || undefined}
                onLoad={() => setIsLoaded(true)}
                onError={(event) => replaceFailedImage(event.currentTarget)}
                className={cn(
                    className,
                    'transition-opacity duration-300',
                    !isLoaded && 'opacity-0',
                )}
            />
        </>
    );
}

/**
 * A commit's images open in its photo viewer. Other images, like a message's, open wherever `onOpen` shows them.
 */
type CommitImagesProps =
    | { commit: Commit; images?: never; onOpen?: never }
    | {
          commit?: never;
          images: ShownImage[];
          onOpen?: (index: number) => void;
      };

const isModifiedClick = (event: MouseEvent): boolean =>
    event.metaKey || event.ctrlKey || event.shiftKey || event.altKey;

export function CommitImages({
    commit,
    images: draftImages,
    onOpen,
}: CommitImagesProps) {
    const images = commit ? (commit.images ?? []) : draftImages;

    if (images.length === 0) {
        return null;
    }

    const hasMultipleImages = images.length > 1;

    return (
        <div className="@container mt-3">
            <div
                className={cn(
                    'grid gap-0.5 overflow-hidden rounded-lg border',
                    {
                        'h-[max(18rem,calc(100cqw*288/530))] grid-cols-2':
                            hasMultipleImages,
                    },
                )}
                data-test="commit-images"
                data-image-grid={hasMultipleImages || undefined}
            >
                {images.map((image, index) => {
                    const className = cn('relative block min-h-0 bg-muted', {
                        'row-span-2': images.length === 3 && index === 0,
                    });
                    const thumbnail = (
                        <CommitImageThumbnail
                            image={image}
                            isSingle={!hasMultipleImages}
                            alt={commitImageAlt(image, index, images.length)}
                            className={cn(
                                'w-full',
                                hasMultipleImages
                                    ? cn('h-full', gridImageObjectFit(image))
                                    : 'max-h-[max(32rem,calc(100cqw*512/530))] object-contain',
                            )}
                        />
                    );

                    return commit ? (
                        <a
                            key={image.id}
                            href={commitPhotoUrl(commit, index)}
                            onClick={(event) => {
                                event.stopPropagation();

                                if (isModifiedClick(event)) {
                                    return;
                                }

                                event.preventDefault();
                                openCommitPhoto(commit, index);
                            }}
                            className={className}
                        >
                            {thumbnail}
                        </a>
                    ) : onOpen ? (
                        <a
                            key={image.id}
                            href={image.url}
                            target="_blank"
                            rel="noopener noreferrer"
                            onClick={(event) => {
                                if (isModifiedClick(event)) {
                                    return;
                                }

                                event.preventDefault();
                                onOpen(index);
                            }}
                            className={className}
                        >
                            {thumbnail}
                        </a>
                    ) : (
                        <div key={image.id} className={className}>
                            {thumbnail}
                        </div>
                    );
                })}
            </div>
        </div>
    );
}
