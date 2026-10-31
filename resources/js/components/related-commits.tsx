import { CommitCard } from '@/components/commit-card';
import { GraphCanvas } from '@/components/graph-canvas';
import { useRelatedCommits } from '@/hooks/use-related-commits';
import { laneOf } from '@/lib/utils';
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
 * The related commits chain one after the other in a canvas of their own, below the thread's, and end on a dot below
 * the last one, as a feed does.
 */
export function RelatedCommitList({ commits }: { commits: Commit[] }) {
    return (
        <GraphCanvas>
            {commits.map((commit, index) => {
                const previous = commits[index - 1];

                return (
                    <CommitCard
                        key={commit.id}
                        commit={commit}
                        previousLane={previous && laneOf(previous)}
                        graphAfter={previous?.id}
                        endsWithDot={index === commits.length - 1}
                    />
                );
            })}
        </GraphCanvas>
    );
}
