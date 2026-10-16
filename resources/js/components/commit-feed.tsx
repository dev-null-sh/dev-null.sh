import { InfiniteScroll } from '@inertiajs/react';
import { Pin } from 'lucide-react';
import type { ReactNode } from 'react';
import { Fragment, useState } from 'react';
import { CommitCard } from '@/components/commit-card';
import { GraphRail, RailSegment } from '@/components/graph-rail';
import { useNow } from '@/hooks/use-now';
import { formatDayLabel, laneColor, localDayKey } from '@/lib/utils';
import type { Commit, CursorPaginated } from '@/types';

const interludeAfter = 5;

export function CommitFeed({
    commits,
    empty,
    end,
    firstLane,
    highlightedPeriod = null,
    firstSeenId = null,
    actions,
    threadActions,
    pinnedCommit = null,
    interlude,
}: {
    commits: CursorPaginated<Commit>;
    empty: ReactNode;
    end: string;
    firstLane?: string;
    highlightedPeriod?: string | null;
    firstSeenId?: string | null;
    actions?: (commit: Commit) => ReactNode;
    threadActions?: (commit: Commit) => ReactNode;
    pinnedCommit?: Commit | null;
    interlude?: (lane: string, isDimmed: boolean) => ReactNode;
}) {
    const now = useNow();
    const [highlightedAuthor, setHighlightedAuthor] = useState<string | null>(
        null,
    );

    if (commits.data.length === 0 && pinnedCommit === null) {
        return <div className="px-6 py-16 text-center sm:px-12">{empty}</div>;
    }

    const isDimmed = (userId: string | undefined) =>
        highlightedAuthor !== null && userId !== highlightedAuthor;

    const lastCommit = commits.data.at(-1) ?? pinnedCommit;
    const lastLane = lastCommit ? laneColor(lastCommit.user.lane) : undefined;
    const pinnedLane = pinnedCommit
        ? laneColor(pinnedCommit.user.lane)
        : firstLane;
    const interludeIndex = Math.min(interludeAfter, commits.data.length) - 1;

    return (
        <>
            {pinnedCommit && (
                <section aria-label="Pinned commit">
                    <div className="flex">
                        <RailSegment />
                        <p className="flex items-center gap-1.5 pt-3 pb-1 font-mono text-xs text-muted-foreground">
                            <Pin aria-hidden className="size-3" />
                            Pinned
                        </p>
                    </div>
                    <CommitCard
                        commit={pinnedCommit}
                        isHighlighted={
                            highlightedPeriod !== null &&
                            pinnedCommit.created_at.startsWith(
                                highlightedPeriod,
                            )
                        }
                        threadActions={threadActions?.(pinnedCommit)}
                    />
                </section>
            )}
            <InfiniteScroll
                data="commits"
                onlyNext
                preserveUrl
                loading={
                    <div role="status" aria-label="Loading more commits">
                        <CommitSkeleton previousLane={lastLane} />
                        <CommitSkeleton />
                    </div>
                }
            >
                {commits.data.map((commit, index) => {
                    const previous = commits.data[index - 1];
                    const previousLane = previous
                        ? laneColor(previous.user.lane)
                        : pinnedLane;
                    const startsDay =
                        !previous ||
                        localDayKey(previous.created_at) !==
                            localDayKey(commit.created_at);
                    const isGroupedWithPrevious =
                        previous?.user_id === commit.user_id &&
                        !startsDay &&
                        commit.id !== firstSeenId;

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
                                isGroupedWithPrevious={isGroupedWithPrevious}
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
                                threadActions={threadActions?.(commit)}
                                onAuthorHover={(isHovering) =>
                                    setHighlightedAuthor(
                                        isHovering ? commit.user_id : null,
                                    )
                                }
                            />
                            {index === interludeIndex &&
                                interlude?.(
                                    laneColor(commit.user.lane),
                                    isDimmed(commit.user_id),
                                )}
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

export function CommitSkeleton({ previousLane }: { previousLane?: string }) {
    return (
        <div aria-hidden className="flex">
            <GraphRail
                lane="var(--rail)"
                previousLane={previousLane ?? 'var(--rail)'}
                node={
                    <span className="size-12 rounded-full border-4 border-card bg-muted motion-safe:animate-pulse" />
                }
            />
            <div className="flex min-w-0 flex-1 border-b py-3 pr-4 motion-safe:animate-pulse">
                <div className="flex-1">
                    <span className="mt-[5px] mb-[7px] block h-3 w-2/5 rounded bg-muted" />
                    <span className="my-[5px] block h-3 w-4/5 rounded bg-muted" />
                    <span className="my-[5px] block h-3 w-3/5 rounded bg-muted" />
                    <span className="mt-3 mb-[7px] block h-4 w-9 rounded-md bg-muted" />
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
