import type { Commit, RelatedCommits } from '@/types';

/**
 * Takes the url rather than the commit id so the node test runner, which cannot resolve Wayfinder's imports, can load it.
 */
export function fetchRelatedCommits(
    url: string,
    signal: AbortSignal,
): Promise<Commit[]> {
    return fetch(url, {
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        signal,
    })
        .then((response) =>
            response.ok
                ? response.json().then((data: RelatedCommits) => data.commits)
                : [],
        )
        .catch((error: unknown) => {
            if (signal.aborted) {
                throw error;
            }

            return [];
        });
}

/**
 * The rail keeps the order it was fetched in, so a deleted commit's card is hidden rather than dropped, and a restore
 * mounts it afresh as the feeds do.
 */
export function hiddenAfterFlash(
    hidden: ReadonlySet<string>,
    {
        deleted,
        restored,
    }: { deleted?: { id: string }; restored?: { id: string } },
): ReadonlySet<string> {
    if (deleted) {
        return new Set(hidden).add(deleted.id);
    }

    if (restored && hidden.has(restored.id)) {
        const shown = new Set(hidden);
        shown.delete(restored.id);

        return shown;
    }

    return hidden;
}
