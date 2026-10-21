import type { RefObject } from 'react';
import { useEffect, useState } from 'react';
import { useMediaQuery } from '@/hooks/use-media-query';
import {
    canRetryGifLoad,
    failGifLoad,
    GIF_PLAYBACK_MODE,
    hasGifLoadFailed,
    isGifPlaying,
    retryGifLoad,
    syncGifLoadRetryState,
    toggleGifPlayback,
    type GifLoadRetryState,
} from '@/lib/gifs';
import type { Embed } from '@/types';

const MIN_VISIBLE_RATIO = 0.1;

function useGifVisibility(
    container: RefObject<HTMLDivElement | null>,
): boolean {
    const [isVisible, setIsVisible] = useState(false);

    useEffect(() => {
        const element = container.current;

        if (!element) {
            return;
        }

        if (typeof IntersectionObserver === 'undefined') {
            const frame = requestAnimationFrame(() => setIsVisible(true));

            return () => cancelAnimationFrame(frame);
        }

        const observer = new IntersectionObserver(
            ([entry]) =>
                setIsVisible(
                    entry.isIntersecting &&
                        entry.intersectionRatio >= MIN_VISIBLE_RATIO,
                ),
            { threshold: MIN_VISIBLE_RATIO },
        );
        observer.observe(element);

        return () => observer.disconnect();
    }, [container]);

    return isVisible;
}

export function useGifPlayback({
    embed,
    isStatic,
    container,
}: {
    embed: Embed | null;
    isStatic: boolean;
    container: RefObject<HTMLDivElement | null>;
}) {
    const isVisible = useGifVisibility(container);
    const reducedMotion = useMediaQuery('(prefers-reduced-motion: reduce)');
    const source = embed?.src ?? null;
    const [retryState, setRetryState] = useState<GifLoadRetryState>({
        source,
        failedSource: null,
        retryCount: 0,
        playback: GIF_PLAYBACK_MODE.AUTOMATIC,
    });
    const currentRetryState = syncGifLoadRetryState(retryState, source);

    if (currentRetryState !== retryState) {
        setRetryState(currentRetryState);
    }

    const hasFailed = hasGifLoadFailed(currentRetryState, source);
    const canRetry = canRetryGifLoad(currentRetryState, source);
    const isPlaying =
        embed !== null &&
        isGifPlaying({
            source: embed.src,
            playback: currentRetryState.playback,
            isVisible,
            reducedMotion,
            isStatic,
            hasFailed,
        });

    return {
        isPlaying,
        hasFailed,
        canRetry,
        retryCount: currentRetryState.retryCount,
        onError: () => setRetryState((state) => failGifLoad(state, source)),
        onRetry: () => setRetryState((state) => retryGifLoad(state, source)),
        onToggle: () =>
            setRetryState((state) =>
                toggleGifPlayback(state, source, isPlaying),
            ),
    };
}
