import { cn } from '@/lib/utils';

const drops = [
    { left: '8%', delay: 0, duration: 1700 },
    { left: '21%', delay: 260, duration: 1900 },
    { left: '34%', delay: 90, duration: 1600 },
    { left: '47%', delay: 420, duration: 2000 },
    { left: '59%', delay: 170, duration: 1750 },
    { left: '71%', delay: 520, duration: 1850 },
    { left: '83%', delay: 40, duration: 1650 },
    { left: '92%', delay: 330, duration: 1950 },
];

/**
 * A light rain of mono "--" that falls once behind its container's content
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
                    className="absolute inset-y-0 animate-dash-fall font-mono text-[11px] leading-none text-muted-foreground/40"
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
