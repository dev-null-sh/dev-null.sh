import type { SyntheticEvent } from 'react';
import { useRef } from 'react';
import { cn } from '@/lib/utils';
import { voiceSpeeds } from '@/lib/voice-notes';

const keepToItself = (event: SyntheticEvent) => event.stopPropagation();

/**
 * How fast one voice note plays, until the page reloads. Each press pops in the next speed with a spring, back to 1×
 * after 2×.
 */
export function VoiceSpeed({
    speed,
    onSpeedChange,
}: {
    speed: number;
    onSpeedChange: (speed: number) => void;
}) {
    const label = useRef<HTMLSpanElement>(null);
    const leaving = useRef<HTMLSpanElement>(null);

    return (
        <button
            type="button"
            aria-label={`Playback speed, ${speed}×`}
            onPointerDown={keepToItself}
            onPointerUp={keepToItself}
            onClick={() => {
                if (
                    !window.matchMedia('(prefers-reduced-motion: reduce)')
                        .matches &&
                    leaving.current !== null
                ) {
                    leaving.current.textContent = `${speed}×`;
                    leaving.current.animate(
                        {
                            opacity: [1, 0],
                            transform: ['scale(1)', 'scale(0.4)'],
                        },
                        { duration: 140, easing: 'ease-in' },
                    );
                    label.current?.animate(
                        {
                            opacity: [0, 1],
                            transform: ['scale(0.4)', 'scale(1)'],
                        },
                        {
                            duration: 340,
                            easing: 'cubic-bezier(0.34, 1.8, 0.64, 1)',
                        },
                    );
                }

                onSpeedChange(
                    voiceSpeeds[
                        (voiceSpeeds.indexOf(speed) + 1) % voiceSpeeds.length
                    ],
                );
            }}
            className={cn(
                'relative -ml-1.5 grid h-7 w-8.5 shrink-0 place-items-center rounded-md font-mono text-[11px] whitespace-nowrap tabular-nums transition-[opacity,background-color] hover:bg-current/10 hover:opacity-100 focus-visible:opacity-100 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                speed === 1 && 'opacity-60',
            )}
            data-test="voice-speed"
        >
            <span
                ref={leaving}
                aria-hidden
                className="absolute inset-0 grid place-items-center opacity-0"
            />
            <span ref={label}>{speed}×</span>
        </button>
    );
}
