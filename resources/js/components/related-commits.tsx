import { CommitCard } from '@/components/commit-card';
import { GraphCanvas } from '@/components/graph-canvas';
import { useRelatedCommits } from '@/hooks/use-related-commits';
import type { Commit } from '@/types';

/**
 * Leaves out the commits the page already shows above, like those about the commit's link, and waits while they are
 * null, still loading, so that they never push the related commits down.
 */
export function RelatedCommits({
    commitId,
    exceptIds = [],
}: {
    commitId: string;
    exceptIds?: string[] | null;
}) {
    const commits = useRelatedCommits(commitId).filter(
        ({ id }) => exceptIds !== null && !exceptIds.includes(id),
    );

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
