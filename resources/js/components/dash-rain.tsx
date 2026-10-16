import { cn } from '@/lib/utils';

const drops = [
    { left: '6%', delay: 0, duration: 2300 },
    { left: '14%', delay: 1300, duration: 2600 },
    { left: '23%', delay: 500, duration: 2200 },
    { left: '31%', delay: 1900, duration: 2500 },
    { left: '40%', delay: 200, duration: 2700 },
    { left: '49%', delay: 1100, duration: 2300 },
    { left: '57%', delay: 700, duration: 2600 },
    { left: '66%', delay: 2100, duration: 2400 },
    { left: '74%', delay: 300, duration: 2500 },
    { left: '82%', delay: 1600, duration: 2200 },
    { left: '89%', delay: 900, duration: 2700 },
    { left: '95%', delay: 2400, duration: 2400 },
];

/**
 * A light rain of mono "--" that keeps falling behind its container's content
 * and dissolves before reaching the bottom. The container must be `relative isolate`.
 */
export function DashRain({ className }: { className?: string }) {
    return (
        <div
            aria-hidden
            className={cn(
                'pointer-events-none absolute inset-0 -z-10 overflow-hidden select-none motion-reduce:hidden',
                className,
            )}
        >
            {drops.map((drop) => (
                <span
                    key={drop.left}
                    className="absolute inset-y-0 animate-dash-fall font-mono text-xs leading-none text-muted-foreground"
                    style={{
                        left: drop.left,
                        animationDelay: `${drop.delay}ms`,
                        animationDuration: `${drop.duration}ms`,
                    }}
                >
                    --
                </span>
            ))}
        </div>
    );
}
