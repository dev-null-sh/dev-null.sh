import type { ComponentProps, CSSProperties, ReactNode } from 'react';
import { cn, laneColor, laneTint } from '@/lib/utils';
import type { Lane } from '@/types';

/**
 * Covers are stored at 3:1 and shown on profiles at 3.5:1, which trims a fourteenth off their top and bottom. The settings
 * preview dims exactly those strips, so the two classes change together.
 */
export const profileCoverAspect = 'aspect-7/2';
export const profileCoverTrim = 'h-1/14';

/**
 * A cover in its frame, over the lane tint every profile without one shows.
 */
export function CoverImage({
    lane,
    src,
    alt = '',
    imageStyle,
    className,
    children,
    ...props
}: ComponentProps<'div'> & {
    lane: Lane;
    src: string | null;
    alt?: string;
    imageStyle?: CSSProperties;
}) {
    return (
        <div
            {...props}
            className={cn('relative overflow-hidden', className)}
            style={{ backgroundColor: laneTint(laneColor(lane), 20) }}
        >
            {src && (
                <img
                    src={src}
                    alt={alt}
                    width={1500}
                    height={500}
                    draggable={false}
                    className="pointer-events-none size-full object-cover"
                    style={imageStyle}
                />
            )}
            {children}
        </div>
    );
}

export function CoverPill({ children }: { children: ReactNode }) {
    return (
        <p className="pointer-events-none absolute top-2.5 left-2.5 flex items-center gap-1.5 rounded-full bg-black/70 px-2.5 py-1 text-xs font-medium text-white">
            {children}
        </p>
    );
}
