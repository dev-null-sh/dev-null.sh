import type { Log } from '@/types';

const lanes: Log[] = ['following', 'latest', 'trending'];

const shortestSwipe = 60;

/**
 * Swiping left moves to the lane on the right, like dragging the next tab into view. A swipe has to be mostly sideways,
 * so scrolling the feed at an angle never changes lanes.
 */
export function swipedLane(
    from: Log,
    deltaX: number,
    deltaY: number,
): Log | null {
    if (
        Math.abs(deltaX) < shortestSwipe ||
        Math.abs(deltaX) < Math.abs(deltaY) * 2
    ) {
        return null;
    }

    return lanes[lanes.indexOf(from) + (deltaX < 0 ? 1 : -1)] ?? null;
}
