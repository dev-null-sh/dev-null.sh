import { cn } from '@/lib/utils';
import type { CommitImage } from '@/types';

type ShownImage = Pick<CommitImage, 'id' | 'url'> &
    Partial<Pick<CommitImage, 'width' | 'height'>>;

export function CommitImages({ images }: { images: ShownImage[] }) {
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
                    onClick={(event) => event.stopPropagation()}
                    className={cn('block min-h-0 bg-muted', {
                        'row-span-2': images.length === 3 && index === 0,
                    })}
                >
                    <img
                        src={image.url}
                        alt={
                            images.length === 1
                                ? 'Attached image'
                                : `Attached image ${index + 1} of ${images.length}`
                        }
                        width={image.width}
                        height={image.height}
                        loading="lazy"
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
