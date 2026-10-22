import type { CSSProperties, ReactNode } from 'react';
import { GraphAnchor, WithoutGraph } from '@/components/graph-canvas';
import { LiveAvatarMarker } from '@/components/live-stream-card';
import { UserAvatar } from '@/components/user-avatar';
import { cn, laneStrokeStyle } from '@/lib/utils';
import type { User } from '@/types';

type Props = {
    lane: string;
    previousLane?: string;
    variant?: 'commit' | 'staged' | 'root';
    isFilled?: boolean;
    isFresh?: boolean;
    hasTail?: boolean;
    hasReplyTail?: boolean;
    endsWithDot?: boolean;
    isDimmed?: boolean;
    isPreviousDimmed?: boolean;
    node?: ReactNode;
    nodePosition?: 'top' | 'center' | 'bottom' | 'author';
    onHoverChange?: (isHovering: boolean) => void;
    /** The node's place in a graph canvas, which then draws the lines instead of the rail. */
    graph?: {
        id: string;
        after?: string;
        endId?: string;
        ring?: number;
        /** False when the node registers itself, because it moves inside the rail. */
        measuresNode?: boolean;
    };
};

const dimmable = 'transition-opacity duration-200';

const stagedDash = 4;

/**
 * A fresh commit's end dot pops once its avatar has scaled in (700ms) and its line looks drawn: the draw eases out, so
 * the line reads as whole well before its 500ms end.
 */
const endDotDelay = 900;

/** A rail dot's ring is a 2px box-shadow, outside its box. */
export const railDotRing = 2;

// Nodes fade inside an opaque backdrop so the lane line behind them stays hidden.
const dimmedNode = 'group-data-dimmed/rail-node:opacity-60';

export function GraphRail({
    lane,
    previousLane,
    variant = 'commit',
    isFilled = true,
    isFresh = false,
    hasTail = true,
    hasReplyTail = false,
    endsWithDot = false,
    isDimmed = false,
    isPreviousDimmed = false,
    node,
    nodePosition = 'top',
    onHoverChange,
    graph,
}: Props) {
    return (
        <div
            aria-hidden
            className={cn('relative min-h-16 w-16 shrink-0 sm:w-18', {
                'min-h-24': endsWithDot || nodePosition === 'bottom',
            })}
            style={{ '--lane': lane } as CSSProperties}
            onPointerEnter={
                onHoverChange &&
                ((event) =>
                    event.pointerType === 'mouse' && onHoverChange(true))
            }
            onPointerLeave={
                onHoverChange &&
                ((event) =>
                    event.pointerType === 'mouse' && onHoverChange(false))
            }
        >
            <WithoutGraph>
                {previousLane && variant === 'staged' && (
                    <span className="absolute top-0 left-1/2 h-8 -translate-x-1/2 border-l-2 border-dashed border-rail" />
                )}

                {previousLane && variant !== 'staged' && (
                    <span
                        className={cn(
                            // Overlap the tail by 1px so abutting edges don't flash a hairline gap.
                            'absolute top-0 left-1/2 h-[calc(2rem+1px)] w-0.5 -translate-x-1/2',
                            dimmable,
                            {
                                'opacity-60': isPreviousDimmed,
                                'h-[calc(50%+1px)]': nodePosition === 'center',
                                'h-6': nodePosition === 'author',
                                'bottom-5 h-auto': nodePosition === 'bottom',
                            },
                        )}
                        style={{ background: previousLane }}
                        data-test="rail-previous"
                    />
                )}

                {hasTail &&
                    (variant === 'staged' ? (
                        <span
                            className="absolute top-8 bottom-0 left-1/2 w-0.5 -translate-x-1/2"
                            style={laneStrokeStyle('var(--rail)', {
                                dash: stagedDash,
                                anchor: 'end',
                            })}
                            data-test="rail-staged-tail"
                        />
                    ) : (
                        <span
                            className={cn(
                                // Start 1px early so this meets the previous segment without a seam.
                                'absolute top-[calc(2rem-1px)] bottom-0 left-1/2 w-0.5 -translate-x-1/2 bg-(--lane)',
                                dimmable,
                                {
                                    'opacity-60': isDimmed,
                                    'bottom-5': endsWithDot,
                                    'top-[calc(50%-1px)]':
                                        nodePosition === 'center',
                                    'top-[22px]': nodePosition === 'author',
                                },
                            )}
                            data-test="rail-tail"
                        />
                    ))}

                {hasReplyTail && (
                    <span
                        className={cn(
                            'absolute top-8 bottom-0 left-1/2 -translate-x-1/2 border-l border-dashed border-(--lane)',
                            dimmable,
                            {
                                'opacity-60': isDimmed,
                                'top-[23px]': nodePosition === 'author',
                                'top-1/2': nodePosition === 'center',
                                'top-auto h-5':
                                    endsWithDot || nodePosition === 'bottom',
                            },
                        )}
                        data-test="commit-reply-spine"
                    />
                )}
            </WithoutGraph>

            {hasTail && endsWithDot && (
                <GraphAnchor
                    spec={
                        graph?.endId
                            ? {
                                  id: graph.endId,
                                  lane,
                                  after: graph.id,
                                  dimmed: isDimmed,
                                  ring: railDotRing,
                              }
                            : null
                    }
                    data-dimmed={isDimmed || undefined}
                    isFresh={isFresh}
                    className="group/rail-node absolute bottom-5 left-1/2 flex -translate-x-1/2 translate-y-1/2 rounded-full"
                    style={
                        isFresh
                            ? { animationDelay: `${endDotDelay}ms` }
                            : undefined
                    }
                    data-test="rail-end-dot"
                >
                    <RailDot />
                </GraphAnchor>
            )}

            <GraphAnchor
                spec={
                    graph && graph.measuresNode !== false
                        ? {
                              id: graph.id,
                              lane,
                              after: graph.after,
                              dimmed: isDimmed,
                              ring: graph.ring,
                          }
                        : null
                }
                data-dimmed={isDimmed || undefined}
                isFresh={isFresh}
                className={cn(
                    'group/rail-node absolute top-8 left-1/2 flex -translate-1/2 rounded-full',
                    {
                        'top-1/2': nodePosition === 'center',
                        'top-[23px]': nodePosition === 'author',
                        'top-auto bottom-5 translate-y-1/2':
                            nodePosition === 'bottom',
                    },
                )}
            >
                {node ?? <RailDot isHollow={!isFilled || variant === 'root'} />}
            </GraphAnchor>
        </div>
    );
}

