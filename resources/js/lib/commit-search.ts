import CommitSearchController from '@/actions/App/Http/Controllers/CommitSearchController';
import type { Commit } from '@/types';

export const suggestedQueries = [
    'how’s AI coding going?',
    'what happened on the livestream?',
    'cool projects on GitHub',
    'vibe coding, but with a plan',
    'building dev/null in two days',
    'PHP is still pretty good, huh?',
];

/**
 * Thrown when the server asks the client to slow down, so it can say so instead of claiming nothing matched.
 */
export class TooManySearches extends Error {}

export function searchCommits(
    term: string,
    limit?: number,
    signal?: AbortSignal,
): Promise<Commit[]> {
    return fetch(
        CommitSearchController.index({
            query: limit === undefined ? { q: term } : { q: term, limit },
        }).url,
        {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            signal,
        },
    )
        .then((response) => {
            if (response.status === 429) {
                throw new TooManySearches();
            }

            if (!response.ok) {
                throw new Error(`Commit search failed: ${response.status}`);
            }

            return response.json();
        })
        .then((data: { commits: Commit[] }) => data.commits);
}
