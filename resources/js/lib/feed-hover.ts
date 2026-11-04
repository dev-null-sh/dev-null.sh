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

export type FeedHighlight = {
    commitId: string | null;
    authorId: string | null;
};

export function isDimmedBy(
    highlight: FeedHighlight,
    commit: { id: string; author: { id: string } },
): boolean {
    return highlight.authorId !== null
        ? commit.author.id !== highlight.authorId
        : highlight.commitId !== null && commit.id !== highlight.commitId;
}

export type FeedHighlightStore = ReturnType<typeof createFeedHighlight>;

/**
 * Holds what the pointer highlights outside React state, so each commit re-renders only when its own look changes
 * instead of the whole feed re-rendering on every move between commits.
 */
export function createFeedHighlight() {
    let highlight: FeedHighlight = { commitId: null, authorId: null };
    const listeners = new Set<() => void>();

    return {
        get: (): FeedHighlight => highlight,
        set: (next: Partial<FeedHighlight>) => {
            const updated = { ...highlight, ...next };

            if (
                updated.commitId === highlight.commitId &&
                updated.authorId === highlight.authorId
            ) {
                return;
            }

            highlight = updated;
            listeners.forEach((listener) => listener());
        },
        subscribe: (listener: () => void) => {
            listeners.add(listener);

            return () => {
                listeners.delete(listener);
            };
        },
    };
}
