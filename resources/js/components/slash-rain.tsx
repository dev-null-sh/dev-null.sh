import type { CSSProperties } from 'react';
import { cn } from '@/lib/utils';

const drops = [
    { left: '6%', delay: 0, duration: 2300, spin: '220deg' },
    { left: '14%', delay: 1300, duration: 2600, spin: '-180deg' },
    { left: '23%', delay: 500, duration: 2200, spin: '160deg' },
    { left: '31%', delay: 1900, duration: 2500, spin: '-240deg' },
    { left: '40%', delay: 200, duration: 2700, spin: '200deg' },
    { left: '49%', delay: 1100, duration: 2300, spin: '-150deg' },
    { left: '57%', delay: 700, duration: 2600, spin: '260deg' },
    { left: '66%', delay: 2100, duration: 2400, spin: '-200deg' },
    { left: '74%', delay: 300, duration: 2500, spin: '170deg' },
    { left: '82%', delay: 1600, duration: 2200, spin: '-230deg' },
    { left: '89%', delay: 900, duration: 2700, spin: '190deg' },
    { left: '95%', delay: 2400, duration: 2400, spin: '-170deg' },
];

/**
 * A light rain of mono slashes that spin as they fall behind its container's
 * content and dissolve before reaching the bottom. The container must be `relative isolate`.
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
                    style={{
                        left: drop.left,
                        animationDelay: `${drop.delay}ms`,
                        animationDuration: `${drop.duration}ms`,
                    }}
                >
                    <span
                        className="inline-block animate-slash-spin"
                        style={
                            {
                                '--spin': drop.spin,
                                animationDelay: `${drop.delay}ms`,
                                animationDuration: `${drop.duration}ms`,
                            } as CSSProperties
                        }
                    >
                        /
                    </span>
                </span>
            ))}
        </div>
    );
}
