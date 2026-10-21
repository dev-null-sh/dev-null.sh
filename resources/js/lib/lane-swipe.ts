import type { Log } from '@/types';

export const logLanes: readonly Log[] = ['following', 'latest', 'trending'];

const shortestSwipe = 60;

/**
 * Swiping left moves to the lane on the right, like dragging the next tab into view. A swipe has to be mostly sideways,
 * so scrolling the feed at an angle never changes lanes.
 */
export function swipedLane<Lane extends string>(
    from: Lane,
    lanes: readonly Lane[],
    deltaX: number,
    deltaY: number,
): Lane | null {
    if (
        Math.abs(deltaX) < shortestSwipe ||
        Math.abs(deltaX) < Math.abs(deltaY) * 2
    ) {
        return null;
    }

    const currentIndex = lanes.indexOf(from);

    if (currentIndex === -1) {
        return null;
    }

    return lanes[currentIndex + (deltaX < 0 ? 1 : -1)] ?? null;
}
