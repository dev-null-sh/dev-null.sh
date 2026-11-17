import { CommitCard } from '@/components/commit-card';
import { GraphCanvas } from '@/components/graph-canvas';
import { useRelatedCommits } from '@/hooks/use-related-commits';
import type { Commit } from '@/types';

export function RelatedCommits({ commitId }: { commitId: string }) {
    const commits = useRelatedCommits(commitId);

    if (commits.length === 0) {
        return null;
    }

    return (
        <section aria-labelledby="related-commits" className="border-t">
            <div className="flex h-11 items-center border-b px-4 sm:px-6">
                <h2 id="related-commits" className="font-semibold">
                    Related commits
                </h2>
            </div>

            <RelatedCommitList commits={commits} />
        </section>
    );
}

/**
 * The related commits relate to the commit shown, not to each other, so each one ends on a dot of its own in a canvas
 * below the thread's, as a feed does.
 */
export function RelatedCommitList({ commits }: { commits: Commit[] }) {
    return (
        <GraphCanvas>
            {commits.map((commit) => (
                <CommitCard key={commit.id} commit={commit} endsWithDot />
            ))}
        </GraphCanvas>
    );
}
