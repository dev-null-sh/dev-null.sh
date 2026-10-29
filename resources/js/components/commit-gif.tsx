import { X } from 'lucide-react';
import { gifProviders } from '@/lib/gif-providers';
import type { GifAttachment } from '@/lib/gif-providers';
import { cn } from '@/lib/utils';
import type { CommitGif } from '@/types/commit';

/** An attached GIF, hotlinked verbatim with its required attribution. */
export function CommitGif({
    gif,
    className,
}: {
    gif: CommitGif;
    className?: string;
}) {
    const provider = gifProviders[gif.provider] ?? gifProviders.giphy;
    const credit =
        gif.username !== null
            ? `GIF by @${gif.username} on ${provider.label}`
            : `via ${provider.label}`;

    return (
        <figure
            className={cn(
                'relative mt-3 overflow-hidden rounded-lg border',
                className,
            )}
            data-test="commit-gif"
        >
            <img
                src={gif.url}
                alt={gif.title ?? 'Attached GIF'}
                width={gif.width ?? undefined}
                height={gif.height ?? undefined}
                loading="lazy"
                referrerPolicy="no-referrer"
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
                className="max-h-[32rem] w-full bg-muted object-cover"
            />
            <a
                href={gif.page_url}
                target="_blank"
                rel="noopener noreferrer"
                onClick={(event) => event.stopPropagation()}
                className="absolute right-1.5 bottom-1.5 rounded bg-background/80 px-1.5 py-0.5 text-[11px] font-medium text-foreground backdrop-blur transition hover:bg-background"
            >
                {credit}
            </a>
        </figure>
    );
}

export function GifAttachmentPreview({
    gif,
    onRemove,
}: {
    gif: GifAttachment | null;
    onRemove: () => void;
}) {
    if (gif === null) {
        return null;
    }

    return (
        <div className="mt-2">
            <div
                className="relative inline-block"
                data-test="gif-attachment-preview"
            >
                <img
                    src={gif.url}
                    alt={gif.title ?? 'Attached GIF'}
                    referrerPolicy="no-referrer"
                    className="h-20 max-w-48 rounded-md border bg-muted object-cover"
                />
                <button
                    type="button"
                    onClick={onRemove}
                    aria-label="Remove GIF"
                    className="absolute top-1 right-1 rounded-full bg-background/80 p-0.5 text-foreground backdrop-blur transition hover:bg-background"
                >
                    <X className="size-3.5" />
                </button>
                <span className="absolute bottom-1 left-1 rounded bg-background/80 px-1 text-[11px] leading-4 font-bold tracking-wide text-foreground backdrop-blur">
                    GIF
                </span>
            </div>
        </div>
    );
}
