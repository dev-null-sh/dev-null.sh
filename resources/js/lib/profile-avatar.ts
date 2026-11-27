export function profileAvatarPosition(
    source: Pick<DOMRect, 'left' | 'top' | 'bottom' | 'width'>,
    target: Pick<DOMRect, 'left' | 'top' | 'width'>,
    headerBottom: number,
    reducedMotion = false,
) {
    // Include the large avatar's 4px offset and 2px ring in the handoff.
    const remaining = source.bottom + 6 - headerBottom;
    const progress = Math.max(
        0,
        Math.min(1, 1 - remaining / (source.width / 2 + 6)),
    );
    const position = reducedMotion ? Number(progress === 1) : progress;

    return {
        progress: position,
        left: source.left + (target.left - source.left) * position,
        top: source.top + (target.top - source.top) * position,
        size: source.width + (target.width - source.width) * position,
    };
}
