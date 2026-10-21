import { useState, type ReactNode } from 'react';
import { GifEmbed } from '@/components/gif-embed';
import { LinkEmbed } from '@/components/link-embed';
import { Skeleton } from '@/components/ui/skeleton';
import { useFetchedLinkPreview } from '@/hooks/use-fetched-link-preview';
import { isGifUrl } from '@/lib/gifs';
import { hostname } from '@/lib/urls';
import type { LinkPreview as LinkPreviewData } from '@/types';

function LinkPreviewSkeleton() {
    return (
        <div
            aria-hidden
            className="mt-3 flex items-center overflow-hidden rounded-lg border"
            data-test="link-preview-skeleton"
        >
            <Skeleton className="my-2 ml-2 size-20 shrink-0" />
            <div className="flex min-w-0 flex-1 flex-col justify-center px-3 py-2">
                <span className="flex h-lh items-center text-[13px]">
                    <Skeleton className="h-3 w-24" />
                </span>
                <span className="flex h-[2lh] flex-col justify-around text-[15px] leading-snug sm:h-lh">
                    <Skeleton className="h-3.5 w-full sm:w-2/3" />
                    <Skeleton className="h-3.5 w-2/3 sm:hidden" />
                </span>
                <span className="mt-0.5 flex h-[2lh] flex-col justify-around text-sm leading-snug">
                    <Skeleton className="h-3 w-full" />
                    <Skeleton className="h-3 w-1/2" />
                </span>
            </div>
        </div>
    );
}

export function LinkPreview({
    refreshUrl,
    preview: initialPreview,
    isStatic = false,
    emptyState = null,
}: {
    refreshUrl: string;
    preview: LinkPreviewData;
    isStatic?: boolean;
    emptyState?: ReactNode;
}) {
    const { preview, isWaiting } = useFetchedLinkPreview(
        refreshUrl,
        initialPreview,
    );
    const [isImageBroken, setIsImageBroken] = useState(false);
    const imageUrl = isImageBroken ? null : preview.image_url;

    if (preview.embed) {
        return (
            <LinkEmbed
                preview={preview}
                embed={preview.embed}
                isStatic={isStatic}
            />
        );
    }

    if (isGifUrl(preview.url)) {
        return (
            <GifEmbed key={preview.url} preview={preview} isStatic={isStatic} />
        );
    }

    if (isWaiting) {
        return <LinkPreviewSkeleton />;
    }

    if (!preview.title) {
        return emptyState;
    }

    const content = (
        <>
            {imageUrl && (
                <img
                    src={imageUrl}
                    alt=""
                    loading="lazy"
                    referrerPolicy="no-referrer"
                    onError={() => setIsImageBroken(true)}
                    className="my-2 ml-2 size-20 shrink-0 rounded-md bg-muted object-contain"
                />
            )}

            <div className="flex min-w-0 flex-1 flex-col justify-center px-3 py-2">
                <span className="truncate font-mono text-[13px] text-muted-foreground">
                    {preview.site_name ?? hostname(preview.url)}
                </span>
                <span className="line-clamp-2 text-[15px] leading-snug font-medium wrap-break-word">
                    {preview.title}
                </span>
                {preview.description && (
                    <span className="mt-0.5 line-clamp-2 text-sm leading-snug wrap-break-word text-muted-foreground">
                        {preview.description}
                    </span>
                )}
            </div>
        </>
    );

    if (isStatic) {
        return (
            <div
                className="mt-3 flex items-center overflow-hidden rounded-lg border"
                data-test="link-preview"
            >
                {content}
            </div>
        );
    }

    return (
        <a
            href={preview.url}
            target="_blank"
            rel="noopener noreferrer nofollow ugc"
            onClick={(event) => event.stopPropagation()}
            className="mt-3 flex items-center overflow-hidden rounded-lg border transition-colors hover:bg-accent focus-visible:bg-accent"
            data-test="link-preview"
        >
            {content}
        </a>
    );
}
