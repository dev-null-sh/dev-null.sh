import { useEffect, useState } from 'react';
import RelatedCommitController from '@/actions/App/Http/Controllers/RelatedCommitController';
import { fetchRelatedCommits } from '@/lib/related-commits';
import type { Commit } from '@/types';

export function useRelatedCommits(commitId: string): Commit[] {
    const [loaded, setLoaded] = useState<{ id: string; commits: Commit[] }>({
        id: '',
        commits: [],
    });

    useEffect(() => {
        const controller = new AbortController();

        fetchRelatedCommits(
            RelatedCommitController.index(commitId).url,
            controller.signal,
        )
            .then((commits) => setLoaded({ id: commitId, commits }))
            .catch(() => {});

        return () => controller.abort();
    }, [commitId]);

    return loaded.id === commitId ? loaded.commits : [];
}
