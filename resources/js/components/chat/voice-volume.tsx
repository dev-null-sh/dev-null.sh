import { Volume1, Volume2, VolumeX } from 'lucide-react';
import type { KeyboardEvent, PointerEvent, SyntheticEvent } from 'react';
import { useEffect, useRef, useState } from 'react';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { setVoiceVolume, useVoiceVolume } from '@/hooks/use-voice-volume';

const keepToItself = (event: SyntheticEvent) => event.stopPropagation();

/**
 * One volume for every voice note on this device. A mouse opens the fader by hovering the speaker, the keyboard by
 * pressing it. Phones and tablets have volume buttons, so touch screens don't show it.
 */
export function VoiceVolume() {
    const volume = useVoiceVolume();
    const [isOpen, setIsOpen] = useState(false);
    const isHovering = useRef(false);
    const isOpenedByHover = useRef(false);
    const closing = useRef<number | undefined>(undefined);
    const trigger = useRef<HTMLButtonElement>(null);
    const fader = useRef<HTMLDivElement>(null);

    // The voice note's own controls keep their presses to themselves, so only capturing sees them close the fader.
    useEffect(() => {
        if (!isOpen) {
            return;
        }

        const onPointerDown = (event: globalThis.PointerEvent) => {
            const target = event.target as Node;

            if (
                !trigger.current?.contains(target) &&
                !fader.current?.contains(target)
            ) {
                setIsOpen(false);
            }
        };

        document.addEventListener('pointerdown', onPointerDown, true);

        return () =>
            document.removeEventListener('pointerdown', onPointerDown, true);
    }, [isOpen]);

    const percent = Math.round(volume * 100);
    const Icon = volume === 0 ? VolumeX : volume < 0.5 ? Volume1 : Volume2;

    const hover = (isEntering: boolean) => (event: PointerEvent) => {
        if (event.pointerType !== 'mouse') {
            return;
        }

        isHovering.current = isEntering;
        window.clearTimeout(closing.current);

        if (isEntering) {
            isOpenedByHover.current ||= !isOpen;
            setIsOpen(true);
        } else {
            closing.current = window.setTimeout(() => setIsOpen(false), 150);
        }
    };

    // Hovering opens and closes the fader without taking focus from wherever the person is typing.
    const keepFocus = (event: Event) => {
        if (isOpenedByHover.current) {
            event.preventDefault();
        }
    };

    return (
        <Popover
            open={isOpen}
            onOpenChange={(isOpening) => {
                if (isOpening) {
                    isOpenedByHover.current = false;
                }

                setIsOpen(isOpening);
            }}
        >
            <PopoverTrigger
                ref={trigger}
                aria-label={`Voice volume, ${percent}%`}
                onPointerEnter={hover(true)}
                onPointerLeave={hover(false)}
                onPointerDown={keepToItself}
                onPointerUp={keepToItself}
                onClick={(event) => {
                    if (isHovering.current) {
                        event.preventDefault();
                    }
                }}
                className="grid size-7 shrink-0 place-items-center rounded-md opacity-60 transition-opacity hover:bg-current/10 hover:opacity-100 focus-visible:opacity-100 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none data-[state=open]:bg-current/10 data-[state=open]:opacity-100 pointer-coarse:hidden"
                data-test="voice-volume"
            >
                <Icon aria-hidden className="size-3.5" />
            </PopoverTrigger>
            <PopoverContent
                ref={fader}
                side="top"
                sideOffset={2}
                onOpenAutoFocus={keepFocus}
                onCloseAutoFocus={keepFocus}
                onPointerEnter={hover(true)}
                onPointerLeave={hover(false)}
                onPointerDown={keepToItself}
                onPointerUp={keepToItself}
                onClick={keepToItself}
                className="w-auto p-0.5"
            >
                <VolumeFader percent={percent} />
            </PopoverContent>
        </Popover>
    );
}

function VolumeFader({ percent }: { percent: number }) {
    const track = useRef<HTMLDivElement>(null);

    const seekTo = (event: PointerEvent<HTMLDivElement>) => {
        const box = track.current?.getBoundingClientRect();

        if (box !== undefined) {
            setVoiceVolume((box.bottom - event.clientY) / box.height);
        }
    };

    const onKeyDown = (event: KeyboardEvent<HTMLDivElement>) => {
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
        <div
            role="slider"
            tabIndex={0}
            aria-label="Voice volume"
            aria-orientation="vertical"
            aria-valuemin={0}
            aria-valuemax={100}
            aria-valuenow={percent}
            aria-valuetext={`${percent}%`}
            onPointerDown={(event) => {
                event.currentTarget.setPointerCapture(event.pointerId);
                seekTo(event);
            }}
            onPointerMove={(event) => {
                if (event.currentTarget.hasPointerCapture(event.pointerId)) {
                    seekTo(event);
                }
            }}
            onKeyDown={onKeyDown}
            className="group flex h-24 w-7 cursor-pointer touch-none justify-center rounded-md py-2 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
            data-test="voice-volume-fader"
        >
            <div
                ref={track}
                className="relative w-1 rounded-full bg-current/15"
            >
                <div
                    className="absolute inset-x-0 bottom-0 rounded-full bg-current"
                    style={{ height: `${percent}%` }}
                />
                <div
                    className="absolute left-1/2 size-3 -translate-x-1/2 translate-y-1/2 rounded-full bg-current shadow-sm transition-transform group-hover:scale-110 group-active:scale-125"
                    style={{ bottom: `${percent}%` }}
                />
            </div>
        </div>
    );
}
