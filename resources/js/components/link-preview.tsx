import { useEffect, useState, type ReactNode } from 'react';
import { GifEmbed } from '@/components/gif-embed';
import { LinkEmbed } from '@/components/link-embed';
import { Skeleton } from '@/components/ui/skeleton';
import { isGifUrl } from '@/lib/gifs';
import { hostname } from '@/lib/urls';
import type { LinkPreview as LinkPreviewData } from '@/types';

const POLL_DELAYS = [1000, 2000, 3000, 5000, 8000, 13000];
const MAX_RETRY_AFTER_MS = 60_000;
const MILLISECONDS_PER_SECOND = 1000;
const HTTP_TOO_MANY_REQUESTS = 429;

type PollState = {
    refreshUrl: string;
    url: string;
    isPending: boolean;
    fetched: LinkPreviewData | null;
    hasGivenUp: boolean;
};

function retryAfterDelay(response: Response, fallback: number): number | null {
    const retryAfter = response.headers.get('Retry-After');

    if (retryAfter === null) {
        return fallback;
    }

    const seconds = Number(retryAfter);

    if (Number.isFinite(seconds)) {
        return retryAfterMilliseconds(
            seconds * MILLISECONDS_PER_SECOND,
            fallback,
        );
    }

    const date = Date.parse(retryAfter);

    if (Number.isNaN(date)) {
        return fallback;
    }

    return retryAfterMilliseconds(Math.max(date - Date.now(), 0), fallback);
}

function retryAfterMilliseconds(
    delay: number,
    fallback: number,
): number | null {
    if (delay > MAX_RETRY_AFTER_MS) {
        return null;
    }

    return Math.max(fallback, delay);
}

function isCurrentPollCycle(
    state: PollState,
    refreshUrl: string,
    url: string,
    isPending: boolean,
): boolean {
    return (
        state.refreshUrl === refreshUrl &&
        state.url === url &&
        state.isPending === isPending
    );
}

function syncPollState(
    state: PollState,
    refreshUrl: string,
    url: string,
    isPending: boolean,
): PollState {
    if (isCurrentPollCycle(state, refreshUrl, url, isPending)) {
        return state;
    }

    return {
        refreshUrl,
        url,
        isPending,
        fetched: null,
        hasGivenUp: false,
    };
}

function useFetchedLinkPreview(
    refreshUrl: string,
    preview: LinkPreviewData,
): { preview: LinkPreviewData; isWaiting: boolean } {
    const { url, is_pending: isPending } = preview;
    const [pollState, setPollState] = useState<PollState>({
        refreshUrl,
        url,
        isPending,
        fetched: null,
        hasGivenUp: false,
    });
    const currentPollState = syncPollState(
        pollState,
        refreshUrl,
        url,
        isPending,
    );

    if (currentPollState !== pollState) {
        setPollState(currentPollState);
    }

    useEffect(() => {
        if (!isPending) {
            return;
        }

        const controller = new AbortController();
        let timeout = 0;
        let isCurrent = true;

        const hasBeenReplaced = () => !isCurrent || controller.signal.aborted;
        const giveUp = () => {
            setPollState((state) => {
                if (!isCurrentPollCycle(state, refreshUrl, url, isPending)) {
                    return state;
                }

                return { ...state, hasGivenUp: true };
            });
        };

        const poll = async (attempt: number) => {
            let delay = POLL_DELAYS[attempt + 1];

            try {
                const response = await fetch(refreshUrl, {
                    headers: { Accept: 'application/json' },
                    cache: 'no-store',
                    signal: controller.signal,
                });

                if (hasBeenReplaced()) {
                    return;
                }

                if (!response.ok) {
                    if (
                        response.status !== HTTP_TOO_MANY_REQUESTS ||
                        delay === undefined
                    ) {
                        giveUp();

                        return;
                    }

                    const retryAfter = retryAfterDelay(response, delay);

                    if (retryAfter === null) {
                        giveUp();

                        return;
                    }

                    delay = retryAfter;
                } else {
                    const { link_preview } = (await response.json()) as {
                        link_preview: LinkPreviewData | null;
                    };

                    if (hasBeenReplaced()) {
                        return;
                    }

                    if (link_preview && !link_preview.is_pending) {
                        setPollState((state) => {
                            if (
                                !isCurrentPollCycle(
                                    state,
                                    refreshUrl,
                                    url,
                                    isPending,
                                )
                            ) {
                                return state;
                            }

                            return { ...state, fetched: link_preview };
                        });

                        return;
                    }
                }
            } catch {
                if (hasBeenReplaced()) {
                    return;
                }
            }

            if (hasBeenReplaced()) {
                return;
            }

            if (delay !== undefined) {
                timeout = window.setTimeout(
                    () => void poll(attempt + 1),
                    delay,
                );
            } else {
                giveUp();
            }
        };

        timeout = window.setTimeout(() => void poll(0), POLL_DELAYS[0]);

        return () => {
            isCurrent = false;
            window.clearTimeout(timeout);
            controller.abort();
        };
    }, [isPending, refreshUrl, url]);

    const current =
        isPending && currentPollState.fetched?.url === url
            ? currentPollState.fetched
            : preview;

    return {
        preview: current,
        isWaiting: current.is_pending && !currentPollState.hasGivenUp,
    };
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
