import type { CSSProperties } from 'react';
import { cn } from '@/lib/utils';
import './pastel-icon.css';

export type PastelVariant = 'original' | 'diamond' | 'frosted';

export function PastelIcon({
    variant = 'original',
    size = 20,
    glow = false,
    animated = false,
}: {
    variant?: PastelVariant;
    size?: number;
    glow?: boolean;
    animated?: boolean;
}) {
    return (
        <span
            aria-hidden="true"
            className={cn(
                'pastel-icon',
                (glow || variant === 'diamond') && 'pastel-icon-glow',
            )}
            data-variant={variant}
            data-animated={animated}
            style={{ '--pastel-size': `${size}px` } as CSSProperties}
        >
            <img
                src={
                    variant === 'original'
                        ? '/pastel-de-nata.svg'
                        : `/pastel-de-nata-${variant}.svg`
                }
                alt=""
                width={size}
                height={size}
            />
            {variant !== 'original' &&
                Array.from({ length: 12 }, (_, index) => (
                    <svg
                        key={index}
                        className="pastel-particle"
                        viewBox="0 0 24 24"
                        style={
                            {
                                '--particle-x': `${10 + ((index * 31) % 80)}%`,
                                '--particle-y': `${20 + ((index * 17) % 60)}%`,
                                '--particle-delay': `${-index * 0.217}s`,
                                '--particle-duration': `${1.8 + (index % 5) * 0.25}s`,
                                '--particle-tint': [
                                    '#c19aff',
                                    '#88c7ff',
                                    '#ffffff',
                                    '#fff1ce',
                                ][index % 4],
                            } as CSSProperties
                        }
                    >
                        {variant === 'diamond' ? (
                            <path
                                fill="currentColor"
                                d="M12 0 14 9 24 12 14 15 12 24 10 15 0 12 10 9Z"
                            />
                        ) : (
                            <path
                                fill="none"
                                stroke="currentColor"
                                strokeWidth="1.5"
                                strokeLinecap="round"
                                d="M12 2v20M3.34 7l17.32 10M3.34 17 20.66 7M9 5l3 3 3-3M9 19l3-3 3 3M4.5 10.5l4.1-1.1-1.1-4.1M16.5 18.7l-1.1-4.1 4.1-1.1M7.5 18.7l1.1-4.1-4.1-1.1M19.5 10.5l-4.1-1.1 1.1-4.1"
                            />
                        )}
                    </svg>
                ))}
        </span>
    );
}
