import { Pause, Play, RotateCw, Square, Trash2 } from 'lucide-react';
import type { KeyboardEvent, PointerEvent, SyntheticEvent } from 'react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import type { UseVoiceNoteReturn } from '@/hooks/use-voice-note';
import { cn } from '@/lib/utils';
import { formatVoiceTime, maxVoiceMs, voiceBars } from '@/lib/voice-notes';
import type { VoiceDraft } from '@/lib/voice-notes';
import type { MessageVoice } from '@/types';

/**
 * Only one voice note plays at a time, wherever it is on the page.
 */
let playing: HTMLAudioElement | null = null;

const keepToItself = (event: SyntheticEvent) => event.stopPropagation();

/**
 * Nothing is downloaded until play is pressed. The played part of the waveform and the clock are painted straight onto
 * the elements on every frame, so playing never re-renders the thread.
 */
export function VoiceNote({ voice }: { voice: MessageVoice | VoiceDraft }) {
    const duration = voice.duration_ms;
    const [state, setState] = useState<
        'idle' | 'loading' | 'playing' | 'paused' | 'failed'
    >('idle');
    const root = useRef<HTMLDivElement>(null);
    const slider = useRef<HTMLDivElement>(null);
    const clock = useRef<HTMLSpanElement>(null);
    const audio = useRef<HTMLAudioElement | null>(null);
    const objectUrl = useRef<string | null>(null);
    const loading = useRef<Promise<HTMLAudioElement> | null>(null);

    const paint = useCallback(() => {
        const at = (audio.current?.currentTime ?? 0) * 1000;

        root.current?.style.setProperty(
            '--played',
            String(Math.min(1, at / duration)),
        );
        slider.current?.setAttribute(
            'aria-valuenow',
            String(Math.floor(at / 1000)),
        );
        slider.current?.setAttribute(
            'aria-valuetext',
            `${formatVoiceTime(at)} of ${formatVoiceTime(duration)}`,
        );

        if (clock.current !== null) {
            clock.current.textContent = formatVoiceTime(at > 0 ? at : duration);
        }
    }, [duration]);

    useEffect(
        () => () => {
            audio.current?.pause();

            if (objectUrl.current !== null) {
                URL.revokeObjectURL(objectUrl.current);
            }
        },
        [],
    );

    useEffect(() => {
        if (state !== 'playing') {
            return;
        }

        let frame = requestAnimationFrame(function tick() {
            paint();
            frame = requestAnimationFrame(tick);
        });

        return () => cancelAnimationFrame(frame);
    }, [state, paint]);

    // The thread lets touch users pull a message to reply; scrubbing the waveform must not do that.
    useEffect(() => {
        const element = slider.current;
        const stop = (event: TouchEvent) => event.stopPropagation();

        element?.addEventListener('touchstart', stop, { passive: true });
        element?.addEventListener('touchmove', stop, { passive: true });

        return () => {
            element?.removeEventListener('touchstart', stop);
            element?.removeEventListener('touchmove', stop);
        };
    }, []);

    // Pressing play again or scrubbing while the note downloads waits for the same download.
    const load = (): Promise<HTMLAudioElement> => {
        loading.current ??= (async () => {
            const blob =
                'file' in voice
                    ? voice.file
                    : await fetch(voice.url, {
                          credentials: 'same-origin',
                          headers: { Accept: 'audio/*' },
                      }).then((response) => {
                          if (!response.ok) {
                              throw new Error(String(response.status));
                          }

                          return response.blob();
                      });

            objectUrl.current = URL.createObjectURL(blob);

            const element = new Audio(objectUrl.current);

            element.onpause = () =>
                setState((state) => (state === 'playing' ? 'paused' : state));
            element.onended = () => {
                element.currentTime = 0;
                paint();
            };
            audio.current = element;

            return element;
        })().catch((error: unknown) => {
            loading.current = null;

            throw error;
        });

        return loading.current;
    };

    const play = async (from?: number) => {
        if (state !== 'playing') {
            setState('loading');
        }

        try {
            const element = await load();

            if (from !== undefined) {
                element.currentTime = from / 1000;
                paint();
            }

            if (playing !== element) {
                playing?.pause();
            }

            playing = element;
            await element.play();
            setState('playing');
        } catch {
            setState('failed');
        }
    };

    const seekTo = (event: PointerEvent<HTMLDivElement>) => {
        const box = event.currentTarget.getBoundingClientRect();

        void play(
            Math.max(0, Math.min(1, (event.clientX - box.left) / box.width)) *
                duration,
        );
    };

    const onKeyDown = (event: KeyboardEvent<HTMLDivElement>) => {
        const at = (audio.current?.currentTime ?? 0) * 1000;
        const to = {
            ArrowLeft: at - 2000,
            ArrowRight: at + 2000,
            Home: 0,
            End: duration,
        }[event.key];

        if (to !== undefined) {
            event.preventDefault();
            void play(Math.max(0, Math.min(duration, to)));
        }
    };

    return (
        <div
            ref={root}
            className="flex w-60 max-w-full items-center gap-2.5 [--played:0]"
            data-test="voice-note"
        >
            <button
                type="button"
                onClick={() =>
                    state === 'playing' ? audio.current?.pause() : void play()
                }
                onPointerDown={keepToItself}
                onPointerUp={keepToItself}
                aria-label={
                    state === 'playing'
                        ? 'Pause voice note'
                        : state === 'failed'
                          ? 'Retry voice note'
                          : 'Play voice note'
                }
                className="grid size-8 shrink-0 place-items-center rounded-md bg-current/10 transition-colors hover:bg-current/20 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                data-test="voice-note-toggle"
            >
                {state === 'loading' ? (
                    <Spinner aria-hidden role={undefined} />
                ) : state === 'playing' ? (
                    <Pause aria-hidden className="size-4 fill-current" />
                ) : state === 'failed' ? (
                    <RotateCw aria-hidden className="size-4" />
                ) : (
                    <Play aria-hidden className="size-4 fill-current" />
                )}
            </button>
            <div
                ref={slider}
                role="slider"
                tabIndex={0}
                aria-label="Voice note position"
                aria-valuemin={0}
                aria-valuemax={Math.floor(duration / 1000)}
                aria-valuenow={0}
                aria-valuetext={`0:00 of ${formatVoiceTime(duration)}`}
                onPointerDown={(event) => {
                    keepToItself(event);
                    event.currentTarget.setPointerCapture(event.pointerId);
                    seekTo(event);
                }}
                onPointerMove={(event) => {
                    keepToItself(event);

                    if (
                        event.currentTarget.hasPointerCapture(event.pointerId)
                    ) {
                        seekTo(event);
                    }
                }}
                onPointerUp={keepToItself}
                onKeyDown={onKeyDown}
                className="relative h-7 min-w-0 flex-1 cursor-pointer touch-none rounded-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
            >
                <Waveform levels={voice.waveform} className="opacity-35" />
                <Waveform
                    levels={voice.waveform}
                    className="absolute inset-0 [clip-path:inset(0_calc(100%_-_var(--played)*100%)_0_0)]"
                />
            </div>
            <span
                ref={clock}
                className="w-[4ch] shrink-0 text-right font-mono text-[11px] tabular-nums"
                data-test="voice-note-clock"
            >
                {formatVoiceTime(duration)}
            </span>
        </div>
    );
}

