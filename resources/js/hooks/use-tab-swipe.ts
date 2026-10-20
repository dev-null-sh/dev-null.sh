import type { RefObject } from 'react';
import { useEffect, useEffectEvent } from 'react';
import { swipeDirection } from '@/lib/tab-swipe';

/**
 * Mobile browsers use swipes that start at the edge of the screen for going back and forward.
 */
const screenEdge = 24;

/** Touch navigation shared by log and settings tabs, excluding fields and horizontal scrollers. */
export function useTabSwipe(
    feedRef: RefObject<HTMLElement | null>,
    onSwipe: (direction: -1 | 1) => void,
): void {
    const navigate = useEffectEvent(onSwipe);
    useEffect(() => {
        const feed = feedRef.current;

        if (!feed) {
            return;
        }

        let start: { x: number; y: number } | null = null;
        document.documentElement.dataset.laneSwipe = '';

        const onTouchStart = (event: TouchEvent) => {
            const touch = event.touches[0];

            start =
                event.touches.length === 1 &&
                startsSwipe(touch, event.target, feed)
                    ? { x: touch.clientX, y: touch.clientY }
                    : null;
        };

        const onTouchEnd = (event: TouchEvent) => {
            const touch = event.changedTouches[0];
            const from = start;
            start = null;

            if (from === null || touch === undefined || hasSelectedText()) {
                return;
            }

            const direction = swipeDirection(
                touch.clientX - from.x,
                touch.clientY - from.y,
            );
            if (direction !== null) navigate(direction);
        };

        const onTouchCancel = () => {
            start = null;
        };

        feed.addEventListener('touchstart', onTouchStart, { passive: true });
        feed.addEventListener('touchend', onTouchEnd);
        feed.addEventListener('touchcancel', onTouchCancel);

        return () => {
            delete document.documentElement.dataset.laneSwipe;
            feed.removeEventListener('touchstart', onTouchStart);
            feed.removeEventListener('touchend', onTouchEnd);
            feed.removeEventListener('touchcancel', onTouchCancel);
        };
    }, [feedRef]);
}

/**
 * Sideways swipes already mean something when zoomed in, inside a field, or over content that scrolls sideways.
 */
function startsSwipe(
    touch: Touch,
    target: EventTarget | null,
    feed: HTMLElement,
): boolean {
    if (
        touch.clientX < screenEdge ||
        touch.clientX > window.innerWidth - screenEdge ||
        (window.visualViewport?.scale ?? 1) > 1
    ) {
        return false;
    }

    for (
        let element = target instanceof Element ? target : null;
        element !== null && element !== feed;
        element = element.parentElement
    ) {
        if (
            element.matches('input, textarea, select, [contenteditable]') ||
            scrollsSideways(element)
        ) {
            return false;
        }
    }

    return true;
}

function scrollsSideways(element: Element): boolean {
    return (
        element.scrollWidth > element.clientWidth &&
        ['auto', 'scroll'].includes(getComputedStyle(element).overflowX)
    );
}

function hasSelectedText(): boolean {
    return (window.getSelection()?.toString() ?? '') !== '';
}