export function RailDot({
    isHollow = false,
    className,
}: {
    isHollow?: boolean;
    className?: string;
}) {
    return (
        <span className="flex rounded-full bg-card">
            <span
                className={cn(
                    'size-3.5 rounded-full border-[3px] border-card bg-(--lane) shadow-[0_0_0_2px_var(--lane)] transition-[background-color,scale,opacity] duration-200',
                    dimmedNode,
                    { 'bg-card': isHollow },
                    className,
                )}
            />
        </span>
    );
}

export function RailAvatar({
    user,
    progress,
}: {
    user: Pick<User, 'name' | 'avatar_url' | 'live'>;
    // Fills the ring clockwise from 0 to 1, as a post grows toward its limit. At 0 the ring stays dashed.
    progress?: number;
}) {
    const isLive = Boolean(user.live);

    return (
        <span className="relative flex rounded-full bg-card">
            <span
                className={cn(
                    'relative flex rounded-full border-2 border-(--lane) p-0.5 transition-opacity duration-200',
                    dimmedNode,
                    {
                        'border-red-600': isLive,
                        'border-transparent': progress !== undefined,
                    },
                )}
            >
                {progress !== undefined && (
                    <>
                        <span
                            className={cn(
                                'pointer-events-none absolute -inset-0.5 rounded-full border-2 border-dashed transition-opacity duration-150 ease-out motion-reduce:transition-none',
                                isLive ? 'border-red-600' : 'border-(--lane)',
                                progress > 0 ? 'opacity-0' : 'opacity-100',
                            )}
                        />
                        <svg
                            viewBox="0 0 48 48"
                            className={cn(
                                'pointer-events-none absolute -inset-0.5 size-12 -rotate-90 transition-opacity duration-150 ease-out motion-reduce:transition-none',
                                progress > 0 ? 'opacity-100' : 'opacity-0',
                            )}
                            fill="none"
                            strokeWidth={2}
                        >
                            <circle
                                cx="24"
                                cy="24"
                                r="23"
                                className={cn(
                                    isLive
                                        ? 'stroke-red-600'
                                        : 'stroke-(--lane)',
                                    'opacity-25',
                                )}
                            />
                            <circle
                                cx="24"
                                cy="24"
                                r="23"
                                pathLength={1}
                                strokeDasharray={1}
                                strokeLinecap="round"
                                strokeDashoffset={1 - Math.min(progress, 1)}
                                className={cn(
                                    isLive
                                        ? 'stroke-red-600'
                                        : 'stroke-(--lane)',
                                    'transition-[stroke-dashoffset] duration-150 ease-out motion-reduce:transition-none',
                                )}
                            />
                        </svg>
                    </>
                )}
                <UserAvatar user={user} className="size-10" />
            </span>
            {isLive && (
                <LiveAvatarMarker className="px-1 text-[9px] leading-3.5" />
            )}
        </span>
    );
}

export function RailSegment({
    lane,
    isDimmed = false,
    isDashed = false,
    className,
}: {
    lane?: string;
    isDimmed?: boolean;
    isDashed?: boolean;
    className?: string;
}) {
    return (
        <div
            aria-hidden
            className={cn('relative w-16 shrink-0 sm:w-18', className)}
        >
            {lane &&
                (isDashed ? (
                    // The dashed rail carries the composer's on, which no graph canvas draws.
                    <span
                        className="absolute inset-y-0 left-1/2 w-0.5 -translate-x-1/2"
                        style={laneStrokeStyle(lane, { dash: stagedDash })}
                    />
                ) : (
                    <WithoutGraph>
                        <span
                            className={cn(
                                'absolute inset-y-0 left-1/2 w-0.5 -translate-x-1/2',
                                dimmable,
                                { 'opacity-60': isDimmed },
                            )}
                            style={{ background: lane }}
                        />
                    </WithoutGraph>
                ))}
        </div>
    );
}
