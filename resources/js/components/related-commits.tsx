import { CommitCard } from '@/components/commit-card';
import { useRelatedCommits } from '@/hooks/use-related-commits';
import { laneOf } from '@/lib/utils';

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

            {commits.map((commit, index) => (
                <CommitCard
                    key={commit.id}
                    commit={commit}
                    previousLane={
                        index === 0 ? undefined : laneOf(commits[index - 1])
                    }
                />
            ))}
        </section>
    );
}
