import { InfiniteScroll, router, usePage } from '@inertiajs/react';
import { Pin } from 'lucide-react';
import type { PointerEvent, ReactNode } from 'react';
import {
    Fragment,
    useEffect,
    useEffectEvent,
    useRef,
    useState,
    useSyncExternalStore,
} from 'react';
import { CommitCard } from '@/components/commit-card';
import { GraphCanvas } from '@/components/graph-canvas';
import { GraphRail, RailSegment } from '@/components/graph-rail';
import { useNow } from '@/hooks/use-now';
import {
    restoreFeedCommit,
    updateQuotedCommits,
} from '@/lib/commit-mutation-options';
import type { FeedHighlightStore } from '@/lib/feed-hover';
import {
    createFeedHighlight,
    isDeliberateHover,
    isDimmedBy,
} from '@/lib/feed-hover';
import { authorLaneColor, cn, formatDayLabel, localDayKey } from '@/lib/utils';
import type { Commit, CursorPaginated } from '@/types';

const interludeAfter = 5;

function commitCardAt(point: { x: number; y: number } | null) {
    return point
        ? document
              .elementFromPoint(point.x, point.y)
              ?.closest<HTMLElement>('[data-commit]')
        : null;
}

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
    /**
     * Shown once the feed reached its end, and left out while what follows it is being read again.
     */
    end?: string;
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
    const isModeration = page.component === 'moderation/show';
    const isStarred = page.component === 'starred/index';
    const profileUserId =
        page.component === 'user/show'
            ? (page.props.user as { id: string }).id
            : null;
    const isProfileReplies =
        profileUserId !== null && page.props.tab === 'replies';
    // The organization's Mentions tab lists replies too, whatever handle they mention it with.
    const mentionedOrganizationId =
        page.component === 'organization/show'
            ? (page.props.organization as { id: string }).id
            : null;
    const [highlight] = useState(createFeedHighlight);
    const unhighlightTimer = useRef<ReturnType<typeof setTimeout>>(undefined);
    const isScrolling = useRef(false);
    const authorUnderPointer = useRef<string | null>(null);
    const pointer = useRef<{ x: number; y: number } | null>(null);
    const litCard = useRef<HTMLElement | null>(null);
    const isPointerOnCommit = useRef(false);
    const commitMove = useRef<Event | null>(null);
    const listedIds = useRef(new Set<string>());

    // Crossing a day marker between two commits shouldn't flash the whole feed back on. A commit that scrolled away
    // can report leaving after the one under the still pointer lit up, so only a pointer off the lit commit clears it.
    const unhighlightSoon = () => {
        clearTimeout(unhighlightTimer.current);
        unhighlightTimer.current = setTimeout(() => {
            if (commitCardAt(pointer.current) !== litCard.current) {
                highlight.set({ commitId: null });
            }
        }, 150);
    };

    // A menu closing under the pointer, or a deleted commit, never reports a leave, so a move that reaches no commit
    // counts as leaving them.
    const leaveCommits = useEffectEvent(() => {
        if (isPointerOnCommit.current) {
            isPointerOnCommit.current = false;
            unhighlightSoon();
        }
    });

    useEffect(() => () => clearTimeout(unhighlightTimer.current), []);

    useEffect(() => {
        let settleTimer: ReturnType<typeof setTimeout> | undefined;
        let settleFrame = 0;
        let lastWheelAt = -Infinity;
        let isWheelScroll = false;

        // Once a wheel scroll stops, the commit resting under the still pointer lights up as if the pointer had moved
        // onto it. Keyboard scrolling leaves the feed alone, since the pointer isn't what the reader is following.
        const highlightUnderPointer = () => {
            const card = commitCardAt(pointer.current);
            const commitId = card?.dataset.commit;

            if (card && commitId && listedIds.current.has(commitId)) {
                litCard.current = card;
                isPointerOnCommit.current = true;
                highlight.set({ commitId, authorId: null });
            }
        };

        const unhighlight = () => {
            const now = performance.now();

            // A smooth wheel or a fling keeps scrolling after its last wheel event, and a long render can split it in
            // two, so a scroll that starts within a second of wheel scrolling continues it.
            if (!isScrolling.current) {
                isWheelScroll = now - lastWheelAt < 1000;
            }

            if (isWheelScroll) {
                lastWheelAt = now;
            }

            isScrolling.current = true;
            clearTimeout(unhighlightTimer.current);
            highlight.set({ commitId: null, authorId: null });

            clearTimeout(settleTimer);
            cancelAnimationFrame(settleFrame);
            // A long render can hold back the scroll events of a frame past the delay, and a frame dispatches them before
            // its animation callbacks, so a scroll still under way cancels the settle.
            settleTimer = setTimeout(() => {
                settleFrame = requestAnimationFrame(() => {
                    isScrolling.current = false;

                    if (isWheelScroll) {
                        highlightUnderPointer();
                    }
                });
            }, 300);
        };

        const trackWheel = (event: WheelEvent) => {
            if (event.ctrlKey || event.deltaY === 0) {
                return;
            }

            lastWheelAt = performance.now();
            isWheelScroll ||= isScrolling.current;
        };

        // A key or a press can start a scroll of its own, such as a shortcut or a button that scrolls to the top.
        const forgetWheel = (
            event: KeyboardEvent | globalThis.PointerEvent,
        ) => {
            if (
                'key' in event &&
                ['Alt', 'Control', 'Meta', 'Shift'].includes(event.key)
            ) {
                return;
            }

            lastWheelAt = -Infinity;
            isWheelScroll = false;
        };

        const trackPointer = (event: globalThis.PointerEvent) => {
            if (event.pointerType !== 'mouse') {
                return;
            }

            pointer.current = { x: event.clientX, y: event.clientY };

            if (event !== commitMove.current) {
                leaveCommits();
            }
        };

        const forgetPointer = (event: globalThis.PointerEvent) => {
            if (event.pointerType === 'mouse' && event.relatedTarget === null) {
                pointer.current = null;
                leaveCommits();
            }
        };

        window.addEventListener('scroll', unhighlight, { passive: true });
        window.addEventListener('wheel', trackWheel, { passive: true });
        window.addEventListener('keydown', forgetWheel);
        window.addEventListener('pointerdown', forgetWheel);
        window.addEventListener('pointermove', trackPointer, { passive: true });
        document.addEventListener('pointerout', forgetPointer);

        return () => {
            window.removeEventListener('scroll', unhighlight);
            window.removeEventListener('wheel', trackWheel);
            window.removeEventListener('keydown', forgetWheel);
            window.removeEventListener('pointerdown', forgetWheel);
            window.removeEventListener('pointermove', trackPointer);
            document.removeEventListener('pointerout', forgetPointer);
            clearTimeout(settleTimer);
            cancelAnimationFrame(settleFrame);
        };
    }, [highlight]);

    const highlightAuthor = (userId: string, isHovering: boolean) => {
        authorUnderPointer.current = isHovering ? userId : null;

        if (!isHovering) {
            highlight.set({ authorId: null });
        } else if (!isScrolling.current) {
            highlight.set({ authorId: userId });
        }
    };

    const highlightCommit = (
        commitId: string,
        isHovering: boolean,
        event: PointerEvent<HTMLElement>,
    ) => {
        if (isHovering) {
            commitMove.current = event.nativeEvent;
            isPointerOnCommit.current = true;

            if (isDeliberateHover(event, isScrolling.current)) {
                clearTimeout(unhighlightTimer.current);
                litCard.current = event.currentTarget;
                // Author targets only report entering, so one that slid under the pointer while scrolling lights up on the next move.
                highlight.set({
                    commitId,
                    authorId: authorUnderPointer.current,
                });
            }

            return;
        }

        if (event.pointerType !== 'mouse') {
            return;
        }

        isPointerOnCommit.current = false;
        unhighlightSoon();
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
                        (isStarred
                            ? restored.starred === true
                            : mentionedOrganizationId !== null
                              ? Object.values(
                                    restored.organization_mentions ?? {},
                                ).includes(mentionedOrganizationId)
                              : isProfileReplies
                                ? restored.parent_id !== null
                                : restored.parent_id === null) &&
                        (profileUserId === null ||
                            (restored.author.id === profileUserId &&
                                !restored.is_pinned));

                    router.replaceProp('commits.data', (rows: Commit[]) =>
                        belongsInFeed
                            ? restoreFeedCommit(
                                  rows,
                                  restored,
                                  isStarred
                                      ? 'starred'
                                      : isRanked
                                        ? 'ranked'
                                        : 'latest',
                              )
                            : updateQuotedCommits(rows, restored),
                    );
                }
            }),
        [
            profileUserId,
            isModeration,
            isProfileReplies,
            mentionedOrganizationId,
            isStarred,
            isRanked,
        ],
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

    useEffect(() => {
        listedIds.current = new Set(
            [...listed, ...(pinnedCommit ? [pinnedCommit] : [])].map(
                (commit) => commit.id,
            ),
        );

        const { commitId } = highlight.get();

        if (commitId !== null && !listedIds.current.has(commitId)) {
            authorUnderPointer.current = null;
            highlight.set({ commitId: null, authorId: null });
        }
    });

    if (listed.length === 0 && pinnedCommit === null) {
        return <div className="px-6 py-16 text-center sm:px-12">{empty}</div>;
    }

    const lastCommit = listed.at(-1) ?? pinnedCommit;
    const interludeIndex = Math.min(interludeAfter, listed.length) - 1;

    // Feed commits are unrelated to each other, so each one stands on its own with its author's avatar, even when the
    // same author committed several in a row.
    const card = (commit: Commit) => (
        <Highlighted highlight={highlight} commit={commit}>
            {({ isDimmed, isHovered }) => (
                <CommitCard
                    commit={commit}
                    endsWithDot
                    isDimmed={isDimmed}
                    isHovered={isHovered}
                    isHighlighted={
                        highlightedPeriod !== null &&
                        commit.created_at.startsWith(highlightedPeriod)
                    }
                    actions={actions?.(commit)}
                    threadActions={threadActions?.(commit)}
                    isFolded={isFolded}
                    showsParent={isModeration}
                    onAuthorHover={(isHovering) =>
                        highlightAuthor(commit.author.id, isHovering)
                    }
                    onHover={(isHovering, event) =>
                        highlightCommit(commit.id, isHovering, event)
                    }
                />
            )}
        </Highlighted>
    );

    return (
        <GraphCanvas>
            {pinnedCommit && (
                <section aria-label="Pinned commit">
                    <div className="flex">
                        <RailSegment />
                        <p className="flex items-center gap-1.5 pt-3 pb-1 font-mono text-xs text-muted-foreground">
                            <Pin aria-hidden className="size-3" />
                            Pinned
                        </p>
                    </div>
                    <Highlighted highlight={highlight} commit={pinnedCommit}>
                        {({ isDimmed, isHovered }) => (
                            <CommitCard
                                commit={pinnedCommit}
                                endsWithDot
                                isDimmed={isDimmed}
                                isHovered={isHovered}
                                onHover={(isHovering, event) =>
                                    highlightCommit(
                                        pinnedCommit.id,
                                        isHovering,
                                        event,
                                    )
                                }
                                isHighlighted={
                                    highlightedPeriod !== null &&
                                    pinnedCommit.created_at.startsWith(
                                        highlightedPeriod,
                                    )
                                }
                                threadActions={threadActions?.(pinnedCommit)}
                            />
                        )}
                    </Highlighted>
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
                            {index === interludeIndex && interlude && (
                                <Highlighted
                                    highlight={highlight}
                                    commit={commit}
                                >
                                    {({ isDimmed }) =>
                                        interlude(
                                            authorLaneColor(commit.author),
                                            isDimmed,
                                        )
                                    }
                                </Highlighted>
                            )}
                        </Fragment>
                    );
                })}
            </InfiniteScroll>

            {commits.next_cursor === null &&
                lastCommit &&
                end !== undefined && (
                    <div className="flex">
                        <GraphRail
                            lane={authorLaneColor(lastCommit.author)}
                            variant="root"
                            hasTail={false}
                        />
                        <p className="pt-[1.3rem] pr-4 pb-10 text-sm text-muted-foreground">
                            {end}
                        </p>
                    </div>
                )}
        </GraphCanvas>
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

function Highlighted({
    highlight,
    commit,
    children,
}: {
    highlight: FeedHighlightStore;
    commit: Commit;
    children: (state: { isDimmed: boolean; isHovered: boolean }) => ReactNode;
}) {
    const { dimsLanes } = usePage().props;
    const isDimmed = useSyncExternalStore(
        highlight.subscribe,
        () => dimsLanes && isDimmedBy(highlight.get(), commit),
        () => false,
    );
    const isHovered = useSyncExternalStore(
        highlight.subscribe,
        () => highlight.get().commitId === commit.id,
        () => false,
    );

    return children({ isDimmed, isHovered });
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
