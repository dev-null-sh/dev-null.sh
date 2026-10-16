import type { CSSProperties } from 'react';
import { cn } from '@/lib/utils';

const drops = [
    { left: '5%', delay: 0, duration: 4600, spin: '70deg', drift: '6px' },
    { left: '15%', delay: 2600, duration: 5200, spin: '-90deg', drift: '-5px' },
    { left: '25%', delay: 1100, duration: 4400, spin: '60deg', drift: '-7px' },
    { left: '35%', delay: 3600, duration: 5000, spin: '-110deg', drift: '5px' },
    { left: '45%', delay: 500, duration: 5400, spin: '80deg', drift: '-6px' },
    { left: '55%', delay: 2100, duration: 4700, spin: '-60deg', drift: '7px' },
    { left: '65%', delay: 4200, duration: 5100, spin: '100deg', drift: '-5px' },
    { left: '75%', delay: 1500, duration: 4500, spin: '-80deg', drift: '6px' },
    { left: '84%', delay: 3100, duration: 5300, spin: '90deg', drift: '-6px' },
    { left: '92%', delay: 800, duration: 4800, spin: '-70deg', drift: '5px' },
];

/**
 * Mono `//` that drift down behind its container's content, slowly turning,
 * and dissolve before reaching the bottom. The container must be `relative isolate`.
 */
export function SlashRain({ className }: { className?: string }) {
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
                    className="absolute inset-y-0 animate-slash-fall font-mono text-xs leading-none text-muted-foreground"
                    style={
                        {
                            '--drift': drop.drift,
                            left: drop.left,
                            animationDelay: `${drop.delay}ms`,
                            animationDuration: `${drop.duration}ms`,
                        } as CSSProperties
                    }
                >
                    <span
                        className="inline-block animate-slash-spin tracking-[-0.35em]"
                        style={
                            {
                                '--spin': drop.spin,
                                animationDelay: `${drop.delay}ms`,
                                animationDuration: `${drop.duration}ms`,
                            } as CSSProperties
                        }
                    >
                        {'//'}
                    </span>
                </span>
            ))}
        </div>
    );
}
