/**
 * The first row still showing at the top of a scroller, and how far below the scroller's top edge it starts.
 */
export type ScrollAnchor = {
    id: string;
    offset: number;
};

export function scrollAnchorOf(scroller: HTMLElement): ScrollAnchor | null {
    const top = scroller.getBoundingClientRect().top;

    for (const row of scroller.querySelectorAll<HTMLElement>(
        '[data-scroll-anchor]',
    )) {
        const { top: rowTop, bottom } = row.getBoundingClientRect();

        if (bottom > top) {
            return { id: row.dataset.scrollAnchor ?? '', offset: rowTop - top };
        }
    }

    return null;
}

/**
 * Scrolls so the anchor's row is back where it was, after content above it grew or shrank.
 */
export function restoreScrollAnchor(
    scroller: HTMLElement,
    anchor: ScrollAnchor,
): void {
    const row = scroller.querySelector<HTMLElement>(
        `[data-scroll-anchor="${CSS.escape(anchor.id)}"]`,
    );

    if (row === null) {
        return;
    }

    const shift =
        row.getBoundingClientRect().top -
        scroller.getBoundingClientRect().top -
        anchor.offset;

    // Only a real shift is corrected, so content loading elsewhere never interrupts a scroll in motion.
    if (Math.abs(shift) >= 1) {
        scroller.scrollTop += shift;
    }
}
