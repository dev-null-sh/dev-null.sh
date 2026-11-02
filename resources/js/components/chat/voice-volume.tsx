import { Volume1, Volume2, VolumeX } from 'lucide-react';
import type { KeyboardEvent, PointerEvent, SyntheticEvent } from 'react';
import { useEffect, useId, useRef } from 'react';
import { setVoiceVolume, useVoiceVolume } from '@/hooks/use-voice-volume';
import { cn } from '@/lib/utils';
import { voiceBars } from '@/lib/voice-notes';

const keepToItself = (event: SyntheticEvent) => event.stopPropagation();

export const voiceVolumeRamp = Array.from(
    { length: voiceBars },
    (_, bar) => 15 + (85 * bar) / (voiceBars - 1),
);

/**
 * One volume for every voice note on this device. Resting on the speaker, or pressing it from the keyboard, lays a fader
 * over the voice note while its waveform grows into a ramp filled up to the volume, with the volume in the clock's
 * place. Phones and tablets have volume buttons, so touch screens don't show it.
 */
export function VoiceVolume({
    isAdjusting,
    onAdjustingChange,
}: {
    isAdjusting: boolean;
    onAdjustingChange: (isAdjusting: boolean) => void;
}) {
    const volume = useVoiceVolume();
    const percent = Math.round(volume * 100);
    const id = useId();
    const speaker = useRef<HTMLButtonElement>(null);
    const fader = useRef<HTMLDivElement>(null);
    const hovering = useRef<number | undefined>(undefined);
    const shouldFocus = useRef(false);

    useEffect(() => {
        if (isAdjusting && shouldFocus.current) {
            shouldFocus.current = false;
            fader.current?.focus();
        }
    }, [isAdjusting]);

    useEffect(() => () => window.clearTimeout(hovering.current), []);

    // The keyboard and leaving the fader decide at once, overruling a hover still waiting.
    const setAdjusting = (to: boolean) => {
        window.clearTimeout(hovering.current);
        onAdjustingChange(to);
    };

    // Passing over the speaker on the way elsewhere changes nothing, and leaving puts the waveform back unless the
    // keyboard is still on the fader.
    const hover = (isEntering: boolean) => (event: PointerEvent) => {
        if (event.pointerType !== 'mouse') {
            return;
        }

        window.clearTimeout(hovering.current);
        hovering.current = window.setTimeout(
            () => {
                if (isEntering) {
                    onAdjustingChange(true);
                } else if (document.activeElement !== fader.current) {
                    onAdjustingChange(false);
                }
            },
            isEntering ? 120 : 200,
        );
    };

    const seekTo = (event: PointerEvent<HTMLDivElement>) => {
        const box = event.currentTarget.getBoundingClientRect();

        setVoiceVolume((event.clientX - box.left) / box.width);
    };

    const onKeyDown = (event: KeyboardEvent<HTMLDivElement>) => {
        if (event.key === 'Escape') {
            setAdjusting(false);
            speaker.current?.focus();

            return;
        }

        const to = {
            ArrowUp: percent + 5,
            ArrowRight: percent + 5,
            ArrowDown: percent - 5,
            ArrowLeft: percent - 5,
            PageUp: percent + 20,
            PageDown: percent - 20,
            Home: 0,
            End: 100,
        }[event.key];

        if (to !== undefined) {
            event.preventDefault();
            setVoiceVolume(to / 100);
        }
    };

    return (
        <>
            <div
                inert={!isAdjusting}
                className={cn(
                    'absolute inset-y-0 right-19 left-10.5 flex items-center gap-2.5 transition-opacity duration-240 ease-out motion-reduce:transition-none',
                    !isAdjusting && 'opacity-0',
                )}
                onPointerEnter={hover(true)}
                onPointerLeave={hover(false)}
            >
                <div
                    ref={fader}
                    id={id}
                    role="slider"
                    tabIndex={0}
                    aria-label="Voice volume"
                    aria-valuemin={0}
                    aria-valuemax={100}
                    aria-valuenow={percent}
                    aria-valuetext={`${percent}%`}
                    // Adjusting with the mouse leaves the focus where it was, such as in the message field.
                    onPointerDown={(event) => {
                        keepToItself(event);
                        event.preventDefault();
                        event.currentTarget.setPointerCapture(event.pointerId);
                        seekTo(event);
                    }}
                    onPointerMove={(event) => {
                        if (
                            event.currentTarget.hasPointerCapture(
                                event.pointerId,
                            )
                        ) {
                            seekTo(event);
                        }
                    }}
                    onPointerUp={keepToItself}
                    onClick={keepToItself}
                    onKeyDown={onKeyDown}
                    onBlur={() => setAdjusting(false)}
                    className="h-7 min-w-0 flex-1 cursor-pointer touch-none rounded-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    data-test="voice-volume-fader"
                />
                <span className="w-[4ch] shrink-0 text-right font-mono text-[11px] whitespace-nowrap tabular-nums">
                    {percent}%
                </span>
            </div>
            <button
                ref={speaker}
                type="button"
                aria-label={`Voice volume, ${percent}%`}
                aria-expanded={isAdjusting}
                aria-controls={isAdjusting ? id : undefined}
                onPointerEnter={hover(true)}
                onPointerLeave={hover(false)}
                onPointerDown={keepToItself}
                onPointerUp={keepToItself}
                // The mouse opens it by hovering; only the keyboard's press toggles it.
                onClick={(event) => {
                    if (event.detail === 0) {
                        shouldFocus.current = !isAdjusting;
                        setAdjusting(!isAdjusting);
                    }
                }}
                className="grid size-7 shrink-0 place-items-center rounded-md opacity-60 transition-[opacity,background-color] hover:bg-current/10 hover:opacity-100 focus-visible:opacity-100 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none aria-expanded:bg-current/10 aria-expanded:opacity-100 pointer-coarse:hidden"
                data-test="voice-volume"
            >
                {volume === 0 ? (
                    <VolumeX aria-hidden className="size-3.5" />
                ) : volume < 0.5 ? (
                    <Volume1 aria-hidden className="size-3.5" />
                ) : (
                    <Volume2 aria-hidden className="size-3.5" />
                )}
            </button>
        </>
    );
}
