import { Volume1, Volume2, VolumeX } from 'lucide-react';
import type { KeyboardEvent, PointerEvent, SyntheticEvent } from 'react';
import { useEffect, useId, useRef } from 'react';
import { Waveform } from '@/components/chat/waveform';
import { setVoiceVolume, useVoiceVolume } from '@/hooks/use-voice-volume';
import { voiceBars } from '@/lib/voice-notes';

const keepToItself = (event: SyntheticEvent) => event.stopPropagation();

const ramp = Array.from(
    { length: voiceBars },
    (_, bar) => 15 + (85 * bar) / (voiceBars - 1),
);

/**
 * One volume for every voice note on this device. Hovering the speaker, or pressing it from the keyboard, turns the
 * waveform into a ramp of the same bars, filled up to the volume, with the volume in the clock's place. Phones and
 * tablets have volume buttons, so touch screens don't show it.
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
    const track = useRef<HTMLDivElement>(null);
    const closing = useRef<number | undefined>(undefined);
    const shouldFocus = useRef(false);

    useEffect(() => {
        if (isAdjusting && shouldFocus.current) {
            shouldFocus.current = false;
            fader.current?.focus();
        }
    }, [isAdjusting]);

    // Leaving puts the waveform back, unless the keyboard is still on the fader.
    const hover = (isEntering: boolean) => (event: PointerEvent) => {
        if (event.pointerType !== 'mouse') {
            return;
        }

        window.clearTimeout(closing.current);

        if (isEntering) {
            onAdjustingChange(true);
        } else {
            closing.current = window.setTimeout(() => {
                if (document.activeElement !== fader.current) {
                    onAdjustingChange(false);
                }
            }, 200);
        }
    };

    const seekTo = (event: PointerEvent<HTMLDivElement>) => {
        const box = track.current?.getBoundingClientRect();

        if (box !== undefined) {
            setVoiceVolume((event.clientX - box.left) / box.width);
        }
    };

    const onKeyDown = (event: KeyboardEvent<HTMLDivElement>) => {
        if (event.key === 'Escape') {
            onAdjustingChange(false);
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
            {isAdjusting && (
                <div
                    className="absolute inset-y-0 right-9.5 left-10.5 flex animate-in items-center gap-2.5 duration-150 fade-in-0"
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
                            event.currentTarget.setPointerCapture(
                                event.pointerId,
                            );
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
                        onBlur={() => onAdjustingChange(false)}
                        className="h-7 min-w-0 flex-1 cursor-pointer touch-none rounded-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        data-test="voice-volume-fader"
                    >
                        <div ref={track} className="relative h-full">
                            <Waveform levels={ramp} className="opacity-35" />
                            <div
                                className="absolute inset-0"
                                style={{
                                    clipPath: `inset(0 ${100 - percent}% 0 0)`,
                                }}
                            >
                                <Waveform levels={ramp} />
                            </div>
                        </div>
                    </div>
                    <span className="w-[4ch] shrink-0 text-right font-mono text-[11px] tabular-nums">
                        {percent}%
                    </span>
                </div>
            )}
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
                        onAdjustingChange(!isAdjusting);
                    }
                }}
                className="grid size-7 shrink-0 place-items-center rounded-md opacity-60 transition-opacity hover:bg-current/10 hover:opacity-100 focus-visible:opacity-100 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none aria-expanded:bg-current/10 aria-expanded:opacity-100 pointer-coarse:hidden"
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
