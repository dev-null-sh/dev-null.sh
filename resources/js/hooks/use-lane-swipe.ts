import { router } from '@inertiajs/react';
import type { RefObject } from 'react';
import { useEffect } from 'react';
import LogController from '@/actions/App/Http/Controllers/LogController';
import { swipedLane } from '@/lib/lane-swipe';
import type { Log } from '@/types';

/**
 * Mobile browsers use swipes that start at the edge of the screen for going back and forward.
 */
const screenEdge = 24;

/**
 * Lets touch users swipe sideways across the feed to switch lanes. It listens on the DOM rather than through React, so
 * touches inside dialogs portalled out of the feed never reach it.
 */
export function useLaneSwipe(
    feedRef: RefObject<HTMLElement | null>,
    log: Log,
): void {
    useEffect(() => {
        const feed = feedRef.current;

        if (!feed) {
            return;
        }

        let start: { x: number; y: number } | null = null;

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

            const lane = swipedLane(
                log,
                touch.clientX - from.x,
                touch.clientY - from.y,
            );

            if (lane !== null) {
                router.visit(LogController.show(lane), {
                    viewTransition: true,
                });
            }
        };

        const onTouchCancel = () => {
            start = null;
        };

        feed.addEventListener('touchstart', onTouchStart, { passive: true });
        feed.addEventListener('touchend', onTouchEnd);
        feed.addEventListener('touchcancel', onTouchCancel);

        return () => {
            feed.removeEventListener('touchstart', onTouchStart);
            feed.removeEventListener('touchend', onTouchEnd);
            feed.removeEventListener('touchcancel', onTouchCancel);
        };
    }, [feedRef, log]);
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
