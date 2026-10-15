import { InfiniteScroll } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { Fragment, useState } from 'react';
import { CommitCard } from '@/components/commit-card';
import { GraphRail, RailSegment } from '@/components/graph-rail';
import { useNow } from '@/hooks/use-now';
import { formatDayLabel, laneColor, localDayKey } from '@/lib/utils';
import type { Commit, CursorPaginated } from '@/types';

export function CommitFeed({
    commits,
    empty,
    end,
    firstLane,
    highlightedPeriod = null,
    firstSeenId = null,
    actions,
}: {
    commits: CursorPaginated<Commit>;
    empty: ReactNode;
    end: string;
    firstLane?: string;
    highlightedPeriod?: string | null;
    firstSeenId?: string | null;
    actions?: (commit: Commit) => ReactNode;
}) {
    const now = useNow();
    const [highlightedAuthor, setHighlightedAuthor] = useState<string | null>(
        null,
    );

    if (commits.data.length === 0) {
        return <div className="px-6 py-16 text-center sm:px-12">{empty}</div>;
    }

    const isDimmed = (userId: string | undefined) =>
        highlightedAuthor !== null && userId !== highlightedAuthor;

    const lastCommit = commits.data.at(-1);

    return (
        <>
            <InfiniteScroll
                data="commits"
                onlyNext
                preserveUrl
                loading={
                    <div role="status" aria-label="Loading more commits">
                        <CommitSkeleton />
                        <CommitSkeleton />
                    </div>
                }
            >
                {commits.data.map((commit, index) => {
                    const previous = commits.data[index - 1];
                    const previousLane = previous
                        ? laneColor(previous.user.lane)
                        : firstLane;
                    const startsDay =
                        !previous ||
                        localDayKey(previous.created_at) !==
                            localDayKey(commit.created_at);

                    return (
                        <Fragment key={commit.id}>
                            {commit.id === firstSeenId && (
                                <LastVisitMarker
                                    lane={previousLane}
                                    isDimmed={isDimmed(previous?.user_id)}
                                />
                            )}
                            {startsDay && (
                                <DayMarker
                                    label={formatDayLabel(
                                        commit.created_at,
                                        now,
                                    )}
                                    lane={previousLane}
                                    isDimmed={isDimmed(previous?.user_id)}
                                />
                            )}
                            <CommitCard
                                commit={commit}
                                previousLane={previousLane}
                                isDimmed={isDimmed(commit.user_id)}
                                isPreviousDimmed={
                                    previous
                                        ? isDimmed(previous.user_id)
                                        : highlightedAuthor !== null
                                }
                                isHighlighted={
                                    highlightedPeriod !== null &&
                                    commit.created_at.startsWith(
                                        highlightedPeriod,
                                    )
                                }
                                actions={actions?.(commit)}
                                onAuthorHover={(isHovering) =>
                                    setHighlightedAuthor(
                                        isHovering ? commit.user_id : null,
                                    )
                                }
                            />
                        </Fragment>
                    );
                })}
            </InfiniteScroll>

            {commits.next_cursor === null && lastCommit && (
                <div className="flex">
                    <GraphRail
                        lane={laneColor(lastCommit.user.lane)}
                        previousLane={laneColor(lastCommit.user.lane)}
                        variant="root"
                        hasTail={false}
                    />
                    <p className="pt-[1.3rem] pr-4 pb-10 text-sm text-muted-foreground">
                        {end}
                    </p>
                </div>
            )}
        </>
    );
}

function DayMarker({
    label,
    lane,
    isDimmed,
}: {
    label: string;
    lane?: string;
    isDimmed: boolean;
}) {
    return (
        <div className="flex">
            <RailSegment lane={lane} isDimmed={isDimmed} />
            <div className="flex flex-1 items-center gap-3 border-b py-2 pr-4">
                <h3 className="font-mono text-xs text-muted-foreground">
                    {label}
                </h3>
            </div>
        </div>
    );
}

function LastVisitMarker({
    lane,
    isDimmed,
}: {
    lane?: string;
    isDimmed: boolean;
}) {
    return (
        <div
            role="separator"
            aria-label="Commits above are new since your last visit"
            className="flex"
        >
            <RailSegment lane={lane} isDimmed={isDimmed} />
            <div className="flex flex-1 items-center gap-3 border-b py-2 pr-4">
                <span className="font-mono text-xs text-muted-foreground">
                    HEAD@{'{last visit}'}
                </span>
                <span className="flex-1 border-t border-dashed" />
            </div>
        </div>
    );
}

export function CommitSkeleton() {
    return (
        <div aria-hidden className="flex">
            <div className="relative w-11 shrink-0 sm:w-14">
                <span className="absolute inset-y-0 left-1/2 w-0.5 -translate-x-1/2 bg-rail" />
                <span className="absolute top-8 left-1/2 size-3.5 -translate-1/2 rounded-full border-[3px] border-card bg-rail shadow-[0_0_0_2px_var(--rail)]" />
            </div>
            <div className="flex min-w-0 flex-1 gap-3 border-b py-3 pr-4 motion-safe:animate-pulse">
                <span className="size-10 shrink-0 rounded-full bg-muted" />
                <div className="flex-1 space-y-2.5 pt-1">
                    <span className="block h-3 w-2/5 rounded bg-muted" />
                    <span className="block h-3 w-4/5 rounded bg-muted" />
                    <span className="block h-3 w-3/5 rounded bg-muted" />
                </div>
            </div>
        </div>
    );
}

export function EmptyFeed({
    command,
    children,
}: {
    command: string;
    children: ReactNode;
}) {
    return (
        <>
            <p className="font-mono text-[13px] text-muted-foreground">
                {command}
            </p>
            <div className="mx-auto mt-3 max-w-sm text-[15px] text-pretty">
                {children}
            </div>
        </>
    );
}
