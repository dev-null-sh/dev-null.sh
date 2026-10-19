/**
 * Scrolling slides commits under a still pointer, which browsers report as hovering each one in turn. Only a pointer
 * that actually moved, while the feed is not scrolling, means someone is pointing at a commit.
 */
export function isDeliberateHover(
    movement: { movementX: number; movementY: number },
    isScrolling: boolean,
): boolean {
    return (
        !isScrolling && (movement.movementX !== 0 || movement.movementY !== 0)
    );
}
