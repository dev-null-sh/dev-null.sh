import { cn } from '@/lib/utils';

export function Waveform({
    levels,
    className,
}: {
    levels: number[];
    className?: string;
}) {
    return (
        <div
            aria-hidden
            className={cn('flex h-full items-center gap-px', className)}
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
