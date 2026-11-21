import { usePage } from '@inertiajs/react';
import { Fragment, type ReactNode } from 'react';
import { CommitCard } from '@/components/commit-card';
import { CommitSkeleton, EmptyFeed } from '@/components/commit-feed';
import { GraphCanvas } from '@/components/graph-canvas';
import { GuildIcon } from '@/components/guild-icon';
import { groupSearchCommits } from '@/lib/commit-search-results';
import type { Commit } from '@/types';

export type SearchResultSet = {
    term: string;
    commits: Commit[];
    isUnavailable?: boolean;
};

export function GuildSearchHeading({ handle }: { handle: string }) {
    const { guilds } = usePage().props;
    const guild = guilds?.find((guild) => guild.handle === handle);

    return (
        <span className="flex min-w-0 items-center gap-2 font-sans text-sm font-semibold">
            <GuildIcon guild={guild ?? { emoji: '#', icon_url: null }} />
            <span className="truncate" title={guild?.name ?? `#${handle}`}>
                {guild?.name ?? `#${handle}`}
            </span>
        </span>
    );
}

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
                {!results.isUnavailable && (
                    <span className="font-mono text-xs text-muted-foreground tabular-nums">
                        {results.commits.length}{' '}
                        {results.commits.length === 1 ? 'match' : 'matches'}
                    </span>
                )}
            </div>
            {results.isUnavailable ? (
                <div className="px-6 py-16 text-center sm:px-12">
                    <EmptyFeed command="error: commit search is down">
                        It can't read your search right now. Try again in a
                        moment.
                    </EmptyFeed>
                </div>
            ) : results.commits.length === 0 ? (
                <div className="px-6 py-16 text-center sm:px-12">
                    <EmptyFeed
                        command={`fatal: nothing close to '${results.term}'`}
                    >
                        {empty}
                    </EmptyFeed>
                </div>
            ) : (
                groupSearchCommits(results.commits).map(([handle, commits]) => (
                    <Fragment key={handle ? `guild:${handle}` : 'commits'}>
                        {handle && (
                            <h3 className="flex h-11 items-center border-y bg-muted/40 px-4 sm:px-6">
                                <GuildSearchHeading handle={handle} />
                            </h3>
                        )}
                        <GraphCanvas>
                            {commits.map((commit) => (
                                <CommitCard
                                    key={commit.id}
                                    commit={commit}
                                    endsWithDot
                                />
                            ))}
                        </GraphCanvas>
                    </Fragment>
                ))
            )}
        </section>
    );
}
