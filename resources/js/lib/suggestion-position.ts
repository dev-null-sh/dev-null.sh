export function suggestionPosition(
    character: { left: number; top: number; bottom: number },
    viewport: { left: number; top: number; width: number; height: number },
    layoutHeight: number,
) {
    const margin = 8;
    const gap = 6;
    const width = Math.min(288, Math.max(0, viewport.width - margin * 2));
    const left = Math.max(
        viewport.left + margin,
        Math.min(
            character.left - 12,
            viewport.left + viewport.width - width - margin,
        ),
    );
    const above = Math.max(0, character.top - viewport.top - margin - gap);
    const below = Math.max(
        0,
        viewport.top + viewport.height - margin - character.bottom - gap,
    );
    const opensAbove = below < 320 && above > below;

    return {
        left,
        width,
        maxHeight: Math.min(320, opensAbove ? above : below),
        ...(opensAbove
            ? { bottom: layoutHeight - character.top + gap }
            : { top: character.bottom + gap }),
    };
}
