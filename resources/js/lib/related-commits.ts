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
