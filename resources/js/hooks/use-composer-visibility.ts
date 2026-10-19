import { useLayoutEffect } from 'react';
import { refreshSharedState, useSharedState } from '@/hooks/use-shared-state';

const key = 'is-composer-in-view';

const minVisibleRatio = 0.5;

const setIsInView = (isInView: boolean): void =>
    refreshSharedState(new Map([[key, isInView]]));

export function useIsComposerInView(): boolean {
    const [isInView] = useSharedState<boolean>(key, true);

    return isInView;
}

/**
 * Measures before paint, so the mobile commit button never flashes in over a composer that is already on screen.
 * Content under the sticky tabs, `topOffset` tall, does not count as visible.
 */
export function useComposerVisibility(
    composer: HTMLElement | null,
    topOffset: number,
): void {
    useLayoutEffect(() => {
        if (composer === null) {
            return;
        }

        const { top, bottom, height } = composer.getBoundingClientRect();
        const visibleHeight =
            Math.min(bottom, window.innerHeight) - Math.max(top, topOffset);

        setIsInView(height > 0 && visibleHeight / height >= minVisibleRatio);

        const observer = new IntersectionObserver(
            ([entry]) =>
                setIsInView(entry.intersectionRatio >= minVisibleRatio),
            {
                rootMargin: `-${topOffset}px 0px 0px 0px`,
                threshold: minVisibleRatio,
            },
        );

        observer.observe(composer);

        return () => {
            observer.disconnect();
            setIsInView(true);
        };
    }, [composer, topOffset]);
}
