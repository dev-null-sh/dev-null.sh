import { useEffect, useState } from 'react';
import { LinkEmbed } from '@/components/link-embed';
import { Skeleton } from '@/components/ui/skeleton';
import { hostname } from '@/lib/urls';
import type { LinkPreview as LinkPreviewData } from '@/types';

const POLL_DELAYS = [1000, 2000, 3000, 5000, 8000, 13000];

/**
 * A link is unfurled in the background after its commit or message is posted, so a pending preview is fetched again
 * from the refresh URL until the page behind it has been read, instead of only showing up after a reload.
 */
function useFetchedLinkPreview(
    refreshUrl: string,
    preview: LinkPreviewData,
): { preview: LinkPreviewData; isWaiting: boolean } {
    const [fetched, setFetched] = useState<LinkPreviewData | null>(null);
    const [hasGivenUp, setHasGivenUp] = useState(false);

    useEffect(() => {
        if (!preview.is_pending) {
            return;
        }

        const controller = new AbortController();
        let timeout = 0;

        const poll = async (attempt: number) => {
            try {
                const response = await fetch(refreshUrl, {
                    headers: { Accept: 'application/json' },
                    cache: 'no-store',
                    signal: controller.signal,
                });

                if (!response.ok) {
                    setHasGivenUp(true);

                    return;
                }

                const { link_preview } = (await response.json()) as {
                    link_preview: LinkPreviewData | null;
                };

                if (link_preview && !link_preview.is_pending) {
                    setFetched(link_preview);

                    return;
                }
            } catch {
                if (controller.signal.aborted) {
                    return;
                }
            }

            if (attempt + 1 < POLL_DELAYS.length) {
                timeout = window.setTimeout(
                    () => void poll(attempt + 1),
                    POLL_DELAYS[attempt + 1],
                );
            } else {
                setHasGivenUp(true);
            }
        };

        timeout = window.setTimeout(() => void poll(0), POLL_DELAYS[0]);

        return () => {
            window.clearTimeout(timeout);
            controller.abort();
        };
    }, [refreshUrl, preview.is_pending]);

    const current =
        preview.is_pending && fetched?.url === preview.url ? fetched : preview;

    return { preview: current, isWaiting: current.is_pending && !hasGivenUp };
}

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
}: {
    refreshUrl: string;
    preview: LinkPreviewData;
    isStatic?: boolean;
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

    if (isWaiting) {
        return <LinkPreviewSkeleton />;
    }

    if (!preview.title) {
        return null;
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
