import type { ReactNode } from 'react';
import { CommitCard } from '@/components/commit-card';
import { CommitSkeleton, EmptyFeed } from '@/components/commit-feed';
import { GraphCanvas } from '@/components/graph-canvas';
import { authorLaneColor } from '@/lib/utils';
import type { Commit } from '@/types';

export type SearchResultSet = { term: string; commits: Commit[] };

export function SearchResults({
    results,
    isWaiting = false,
    heading = 'Most relevant commits',
    empty = 'Try describing the idea in other words, or pick one of the searches below.',
}: {
    results: SearchResultSet | null;
    isWaiting?: boolean;
    heading?: string;
    empty?: ReactNode;
}) {
    if (results === null) {
        return (
            <div
                role="status"
                aria-label="Searching commits"
                className={isWaiting ? 'opacity-100' : 'opacity-0'}
            >
                <div className="flex h-11 items-center border-b px-4 sm:px-6">
                    <span className="h-3 w-20 rounded bg-muted motion-safe:animate-pulse" />
                </div>
                <CommitSkeleton />
                <CommitSkeleton />
                <CommitSkeleton />
            </div>
        );
    }

    return (
        <section aria-labelledby="search-results" aria-busy={isWaiting}>
            <div className="flex h-11 items-center justify-between border-b px-4 sm:px-6">
                <h2 id="search-results" className="font-semibold">
                    {heading}
                </h2>
                <span className="font-mono text-xs text-muted-foreground tabular-nums">
                    {results.commits.length}{' '}
                    {results.commits.length === 1 ? 'match' : 'matches'}
                </span>
            </div>
            {results.commits.length === 0 ? (
                <div className="px-6 py-16 text-center sm:px-12">
                    <EmptyFeed
                        command={`fatal: nothing close to '${results.term}'`}
                    >
                        {empty}
                    </EmptyFeed>
                </div>
            ) : (
                <GraphCanvas>
                    {results.commits.map((commit, index) => {
                        const previous = results.commits[index - 1];

                        return (
                            <CommitCard
                                key={commit.id}
                                commit={commit}
                                previousLane={
                                    previous && authorLaneColor(previous.author)
                                }
                                graphAfter={previous?.id}
                                endsWithDot={
                                    index === results.commits.length - 1
                                }
                            />
                        );
                    })}
                </GraphCanvas>
            )}
        </section>
    );
}
