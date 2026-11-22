import {
    Maximize,
    Pause,
    Play,
    RotateCcw,
    Volume2,
    VolumeX,
} from 'lucide-react';
import { useId, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { CommitVideo as VideoData } from '@/types';

export function CommitVideo({
    video,
    className,
}: {
    video: VideoData;
    className?: string;
}) {
    const player = useRef<HTMLVideoElement>(null);
    const frame = useRef<HTMLDivElement>(null);
    const [playing, setPlaying] = useState(false);
    const [ended, setEnded] = useState(false);
    const [started, setStarted] = useState(false);
    const [muted, setMuted] = useState(false);
    const [position, setPosition] = useState(0);
    const [duration, setDuration] = useState(video.duration ?? 0);
    const [error, setError] = useState<string | null>(null);
    const [showDescription, setShowDescription] = useState(false);
    const descriptionId = useId();

    const togglePlayback = async () => {
        const media = player.current;
        if (!media) return;
        if (!media.paused) {
            media.pause();
            return;
        }
        try {
            setError(null);
            await media.play();
        } catch {
            setError('Video could not play. Please try again.');
        }
    };
    const time = (seconds: number) =>
        `${Math.floor(seconds / 60)}:${String(Math.floor(seconds % 60)).padStart(2, '0')}`;
    const playLabel = playing
        ? 'Pause video'
        : ended
          ? 'Replay video'
          : 'Play video';

    if (video.status !== 'ready' || !video.url) return null;
    return (
        <div
            className={cn('mt-2 overflow-hidden rounded-lg border', className)}
            onClick={(event) => {
                event.stopPropagation();
            }}
            onKeyDown={(event) => event.stopPropagation()}
        >
            <div
                ref={frame}
                className="group/player fullscreen:flex fullscreen:flex-col fullscreen:justify-center relative bg-black"
            >
                <div className="relative min-h-0">
                    <video
                        ref={player}
                        src={video.url}
                        poster={video.poster_url ?? undefined}
                        playsInline
                        preload="none"
                        aria-label={video.description || 'Attached video'}
                        width={video.width ?? undefined}
                        height={video.height ?? undefined}
                        className="max-h-[70dvh] w-full object-contain"
                        style={{
                            aspectRatio: `${video.width || 16} / ${video.height || 9}`,
                        }}
                        onPlay={() => {
                            setPlaying(true);
                            setStarted(true);
                            setEnded(false);
                        }}
                        onPause={() => setPlaying(false)}
                        onEnded={() => {
                            setPlaying(false);
                            setEnded(true);
                        }}
                        onTimeUpdate={(event) =>
                            setPosition(event.currentTarget.currentTime)
                        }
                        onLoadedMetadata={(event) =>
                            setDuration(event.currentTarget.duration)
                        }
                        onVolumeChange={(event) =>
                            setMuted(event.currentTarget.muted)
                        }
                        onError={() =>
                            setError('Video could not load. Please try again.')
                        }
                    />
                    <button
                        type="button"
                        aria-label={playLabel}
                        className="peer absolute inset-0 cursor-pointer focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-ring"
                        onClick={() => void togglePlayback()}
                    />
                    {!playing && (
                        <span
                            aria-hidden
                            className="pointer-events-none absolute top-1/2 left-1/2 flex size-14 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full border-2 border-white/80 bg-black/60 text-white transition duration-150 peer-hover:scale-110 peer-hover:border-dashed peer-hover:border-white peer-hover:bg-black/80 peer-focus-visible:scale-110 peer-focus-visible:border-dashed peer-focus-visible:border-white motion-reduce:transition-none motion-reduce:peer-hover:scale-100 motion-reduce:peer-focus-visible:scale-100"
                        >
                            {ended ? (
                                <RotateCcw className="size-6" />
                            ) : (
                                <Play className="ml-0.5 size-6 fill-current" />
                            )}
                        </span>
                    )}
                    {!started && duration > 0 && (
                        <span className="pointer-events-none absolute bottom-2 left-2 rounded bg-black/75 px-1.5 py-0.5 text-xs font-medium text-white tabular-nums">
                            {time(duration)}
                        </span>
                    )}
                    {video.description && (
                        <button
                            type="button"
                            onClick={() => setShowDescription(!showDescription)}
                            aria-expanded={showDescription}
                            aria-controls={descriptionId}
                            aria-label={
                                showDescription
                                    ? 'Hide video description'
                                    : 'Show video description'
                            }
                            className="absolute top-2 left-2 rounded bg-black/75 px-1.5 py-0.5 text-xs font-bold tracking-wide text-white ring-1 ring-white/30 transition hover:bg-black"
                        >
                            ALT
                        </button>
                    )}
                </div>
                {started && (
                    <div
                        role="group"
                        aria-label="Video controls"
                        className="absolute inset-x-0 bottom-0 grid grid-cols-[auto_1fr_auto_auto_auto] items-center gap-x-1 bg-linear-to-t from-black/65 via-black/50 via-65% to-transparent px-3 pt-2 pb-2 text-white transition-opacity group-hover/player:pointer-events-auto group-hover/player:opacity-100 group-has-[:focus-visible]/player:pointer-events-auto group-has-[:focus-visible]/player:opacity-100 motion-reduce:transition-none [&_button:hover]:bg-white/15 [&_button:hover]:text-white [@media(hover:hover)]:pointer-events-none [@media(hover:hover)]:opacity-0"
                    >
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            aria-label={playLabel}
                            className="col-start-1 row-start-2"
                            onClick={() => void togglePlayback()}
                        >
                            {playing ? (
                                <Pause className="size-4" />
                            ) : ended ? (
                                <RotateCcw className="size-4" />
                            ) : (
                                <Play className="size-4" />
                            )}
                        </Button>
                        <input
                            type="range"
                            aria-label="Video position"
                            aria-valuetext={`${time(position)} of ${time(duration)}`}
                            min={0}
                            max={duration}
                            step={0.1}
                            value={position}
                            disabled={duration <= 0}
                            className="col-span-full row-start-1 h-4 w-full min-w-0 cursor-pointer appearance-none bg-transparent bg-center bg-no-repeat [&::-moz-range-thumb]:size-2 [&::-moz-range-thumb]:rounded-full [&::-moz-range-thumb]:border-0 [&::-moz-range-thumb]:bg-white [&::-moz-range-track]:bg-transparent [&::-webkit-slider-thumb]:size-2 [&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:rounded-full [&::-webkit-slider-thumb]:bg-white"
                            style={{
                                backgroundImage: `linear-gradient(to right, white ${(position / (duration || 1)) * 100}%, rgb(255 255 255 / 45%) ${(position / (duration || 1)) * 100}%)`,
                                backgroundSize: '100% 2px',
                            }}
                            onChange={(event) => {
                                if (player.current) {
                                    player.current.currentTime = Number(
                                        event.target.value,
                                    );
                                    setPosition(Number(event.target.value));
                                }
                            }}
                        />
                        <span className="col-start-3 row-start-2 text-xs whitespace-nowrap tabular-nums">
                            {time(position)} / {time(duration)}
                        </span>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            aria-label={muted ? 'Unmute video' : 'Mute video'}
                            className="col-start-4 row-start-2"
                            onClick={() => {
                                if (player.current)
                                    player.current.muted =
                                        !player.current.muted;
                            }}
                        >
                            {muted ? (
                                <VolumeX className="size-4" />
                            ) : (
                                <Volume2 className="size-4" />
                            )}
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            aria-label="Fullscreen video"
                            className="col-start-5 row-start-2"
                            onClick={() => {
                                // iPhone Safari only supports fullscreen on the video element itself.
                                const media = player.current as
                                    | (HTMLVideoElement & {
                                          webkitEnterFullscreen?: () => void;
                                      })
                                    | null;
                                if (
                                    !frame.current?.requestFullscreen &&
                                    media?.webkitEnterFullscreen
                                ) {
                                    try {
                                        media.webkitEnterFullscreen();
                                    } catch {
                                        // Throws until metadata loads with preload="none".
                                        setError(
                                            'Play the video first, then try fullscreen again.',
                                        );
                                    }
                                    return;
                                }
                                if (!frame.current?.requestFullscreen) {
                                    setError(
                                        'Fullscreen is unavailable in this browser.',
                                    );
                                    return;
                                }
                                if (document.fullscreenElement)
                                    void document.exitFullscreen();
                                else
                                    void frame.current
                                        ?.requestFullscreen()
                                        .catch(() =>
                                            setError(
                                                'Fullscreen is unavailable in this browser.',
                                            ),
                                        );
                            }}
                        >
                            <Maximize className="size-4" />
                        </Button>
                    </div>
                )}
            </div>
            {error && (
                <p role="alert" className="px-3 py-2 text-sm text-destructive">
                    {error}
                </p>
            )}
            {video.description && showDescription && (
                <p
                    id={descriptionId}
                    className="px-3 py-2 text-sm whitespace-pre-wrap text-muted-foreground"
                >
                    {video.description}
                </p>
            )}
        </div>
    );
}
