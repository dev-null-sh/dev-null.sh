import type { CSSProperties, ReactNode } from 'react';
import { UserAvatar } from '@/components/user-avatar';
import { cn } from '@/lib/utils';
import type { User } from '@/types';

type Props = {
    lane: string;
    previousLane?: string;
    variant?: 'commit' | 'staged' | 'root';
    isFilled?: boolean;
    isFresh?: boolean;
    hasTail?: boolean;
    isDimmed?: boolean;
    isPreviousDimmed?: boolean;
    node?: ReactNode;
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
    node,
    onHoverChange,
}: Props) {
    return (
        <div
            aria-hidden
            className="relative min-h-16 w-16 shrink-0 sm:w-18"
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
                    'absolute top-8 left-1/2 flex -translate-1/2 rounded-full',
                    dimmable,
                    {
                        'motion-safe:animate-node-in': isFresh,
                        'opacity-30': isDimmed,
                    },
                )}
            >
                {node ?? <RailDot isHollow={!isFilled || variant === 'root'} />}
            </span>
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
        <span
            className={cn(
                'size-3.5 rounded-full border-[3px] border-card bg-(--lane) shadow-[0_0_0_2px_var(--lane)] transition-[background-color,scale] duration-200',
                { 'bg-card': isHollow },
                className,
            )}
        />
    );
}

export function RailAvatar({
    user,
    isStaged = false,
}: {
    user: Pick<User, 'name' | 'avatar_url'>;
    isStaged?: boolean;
}) {
    return (
        <span
            className={cn(
                'flex rounded-full border-2 border-(--lane) bg-card p-0.5 transition-colors duration-200',
                { 'border-dashed': isStaged },
            )}
        >
            <UserAvatar user={user} className="size-10" />
        </span>
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
        <div aria-hidden className="relative w-16 shrink-0 sm:w-18">
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
