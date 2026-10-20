import { Play, Square } from 'lucide-react';
import { useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { useGifPlayback } from '@/hooks/use-gif-playback';
import { canAutoplayGif } from '@/lib/gifs';
import { hostname } from '@/lib/urls';
import { cn } from '@/lib/utils';
import type { Embed, LinkPreview } from '@/types';

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
            data-provider={embed?.provider ?? 'GIF'}
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
    const [posterFailed, setPosterFailed] = useState(false);

    if (playback.isPlaying && embed) {
        return (
            <img
                src={embed.src}
                alt={preview.title ?? 'Animated GIF'}
                referrerPolicy="no-referrer"
                onError={playback.onError}
                className="absolute inset-0 size-full object-contain"
                data-test="gif-animation"
            />
        );
    }

    if (
        embed?.poster &&
        canAutoplayGif(embed.poster) &&
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
            {playback.hasFailed && 'GIF could not load'}
            {!playback.hasFailed &&
                embed &&
                (canAutoplayGif(embed.src)
                    ? 'GIF'
                    : 'External GIF — press Play to load')}
            {!playback.hasFailed &&
                !embed &&
                preview.is_pending &&
                'Loading GIF…'}
            {!playback.hasFailed &&
                !embed &&
                !preview.is_pending &&
                'GIF preview unavailable'}
        </span>
    );
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
    if (isStatic || !embed || playback.hasFailed) {
        return null;
    }

    const Icon = playback.isPlaying ? Square : Play;
    const label = playback.isPlaying ? 'Stop' : 'Play';

    return (
        <Button
            type="button"
            variant="secondary"
            size="icon"
            onClick={playback.onToggle}
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
                {preview.title ?? 'GIF'}
            </span>
        </>
    );
}
