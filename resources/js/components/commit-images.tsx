import { commitPhotoUrl, openCommitPhoto } from '@/lib/commit-photo';
import { cn } from '@/lib/utils';
import type { Commit, CommitImage } from '@/types';

export function commitImageAlt(
    image: CommitImage,
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

export function CommitImages({ commit }: { commit: Commit }) {
    const images = commit.images ?? [];

    if (images.length === 0) {
        return null;
    }

    const hasMultipleImages = images.length > 1;

    return (
        <div
            className={cn(
                'mt-3 grid gap-0.5 overflow-hidden rounded-lg border',
                {
                    'h-72 grid-cols-2': hasMultipleImages,
                },
            )}
            data-test="commit-images"
        >
            {images.map((image, index) => (
                <a
                    key={image.id}
                    href={commitPhotoUrl(commit, index)}
                    onClick={(event) => {
                        event.stopPropagation();

                        if (
                            event.metaKey ||
                            event.ctrlKey ||
                            event.shiftKey ||
                            event.altKey
                        ) {
                            return;
                        }

                        event.preventDefault();
                        openCommitPhoto(commit, index);
                    }}
                    className={cn('block min-h-0 bg-muted', {
                        'row-span-2': images.length === 3 && index === 0,
                    })}
                >
                    <img
                        src={image.url}
                        alt={commitImageAlt(image, index, images.length)}
                        width={image.width}
                        height={image.height}
                        loading="lazy"
                        onError={(event) => {
                            const image = event.currentTarget;
                            if (image.getAttribute('src') !== '/favicon.svg') {
                                image.src = '/favicon.svg';
                                image.classList.replace(
                                    'object-cover',
                                    'object-contain',
                                );
                            }
                        }}
                        className={cn(
                            'w-full object-cover',
                            hasMultipleImages ? 'h-full' : 'max-h-[32rem]',
                        )}
                    />
                </a>
            ))}
        </div>
    );
}
