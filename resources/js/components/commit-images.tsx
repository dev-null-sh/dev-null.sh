import { createContext, useContext } from 'react';
import { cn } from '@/lib/utils';
import type { Commit } from '@/types';

export const CommitImageViewerContext = createContext<
    ((commit: Commit, index: number) => void) | null
>(null);

export function CommitImages({ commit }: { commit: Commit }) {
    const openViewer = useContext(CommitImageViewerContext);
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
                    href={image.url}
                    target="_blank"
                    rel="noopener noreferrer"
                    onClick={(event) => {
                        event.stopPropagation();

                        if (
                            !openViewer ||
                            event.metaKey ||
                            event.ctrlKey ||
                            event.shiftKey ||
                            event.altKey
                        ) {
                            return;
                        }

                        event.preventDefault();
                        openViewer(commit, index);
                    }}
                    className={cn('block min-h-0 bg-muted', {
                        'row-span-2': images.length === 3 && index === 0,
                    })}
                >
                    <img
                        src={image.url}
                        alt={
                            hasMultipleImages
                                ? `Attached image ${index + 1} of ${images.length}`
                                : 'Attached image'
                        }
                        width={image.width}
                        height={image.height}
                        loading="lazy"
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
