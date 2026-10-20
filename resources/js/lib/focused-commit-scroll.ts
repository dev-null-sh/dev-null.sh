import { router } from '@inertiajs/react';
import {
    arrivalOf,
    shouldScrollToFocusedCommit,
} from '@/lib/focused-commit-arrival';

// A first load renders after Inertia's navigate event, so the commit is looked for over a few frames.
const maxFramesToWait = 30;

/**
 * Brings the focused commit to the top of the screen when its page is opened, under the thread it replies to.
 * Inertia scrolls a visited page to the top right before its navigate event, so this runs after that.
 */
export function trackFocusedCommitScroll(): VoidFunction {
    const placeKeepingVisits = new Set<string>();

    const stopStart = router.on('start', ({ detail: { visit } }) => {
        if (visit.preserveState || visit.preserveScroll) {
            placeKeepingVisits.add(visit.id);
        }
    });

    const stopNavigate = router.on(
        'navigate',
        ({ detail: { page, type, visitId } }) => {
            const keepsPlace =
                visitId !== undefined && placeKeepingVisits.delete(visitId);
            const loadType = performance.getEntriesByType('navigation')[0] as
                | PerformanceNavigationTiming
                | undefined;
            const ancestors = page.props.ancestors as unknown[] | undefined;
            const commit = page.props.commit as { id: string } | undefined;

            if (
                commit === undefined ||
                !shouldScrollToFocusedCommit({
                    component: page.component,
                    arrival: arrivalOf(type, loadType?.type),
                    keepsPlace,
                    ancestorCount: ancestors?.length ?? 0,
                })
            ) {
                return;
            }

            scrollToCommit(commit.id, maxFramesToWait);
        },
    );

    return () => {
        stopStart();
        stopNavigate();
    };
}

function scrollToCommit(id: string, framesLeft: number): void {
    window.requestAnimationFrame(() => {
        const commit = document.querySelector<HTMLElement>(
            `[data-commit="${CSS.escape(id)}"]`,
        );

        if (commit !== null) {
            // The card's scroll margin keeps it clear of the sticky header.
            commit.scrollIntoView({ block: 'start' });
        } else if (framesLeft > 0) {
            scrollToCommit(id, framesLeft - 1);
        }
    });
}
