import type { CSSProperties } from 'react';
import { cn } from '@/lib/utils';

type Props = {
    lane: string;
    previousLane?: string;
    variant?: 'commit' | 'staged' | 'root';
    isFilled?: boolean;
    isFresh?: boolean;
    hasTail?: boolean;
    isDimmed?: boolean;
    isPreviousDimmed?: boolean;
    onHoverChange?: (isHovering: boolean) => void;
};

const dimmable = 'transition-opacity duration-200';

export function GraphRail({
    lane,
    previousLane,
    variant = 'commit',
    isFilled = true,
    isFresh = false,
    hasTail = true,
    isDimmed = false,
    isPreviousDimmed = false,
    onHoverChange,
}: Props) {
    return (
        <div
            aria-hidden
            className="relative w-11 shrink-0 sm:w-14"
            style={{ '--lane': lane } as CSSProperties}
            onMouseEnter={onHoverChange && (() => onHoverChange(true))}
            onMouseLeave={onHoverChange && (() => onHoverChange(false))}
        >
            {previousLane && variant === 'staged' && (
                <span className="absolute top-0 left-1/2 h-8 -translate-x-1/2 border-l-2 border-dashed border-rail" />
            )}

            {previousLane && variant !== 'staged' && (
                <span
                    className={cn(
                        'absolute top-0 left-1/2 h-8 w-0.5 -translate-x-1/2',
                        dimmable,
                        { 'opacity-30': isPreviousDimmed },
                    )}
                    style={{ background: previousLane }}
                />
            )}

            {hasTail &&
                (variant === 'staged' ? (
                    <span className="absolute top-8 bottom-0 left-1/2 -translate-x-1/2 border-l-2 border-dashed border-rail" />
                ) : (
                    <span
                        className={cn(
                            'absolute top-8 bottom-0 left-1/2 w-0.5 -translate-x-1/2 bg-(--lane)',
                            dimmable,
                            { 'opacity-30': isDimmed },
                        )}
                    />
                ))}

            <span
                className={cn(
                    'absolute top-8 left-1/2 size-3.5 -translate-1/2 rounded-full border-[3px] border-card bg-(--lane) shadow-[0_0_0_2px_var(--lane)] transition-[background-color,opacity,scale] duration-200',
                    {
                        'bg-card': !isFilled || variant === 'root',
                        'motion-safe:animate-node-in': isFresh,
                        'opacity-30': isDimmed,
                        'cursor-pointer hover:scale-125': onHoverChange,
                    },
                )}
            />
        </div>
    );
}

export function RailSegment({
    lane,
    isDimmed = false,
}: {
    lane?: string;
    isDimmed?: boolean;
}) {
    return (
        <div aria-hidden className="relative w-11 shrink-0 sm:w-14">
            {lane && (
                <span
                    className={cn(
                        'absolute inset-y-0 left-1/2 w-0.5 -translate-x-1/2',
                        dimmable,
                        { 'opacity-30': isDimmed },
                    )}
                    style={{ background: lane }}
                />
            )}
        </div>
    );
}
