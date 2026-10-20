import { InfiniteScroll, router, usePage } from '@inertiajs/react';
import { Pin } from 'lucide-react';
import type { PointerEvent, ReactNode } from 'react';
import { Fragment, useEffect, useRef, useState } from 'react';
import { CommitCard } from '@/components/commit-card';
import { GraphRail, RailSegment } from '@/components/graph-rail';
import { useNow } from '@/hooks/use-now';
import {
    restoreFeedCommit,
    updateQuotedCommits,
} from '@/lib/commit-mutation-options';
import { isDeliberateHover } from '@/lib/feed-hover';
import { cn, formatDayLabel, laneColor, localDayKey } from '@/lib/utils';
import type { Commit, CursorPaginated } from '@/types';

const interludeAfter = 5;

export function CommitFeed({
    commits,
    empty,
    end,
    highlightedPeriod = null,
    firstSeenId = null,
    actions,
    threadActions,
    pinnedCommit = null,
    isRanked = false,
    isFolded = true,
    interlude,
    isBelowComposer = false,
}: {
    commits: CursorPaginated<Commit>;
    empty: ReactNode;
    end: string;
    highlightedPeriod?: string | null;
    firstSeenId?: string | null;
    actions?: (commit: Commit) => ReactNode;
    threadActions?: (commit: Commit) => ReactNode;
    pinnedCommit?: Commit | null;
    isRanked?: boolean;
    isFolded?: boolean;
    interlude?: (lane: string, isDimmed: boolean) => ReactNode;
    isBelowComposer?: boolean;
}) {
    const now = useNow();
    const page = usePage();
    const { dimsLanes } = page.props;
    const isModeration = page.component === 'moderation/show';
    const isSaved = page.component === 'bookmark/index';
    const profileUserId =
        page.component === 'user/show'
            ? (page.props.user as { id: string }).id
            : null;
    const isProfileReplies =
        profileUserId !== null && page.props.tab === 'replies';
    const [highlightedAuthor, setHighlightedAuthor] = useState<string | null>(
        null,
    );
    const [highlightedCommit, setHighlightedCommit] = useState<string | null>(
        null,
    );
    const unhighlightTimer = useRef<ReturnType<typeof setTimeout>>(undefined);
    const isScrolling = useRef(false);
    const authorUnderPointer = useRef<string | null>(null);

    useEffect(() => () => clearTimeout(unhighlightTimer.current), []);

    useEffect(() => {
        let settleTimer: ReturnType<typeof setTimeout> | undefined;

        const unhighlight = () => {
            isScrolling.current = true;
            clearTimeout(unhighlightTimer.current);
            setHighlightedCommit(null);
            setHighlightedAuthor(null);

            clearTimeout(settleTimer);
            settleTimer = setTimeout(() => {
                isScrolling.current = false;
            }, 300);
        };

        window.addEventListener('scroll', unhighlight, { passive: true });

        return () => {
            window.removeEventListener('scroll', unhighlight);
            clearTimeout(settleTimer);
        };
    }, []);

    const highlightAuthor = (userId: string, isHovering: boolean) => {
        authorUnderPointer.current = isHovering ? userId : null;

        if (!isHovering) {
            setHighlightedAuthor(null);
        } else if (!isScrolling.current) {
            setHighlightedAuthor(userId);
        }
    };

    const highlightCommit = (
        commitId: string,
        isHovering: boolean,
        event: PointerEvent<HTMLElement>,
    ) => {
        if (isHovering) {
            if (isDeliberateHover(event, isScrolling.current)) {
                clearTimeout(unhighlightTimer.current);
                setHighlightedCommit(commitId);
                // Author targets only report entering, so one that slid under the pointer while scrolling lights up on the next move.
                setHighlightedAuthor(authorUnderPointer.current);
            }

            return;
        }

        clearTimeout(unhighlightTimer.current);

        // Crossing a day marker between two commits shouldn't flash the whole feed back on.
        unhighlightTimer.current = setTimeout(
            () => setHighlightedCommit(null),
            150,
        );
    };

    useEffect(
        () =>
            router.on('flash', (event) => {
                if (isModeration) {
                    return;
                }

                const { deleted, restored } = event.detail.flash;
                if (deleted) {
                    router.replaceProp('commits.data', (rows: Commit[]) =>
                        updateQuotedCommits(
                            rows.filter((commit) => commit.id !== deleted.id),
                            deleted,
                        ),
                    );
                }
                if (restored) {
                    const belongsInFeed =
                        (isSaved
                            ? restored.is_saved === true
                            : isProfileReplies
                              ? restored.parent_id !== null
                              : restored.parent_id === null) &&
                        (profileUserId === null ||
                            (restored.user_id === profileUserId &&
                                !restored.is_pinned));

                    router.replaceProp('commits.data', (rows: Commit[]) =>
                        belongsInFeed
                            ? restoreFeedCommit(
                                  rows,
                                  restored,
                                  isSaved
                                      ? 'saved'
                                      : isRanked
                                        ? 'ranked'
                                        : 'latest',
                              )
                            : updateQuotedCommits(rows, restored),
                    );
                }
            }),
        [profileUserId, isModeration, isProfileReplies, isSaved, isRanked],
    );

    // A ranked commit can move to a later page while the feed scrolls, so each one is only listed once.
    const seenIds = new Set<string>();
    const listed = commits.data.filter((commit) => {
        if (seenIds.has(commit.id)) {
            return false;
        }

        seenIds.add(commit.id);

        return true;
    });

    if (listed.length === 0 && pinnedCommit === null) {
        return <div className="px-6 py-16 text-center sm:px-12">{empty}</div>;
    }

    const isDimmed = (commit: Commit) =>
        dimsLanes &&
        (highlightedAuthor !== null
            ? commit.user_id !== highlightedAuthor
            : highlightedCommit !== null && commit.id !== highlightedCommit);

    const lastCommit = listed.at(-1) ?? pinnedCommit;
    const interludeIndex = Math.min(interludeAfter, listed.length) - 1;

    // Feed commits are unrelated to each other, so each one stands on its own with its author's avatar, even when the
    // same author committed several in a row.
    const card = (commit: Commit) => (
        <CommitCard
            commit={commit}
            endsWithDot
            isDimmed={isDimmed(commit)}
            isHighlighted={
                highlightedPeriod !== null &&
                commit.created_at.startsWith(highlightedPeriod)
            }
            actions={actions?.(commit)}
            threadActions={threadActions?.(commit)}
            isFolded={isFolded}
            onAuthorHover={(isHovering) =>
                highlightAuthor(commit.user_id, isHovering)
            }
            onHover={(isHovering, event) =>
                highlightCommit(commit.id, isHovering, event)
            }
        />
    );

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
                        endsWithDot
                        isDimmed={isDimmed(pinnedCommit)}
                        onHover={(isHovering, event) =>
                            highlightCommit(pinnedCommit.id, isHovering, event)
                        }
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
                        <CommitSkeleton />
                        <CommitSkeleton />
                    </div>
                }
            >
                {listed.map((commit, index) => {
                    const previous = listed[index - 1];
                    const startsDay =
                        !isRanked &&
                        (!previous ||
                            localDayKey(previous.created_at) !==
                                localDayKey(commit.created_at));
                    const stagedLane =
                        isBelowComposer && index === 0 && !pinnedCommit
                            ? 'var(--rail)'
                            : undefined;

                    return (
                        <Fragment key={commit.id}>
                            {!isRanked && commit.id === firstSeenId && (
                                <LastVisitMarker stagedLane={stagedLane} />
                            )}
                            {startsDay && (
                                <DayMarker
                                    label={formatDayLabel(
                                        commit.created_at,
                                        now,
                                    )}
                                    stagedLane={stagedLane}
                                />
                            )}
                            {card(commit)}
                            {index === interludeIndex &&
                                interlude?.(
                                    laneColor(commit.user.lane),
                                    isDimmed(commit),
                                )}
                        </Fragment>
                    );
                })}
            </InfiniteScroll>

            {commits.next_cursor === null && lastCommit && (
                <div className="flex">
                    <GraphRail
                        lane={laneColor(lastCommit.user.lane)}
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
    stagedLane,
}: {
    label: string;
    stagedLane?: string;
}) {
    return (
        <div className="flex">
            <RailSegment
                lane={stagedLane}
                isDashed
                className={cn(
                    stagedLane &&
                        '[view-transition-class:staged-rail] [view-transition-name:staged-rail-day]',
                )}
            />
            <div className="flex flex-1 items-center gap-3 border-b py-2 pr-4">
                <h3 className="font-mono text-xs text-muted-foreground">
                    {label}
                </h3>
            </div>
        </div>
    );
}

function LastVisitMarker({ stagedLane }: { stagedLane?: string }) {
    return (
        <div
            role="separator"
            aria-label="Commits above are new since your last visit"
            className="flex"
        >
            <RailSegment
                lane={stagedLane}
                isDashed
                className={cn(
                    stagedLane &&
                        '[view-transition-class:staged-rail] [view-transition-name:staged-rail-visit]',
                )}
            />
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
            <GraphRail
                lane="var(--rail)"
                endsWithDot
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
