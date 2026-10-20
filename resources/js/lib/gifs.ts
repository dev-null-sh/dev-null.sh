const GIF_POSTER_HOSTS = new Set(['media.giphy.com']);

function parseHttpsUrl(value: string): URL | null {
    try {
        const url = new URL(value);

        if (
            url.protocol !== 'https:' ||
            url.username !== '' ||
            url.password !== '' ||
            url.port !== ''
        ) {
            return null;
        }

        return url;
    } catch {
        return null;
    }
}

export function isGifUrl(value: string): boolean {
    return (
        parseHttpsUrl(value)?.pathname.toLowerCase().endsWith('.gif') ?? false
    );
}

export function canLoadGifPoster(value: string): boolean {
    const url = parseHttpsUrl(value);

    return url !== null && GIF_POSTER_HOSTS.has(url.hostname);
}

export const GIF_PLAYBACK_MODE = {
    AUTOMATIC: 'auto',
    PLAYING: 'play',
    STOPPED: 'stop',
} as const;

export type GifPlayback =
    (typeof GIF_PLAYBACK_MODE)[keyof typeof GIF_PLAYBACK_MODE];

export function isGifPlaying({
    source,
    playback,
    isVisible,
    reducedMotion,
    isStatic,
    hasFailed,
}: {
    source: string;
    playback: GifPlayback;
    isVisible: boolean;
    reducedMotion: boolean;
    isStatic: boolean;
    hasFailed: boolean;
}): boolean {
    if (
        isStatic ||
        hasFailed ||
        !isVisible ||
        playback === GIF_PLAYBACK_MODE.STOPPED
    ) {
        return false;
    }

    if (playback === GIF_PLAYBACK_MODE.PLAYING) {
        return true;
    }

    return !reducedMotion && isGifUrl(source);
}