function Waveform({
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

/**
 * Takes the message field's place while a voice note is being recorded or waits to be pushed: the microphone's level
 * drawn as it comes in with a clock counting to the one-minute limit, then the recording itself, to listen to first.
 */
export function VoiceNoteComposer({
    voiceNote,
    isCompact,
}: {
    voiceNote: UseVoiceNoteReturn;
    isCompact: boolean;
}) {
    const { state } = voiceNote;

    return (
        <>
            <Button
                type="button"
                variant="ghost"
                size="icon"
                onClick={voiceNote.discard}
                aria-label="Discard voice note"
                title="Discard voice note (Esc)"
                className={cn(
                    '-mx-1 shrink-0 text-muted-foreground hover:text-destructive',
                    isCompact ? 'size-9' : 'size-10',
                )}
                data-test="discard-voice-note"
            >
                <Trash2 className="size-5" />
            </Button>
            <div
                className={cn(
                    'flex min-h-10 min-w-0 flex-1 items-center gap-2 rounded-lg border border-input bg-card px-2',
                    { 'min-h-9': isCompact },
                )}
                data-test="voice-note-composer"
            >
                {state.status === 'recorded' ? (
                    <VoiceNote voice={state.draft} />
                ) : state.status === 'recording' ? (
                    <RecordingMeter
                        voiceNote={voiceNote}
                        startedAt={state.startedAt}
                    />
                ) : (
                    <span className="flex items-center gap-2 px-1 font-mono text-xs text-muted-foreground">
                        <Spinner
                            aria-hidden
                            role={undefined}
                            className="size-3.5"
                        />
                        {state.status === 'encoding'
                            ? 'encoding…'
                            : 'opening microphone…'}
                    </span>
                )}
            </div>
        </>
    );
}

function RecordingMeter({
    voiceNote,
    startedAt,
}: {
    voiceNote: UseVoiceNoteReturn;
    startedAt: number;
}) {
    const [levels, setLevels] = useState<number[]>(() =>
        Array.from({ length: voiceBars }, () => 0),
    );
    const [elapsed, setElapsed] = useState(0);
    const { level, stop } = voiceNote;

    useEffect(() => {
        let last = 0;
        let frame = requestAnimationFrame(function tick(now) {
            const at = now - startedAt;

            if (at >= maxVoiceMs) {
                void stop();

                return;
            }

            if (now - last >= 80) {
                last = now;
                setElapsed(at);
                setLevels((levels) => [
                    ...levels.slice(1),
                    Math.round(level() * 100),
                ]);
            }

            frame = requestAnimationFrame(tick);
        });

        return () => cancelAnimationFrame(frame);
    }, [startedAt, level, stop]);

    return (
        <>
            <span
                aria-hidden
                className="ml-1 size-2 shrink-0 rounded-full bg-foreground motion-safe:animate-pulse"
            />
            <span className="sr-only" role="status">
                Recording a voice note
            </span>
            <Waveform
                levels={levels}
                className="h-6 min-w-0 flex-1 text-foreground"
            />
            <span
                className={cn(
                    'shrink-0 font-mono text-xs text-muted-foreground tabular-nums',
                    {
                        'text-amber-700 dark:text-amber-400':
                            elapsed >= maxVoiceMs - 10_000,
                    },
                )}
                data-test="voice-note-elapsed"
            >
                {formatVoiceTime(elapsed)}/{formatVoiceTime(maxVoiceMs)}
            </span>
            <button
                type="button"
                onClick={() => void stop()}
                aria-label="Stop recording"
                title="Stop recording"
                autoFocus
                className="grid size-7 shrink-0 place-items-center rounded-md text-foreground hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                data-test="stop-voice-note"
            >
                <Square aria-hidden className="size-3.5 fill-current" />
            </button>
        </>
    );
}
