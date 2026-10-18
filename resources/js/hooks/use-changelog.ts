import { useState } from 'react';
import ChangelogController from '@/actions/App/Http/Controllers/ChangelogController';
import type { Changelog } from '@/lib/changelog';

let loaded: Changelog | null = null;
let request: Promise<Changelog> | null = null;

function fetchChangelog(): Promise<Changelog> {
    request ??= fetch(ChangelogController.index().url, {
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    })
        .then(async (response) => {
            if (!response.ok) {
                throw new Error('Unable to load the changelog');
            }

            const changelog = (await response.json()) as Changelog;

            if (changelog.pullRequests === null) {
                throw new Error('GitHub could not be reached');
            }

            loaded = changelog;

            return changelog;
        })
        .catch((error: unknown) => {
            request = null;

            throw error;
        });

    return request;
}

/**
 * Loads the changelog the first time it is asked for, then keeps it for the rest of the visit.
 */
export function useChangelog(): {
    changelog: Changelog | null;
    hasFailed: boolean;
    load: () => void;
} {
    const [changelog, setChangelog] = useState(loaded);
    const [hasFailed, setHasFailed] = useState(false);

    return {
        changelog,
        hasFailed,
        load: () => {
            if (changelog !== null) {
                return;
            }

            setHasFailed(false);
            fetchChangelog().then(setChangelog, () => setHasFailed(true));
        },
    };
}
