import { Play, RotateCcw, Square } from 'lucide-react';
import { useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { useGifPlayback } from '@/hooks/use-gif-playback';
import { canLoadGifPoster } from '@/lib/gifs';
import { hostname } from '@/lib/urls';
import { cn } from '@/lib/utils';
import type { Embed, LinkPreview } from '@/types';

const GIF_LABEL = 'GIF';

type GifEmbedProps = {
    preview: LinkPreview;
    embed?: Embed | null;
    isStatic?: boolean;
};

type GifPlaybackState = ReturnType<typeof useGifPlayback>;

type GifMediaProps = {
    preview: LinkPreview;
    embed: Embed | null;
    playback: GifPlaybackState;
};

export function GifEmbed({
    preview,
    embed = null,
    isStatic = false,
}: GifEmbedProps) {
    const container = useRef<HTMLDivElement>(null);
    const playback = useGifPlayback({ embed, isStatic, container });

    return (
        <div
            ref={container}
            onClick={isStatic ? undefined : (event) => event.stopPropagation()}
            className="mt-3 overflow-hidden rounded-lg border"
            data-test="gif-embed"
            data-provider={embed?.provider ?? GIF_LABEL}
        >
            <div className="group/gif relative mx-auto aspect-square w-full max-w-80 overflow-hidden bg-muted">
                <GifMedia preview={preview} embed={embed} playback={playback} />
                <GifPlaybackButton
                    embed={embed}
                    playback={playback}
                    isStatic={isStatic}
                />
            </div>
            <GifSource preview={preview} embed={embed} isStatic={isStatic} />
        </div>
    );
}

function GifMedia({ preview, embed, playback }: GifMediaProps) {
    return (
        <>
            {/* Retry posters only for new media or a manual retry. */}
            <GifFallback
                key={`${embed?.src}:${embed?.poster}:${playback.retryCount}`}
                preview={preview}
                embed={embed}
                playback={playback}
            />
            {playback.isPlaying && embed && (
                <GifAnimation
                    key={embed.src}
                    preview={preview}
                    embed={embed}
                    playback={playback}
                />
            )}
        </>
    );
}

function GifAnimation({
    preview,
    embed,
    playback,
}: GifMediaProps & { embed: Embed }) {
    const [isLoaded, setIsLoaded] = useState(false);

    return (
        <img
            src={embed.src}
            alt={preview.title ?? 'Animated GIF'}
            referrerPolicy="no-referrer"
            onLoad={() => setIsLoaded(true)}
            onError={playback.onError}
            className={cn(
                'absolute inset-0 size-full bg-muted object-contain',
                !isLoaded && 'invisible',
            )}
            data-test="gif-animation"
        />
    );
}

function GifFallback({ preview, embed, playback }: GifMediaProps) {
    const [posterFailed, setPosterFailed] = useState(false);

    if (
        embed?.poster &&
        canLoadGifPoster(embed.poster) &&
        !posterFailed &&
        !playback.hasFailed
    ) {
        return (
            <img
                src={embed.poster}
                alt=""
                loading="lazy"
                referrerPolicy="no-referrer"
                onError={() => setPosterFailed(true)}
                className="absolute inset-0 size-full object-contain"
                data-test="gif-poster"
            />
        );
    }

    return <GifStatus preview={preview} embed={embed} playback={playback} />;
}

function GifStatus({ preview, embed, playback }: GifMediaProps) {
    return (
        <span
            className="absolute inset-0 flex items-center justify-center px-4 text-center text-sm text-muted-foreground"
            role={playback.hasFailed ? 'status' : undefined}
        >
            {gifStatusMessage(preview, embed, playback.hasFailed)}
        </span>
    );
}

function gifStatusMessage(
    preview: LinkPreview,
    embed: Embed | null,
    hasFailed: boolean,
) {
    if (hasFailed) {
        return 'GIF could not load';
    }

    if (embed) {
        return GIF_LABEL;
    }

    if (preview.is_pending) {
        return 'Loading GIF…';
    }

    return 'GIF preview unavailable';
}

function GifPlaybackButton({
    embed,
    playback,
    isStatic,
}: {
    embed: Embed | null;
    playback: GifPlaybackState;
    isStatic: boolean;
}) {
    if (isStatic || !embed || (playback.hasFailed && !playback.canRetry)) {
        return null;
    }

    const { Icon, label, onClick } = gifPlaybackControl(playback);

    return (
        <Button
            type="button"
            variant="secondary"
            size="icon"
            onClick={onClick}
            aria-label={`${label} GIF`}
            title={`${label} GIF`}
            className={cn(
                'absolute right-2 bottom-2 size-7 cursor-pointer bg-black/60 text-white transition-opacity hover:bg-black/80',
                playback.isPlaying &&
                    'opacity-0 group-hover/gif:opacity-100 focus-visible:opacity-100 [@media(hover:none)]:opacity-100',
            )}
            data-test="gif-playback"
        >
            <Icon className="size-3 fill-current" />
        </Button>
    );
}

function gifPlaybackControl(playback: GifPlaybackState) {
    if (playback.hasFailed) {
        return { Icon: RotateCcw, label: 'Retry', onClick: playback.onRetry };
    }

    return {
        Icon: playback.isPlaying ? Square : Play,
        label: playback.isPlaying ? 'Stop' : 'Play',
        onClick: playback.onToggle,
    };
}

function GifSource({ preview, embed, isStatic }: GifEmbedProps) {
    const details = <GifDetails preview={preview} embed={embed} />;

    if (isStatic) {
        return <div className="border-t px-3 py-2">{details}</div>;
    }

    return (
        <a
            href={preview.url}
            target="_blank"
            rel="noopener noreferrer nofollow ugc"
            className="block border-t px-3 py-2 transition-colors hover:bg-accent focus-visible:bg-accent"
        >
            {details}
        </a>
    );
}

function GifDetails({ preview, embed }: GifEmbedProps) {
    return (
        <>
            <span className="block truncate font-mono text-xs text-muted-foreground">
                {embed?.provider ?? hostname(preview.url)}
            </span>
            <span className="block truncate text-sm leading-snug font-medium">
                {preview.title ?? GIF_LABEL}
            </span>
        </>
    );
}
