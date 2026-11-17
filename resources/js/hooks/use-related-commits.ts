import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import RelatedCommitController from '@/actions/App/Http/Controllers/RelatedCommitController';
import { fetchRelatedCommits, hiddenAfterFlash } from '@/lib/related-commits';
import type { Commit } from '@/types';

export function useRelatedCommits(commitId: string): Commit[] {
    const [loaded, setLoaded] = useState<{
        id: string;
        commits: Commit[];
        hidden: ReadonlySet<string>;
    }>({ id: '', commits: [], hidden: new Set() });

    useEffect(() => {
        const controller = new AbortController();

        fetchRelatedCommits(
            RelatedCommitController.index(commitId).url,
            controller.signal,
        )
            .then((commits) =>
                setLoaded({ id: commitId, commits, hidden: new Set() }),
            )
            .catch(() => {});

        return () => controller.abort();
    }, [commitId]);

    useEffect(
        () =>
            router.on('flash', (event) =>
                setLoaded((current) => ({
                    ...current,
                    hidden: hiddenAfterFlash(
                        current.hidden,
                        event.detail.flash,
                    ),
                })),
            ),
        [],
    );

    return loaded.id === commitId
        ? loaded.commits.filter((commit) => !loaded.hidden.has(commit.id))
        : [];
}
