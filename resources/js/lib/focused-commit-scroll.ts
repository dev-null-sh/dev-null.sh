type Navigation = {
    type: string;
    page: { component: string; props: Record<string, unknown> };
};

/**
 * A commit's page lists the thread above the commit, so a deep reply would open below the fold. Opening the page
 * brings the commit under the header, with the end of its parent still showing above it. Back, Forward, a reload or an
 * anchor in the URL keep the position Inertia or the browser gives them, and opening a photo leaves the page behind it
 * where it was.
 */
export function scrollToFocusedCommit({ type, page }: Navigation): void {
    const { commit, ancestors, photo } = page.props as {
        commit: { id: string };
        ancestors?: unknown[];
        photo?: unknown;
    };

    if (
        page.component !== 'commit/show' ||
        !ancestors?.length ||
        photo ||
        window.location.hash ||
        !(type === 'visit' || (type === 'initial' && isFirstLoad()))
    ) {
        return;
    }

    document
        .querySelector(`[data-commit="${CSS.escape(commit.id)}"]`)
        ?.scrollIntoView({ block: 'start' });
}

function isFirstLoad(): boolean {
    const [entry] = performance.getEntriesByType(
        'navigation',
    ) as PerformanceNavigationTiming[];

    return entry?.type !== 'reload' && entry?.type !== 'back_forward';
}
