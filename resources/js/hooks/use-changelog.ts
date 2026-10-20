import { useCallback, useState } from 'react';
import { flushSync } from 'react-dom';
import ChangelogController from '@/actions/App/Http/Controllers/ChangelogController';
import type { Changelog, ChangelogPullRequest } from '@/lib/changelog';

let loaded: Changelog | null = null;
let request: Promise<Changelog> | null = null;

async function fetchChangelog(
    timeZone: string,
    before: string | null,
): Promise<Changelog> {
    const response = await fetch(
        ChangelogController.index({
            query: {
                timezone: timeZone,
                ...(before === null ? {} : { before }),
            },
        }).url,
        {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        },
    );

    if (!response.ok) {
        throw new Error('Unable to load the changelog');
    }

    const changelog = (await response.json()) as Changelog;

    if (changelog.pullRequests === null) {
        throw new Error('GitHub could not be reached');
    }

    return changelog;
}

/**
 * Loads the latest days of the changelog the first time it is asked for, and older days on request, then keeps what
 * it loaded for the rest of the visit.
 */
export function useChangelog(timeZone: string): {
    changelog: Changelog | null;
    hasFailed: boolean;
    isLoadingOlder: boolean;
    hasOlderFailed: boolean;
    load: () => void;
    loadOlder: (onLoaded: (added: ChangelogPullRequest[]) => void) => void;
} {
    const [changelog, setChangelog] = useState(loaded);
    const [hasFailed, setHasFailed] = useState(false);
    const [isLoadingOlder, setIsLoadingOlder] = useState(false);
    const [hasOlderFailed, setHasOlderFailed] = useState(false);

    const keep = useCallback((next: Changelog) => {
        loaded = next;
        setChangelog(next);
    }, []);

    const load = useCallback(() => {
        if (changelog !== null) {
            return;
        }

        setHasFailed(false);
        request ??= fetchChangelog(timeZone, null);
        request.then(keep, () => {
            request = null;
            setHasFailed(true);
        });
    }, [changelog, keep, timeZone]);

    return {
        changelog,
        hasFailed,
        isLoadingOlder,
        hasOlderFailed,
        load,
        loadOlder: (onLoaded) => {
            if (changelog?.olderBefore == null || isLoadingOlder) {
                return;
            }

            const current = changelog;
            setIsLoadingOlder(true);
            setHasOlderFailed(false);

            fetchChangelog(timeZone, current.olderBefore).then(
                (older) => {
                    const known = new Set(
                        (current.pullRequests ?? []).map(
                            (pullRequest) => pullRequest.number,
                        ),
                    );
                    const added = (older.pullRequests ?? []).filter(
                        (pullRequest) => !known.has(pullRequest.number),
                    );

                    flushSync(() => {
                        keep({
                            ...current,
                            pullRequests: [
                                ...(current.pullRequests ?? []),
                                ...added,
                            ],
                            olderBefore: older.olderBefore,
                        });
                        setIsLoadingOlder(false);
                    });
                    onLoaded(added);
                },
                () => {
                    setIsLoadingOlder(false);
                    setHasOlderFailed(true);
                },
            );
        },
    };
}
