import type { CSSProperties } from 'react';
import { cn } from '@/lib/utils';

export function Waveform({
    levels,
    className,
    style,
}: {
    levels: number[];
    className?: string;
    style?: CSSProperties;
}) {
    return (
        <div
            aria-hidden
            className={cn('flex h-full items-center gap-px', className)}
            style={style}
        >
            {levels.map((level, index) => (
                <span
                    key={index}
                    className="min-w-0 flex-1 rounded-[1px] bg-current"
                    style={{ height: `${Math.max(10, level)}%` }}
                />
            ))}
        </div>
    );
}
