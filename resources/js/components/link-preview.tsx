import { Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import CommitController from '@/actions/App/Http/Controllers/CommitController';
import AppLogoIcon from '@/components/app-logo-icon';
import { LinkEmbed } from '@/components/link-embed';
import { Skeleton } from '@/components/ui/skeleton';
import { commitLinkTo, hostname } from '@/lib/urls';
import { shortHash } from '@/lib/utils';
import type { LinkPreview as LinkPreviewData } from '@/types';

const POLL_DELAYS = [1000, 2000, 3000, 5000, 8000, 13000];

// The picture a commit's page shares is the commit drawn as a card, which its own link card already shows as text.
const drawnCommitPattern = /\/og\.png(?:\?|$)/;

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

function LinkPreviewSkeleton({ isCommit }: { isCommit: boolean }) {
    return (
        <div
            aria-hidden
            className="mt-3 flex items-center overflow-hidden rounded-lg border"
            data-test="link-preview-skeleton"
        >
            {!isCommit && <Skeleton className="my-2 ml-2 size-20 shrink-0" />}
            <div className="flex min-w-0 flex-1 flex-col justify-center px-3 py-2">
                <span className="flex h-lh items-center text-[13px]">
                    <Skeleton className="h-3 w-24" />
                </span>
                {isCommit ? (
                    <span className="mt-0.5 flex h-[2lh] flex-col justify-around text-[15px] leading-snug">
                        <Skeleton className="h-3.5 w-full" />
                        <Skeleton className="h-3.5 w-1/2" />
                    </span>
                ) : (
                    <>
                        <span className="flex h-[2lh] flex-col justify-around text-[15px] leading-snug sm:h-lh">
                            <Skeleton className="h-3.5 w-full sm:w-2/3" />
                            <Skeleton className="h-3.5 w-2/3 sm:hidden" />
                        </span>
                        <span className="mt-0.5 flex h-[2lh] flex-col justify-around text-sm leading-snug">
                            <Skeleton className="h-3 w-full" />
                            <Skeleton className="h-3 w-1/2" />
                        </span>
                    </>
                )}
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
    const commitLink = commitLinkTo(preview.url);
    const imageUrl =
        isImageBroken ||
        (commitLink && drawnCommitPattern.test(preview.image_url ?? ''))
            ? null
            : preview.image_url;

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
        return <LinkPreviewSkeleton isCommit={commitLink !== null} />;
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
                {commitLink ? (
                    <>
                        <span className="flex items-center gap-1.5 font-mono text-[13px] text-muted-foreground">
                            <AppLogoIcon
                                aria-hidden
                                className="size-[1.15em] shrink-0 fill-foreground"
                            />
                            <span className="sr-only">
                                @{commitLink.handle}’s commit{' '}
                                {shortHash(commitLink.commit)}
                            </span>
                            <span aria-hidden className="truncate">
                                {commitLink.handle}/
                                {shortHash(commitLink.commit)}
                            </span>
                        </span>
                        {preview.description && (
                            <span className="mt-0.5 line-clamp-3 text-[15px] leading-snug wrap-break-word">
                                {preview.description}
                            </span>
                        )}
                    </>
                ) : (
                    <>
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
                    </>
                )}
            </div>
        </>
    );

    const linkClassName =
        'mt-3 flex items-center overflow-hidden rounded-lg border transition-colors hover:bg-accent focus-visible:bg-accent';

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

    if (commitLink) {
        return (
            <Link
                href={CommitController.show({
                    user: commitLink.handle,
                    commit: commitLink.commit,
                })}
                onClick={(event) => event.stopPropagation()}
                className={linkClassName}
                data-test="link-preview"
            >
                {content}
            </Link>
        );
    }

    return (
        <a
            href={preview.url}
            target="_blank"
            rel="noopener noreferrer nofollow ugc"
            onClick={(event) => event.stopPropagation()}
            className={linkClassName}
            data-test="link-preview"
        >
            {content}
        </a>
    );
}
