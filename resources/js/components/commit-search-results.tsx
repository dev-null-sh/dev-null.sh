import { usePage } from '@inertiajs/react';
import { Fragment, type ReactNode } from 'react';
import { CommitCard } from '@/components/commit-card';
import { CommitSkeleton, EmptyFeed } from '@/components/commit-feed';
import { GraphCanvas } from '@/components/graph-canvas';
import { GuildIcon } from '@/components/guild-icon';
import { groupSearchCommits } from '@/lib/commit-search-results';
import type { Commit } from '@/types';
import type { GuildBadge } from '@/types/guild';

export type SearchResultSet = { term: string; commits: Commit[] };

export function GuildSearchHeading({
    handle,
    guild,
}: {
    handle: string;
    guild?: GuildBadge | null;
}) {
    const visibleGuild = usePage().props.guildsEnabled ? guild : null;

    return (
        <span className="flex min-w-0 items-center gap-2 font-sans text-sm font-semibold">
            <GuildIcon guild={visibleGuild ?? { emoji: '#', icon_url: null }} />
            <span
                className="truncate"
                title={visibleGuild?.name ?? `#${handle}`}
            >
                {visibleGuild?.name ?? `#${handle}`}
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
                groupSearchCommits(results.commits).map(([handle, commits]) => (
                    <Fragment key={handle ? `guild:${handle}` : 'commits'}>
                        {handle && (
                            <h3 className="flex h-11 items-center border-y bg-muted/40 px-4 sm:px-6">
                                <GuildSearchHeading
                                    handle={handle}
                                    guild={commits[0].guild}
                                />
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
