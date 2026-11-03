import type { HttpExceptionResponse } from '@inertiajs/core';

/**
 * What was deleted, or hidden from the viewer, while they were still looking at it: those commits and pages stay on
 * screen as they were last seen, with every action off, instead of turning into a 404 before they reload. A commit
 * heard to be back, as when its deletion is undone elsewhere, gets its actions back.
 */
export function createDeletedResources() {
    const commits = new Set<string>();
    const pages = new Set<string>();
    const listeners = new Set<() => void>();

    const mark = (deleted: Set<string>, value: string) => {
        if (deleted.has(value)) {
            return;
        }

        deleted.add(value);
        listeners.forEach((notify) => notify());
    };

    return {
        markCommit: (id: string) => mark(commits, id),
        restoreCommit: (id: string) => {
            if (commits.delete(id)) {
                listeners.forEach((notify) => notify());
            }
        },
        markPage: (url: string) => mark(pages, url),
        hasCommit: (id: string) => commits.has(id),
        hasPage: (url: string) => pages.has(url),
        subscribe: (listener: () => void): (() => void) => {
            listeners.add(listener);

            return () => {
                listeners.delete(listener);
            };
        },
    };
}

export const deletedResources = createDeletedResources();

/**
 * Whether a 404 says the open page is gone. Most pages answer the error page then, but some are 404s from the start, like
 * the sign-in prompt guests get for a handle nobody looked up: reloading one answers the same page, which is still there.
 */
export function isPageGone(
    response: HttpExceptionResponse,
    component: string,
): boolean {
    return (
        response.status === 404 &&
        (typeof response.data !== 'object' ||
            response.data.component !== component)
    );
}
