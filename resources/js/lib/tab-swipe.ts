/** A deliberate horizontal swipe moves to the next (left) or previous (right) tab. */
export function swipeDirection(deltaX: number, deltaY: number): -1 | 1 | null {
    if (Math.abs(deltaX) < 60 || Math.abs(deltaX) < Math.abs(deltaY) * 2)
        return null;
    return deltaX < 0 ? 1 : -1;
}
