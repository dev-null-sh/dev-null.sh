import { useState } from 'react';
import Lightbox from 'yet-another-react-lightbox';
import Counter from 'yet-another-react-lightbox/plugins/counter';
import Zoom from 'yet-another-react-lightbox/plugins/zoom';
import 'yet-another-react-lightbox/plugins/counter.css';
import 'yet-another-react-lightbox/styles.css';
import { cn } from '@/lib/utils';
import type { CommitImage } from '@/types';

export function CommitImages({ images }: { images: CommitImage[] }) {
    const [openIndex, setOpenIndex] = useState<number | null>(null);

    if (images.length === 0) {
        return null;
    }

    const hasMultipleImages = images.length > 1;

    return (
        <>
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
                                event.metaKey ||
                                event.ctrlKey ||
                                event.shiftKey ||
                                event.altKey
                            ) {
                                return;
                            }

                            event.preventDefault();
                            setOpenIndex(index);
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

            <Lightbox
                open={openIndex !== null}
                index={openIndex ?? 0}
                close={() => setOpenIndex(null)}
                slides={images.map((image) => ({
                    src: image.url,
                    width: image.width,
                    height: image.height,
                }))}
                plugins={hasMultipleImages ? [Counter, Zoom] : [Zoom]}
                carousel={{ finite: true }}
                controller={{
                    closeOnBackdropClick: true,
                    closeOnPullDown: true,
                }}
                render={
                    hasMultipleImages
                        ? undefined
                        : { buttonPrev: () => null, buttonNext: () => null }
                }
            />
        </>
    );
}
