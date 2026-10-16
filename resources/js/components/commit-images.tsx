import { viewImages } from '@/components/image-viewer';
import { cn } from '@/lib/utils';
import type { CommitImage } from '@/types';

export function CommitImages({ images }: { images: CommitImage[] }) {
    if (images.length === 0) {
        return null;
    }

    return (
        <div
            className={cn(
                'mt-3 grid gap-0.5 overflow-hidden rounded-lg border',
                {
                    'h-72 grid-cols-2': images.length > 1,
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
                            event.metaKey ||
                            event.ctrlKey ||
                            event.shiftKey ||
                            event.button !== 0
                        ) {
                            return;
                        }

                        event.preventDefault();
                        viewImages(
                            images.map((image, position) => ({
                                url: image.url,
                                alt: altFor(position, images.length),
                            })),
                            index,
                        );
                    }}
                    className={cn('block min-h-0 cursor-zoom-in bg-muted', {
                        'row-span-2': images.length === 3 && index === 0,
                    })}
                >
                    <img
                        src={image.url}
                        alt={altFor(index, images.length)}
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
                            images.length === 1 ? 'max-h-[32rem]' : 'h-full',
                        )}
                    />
                </a>
            ))}
        </div>
    );
}

function altFor(index: number, count: number): string {
    return count === 1
        ? 'Attached image'
        : `Attached image ${index + 1} of ${count}`;
}
