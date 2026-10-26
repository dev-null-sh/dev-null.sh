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

export const MAX_GIF_RETRIES = 2;

export type GifLoadRetryState = {
    source: string | null;
    failedSource: string | null;
    retryCount: number;
    playback: GifPlayback;
};

export function syncGifLoadRetryState(
    state: GifLoadRetryState,
    source: string | null,
): GifLoadRetryState {
    if (state.source === source) {
        return state;
    }

    return {
        source,
        failedSource: null,
        retryCount: 0,
        playback: state.playback,
    };
}

export function hasGifLoadFailed(
    state: GifLoadRetryState,
    source: string | null,
): boolean {
    return source !== null && state.failedSource === source;
}

export function canRetryGifLoad(
    state: GifLoadRetryState,
    source: string | null,
): boolean {
    return (
        hasGifLoadFailed(state, source) && state.retryCount < MAX_GIF_RETRIES
    );
}

export function failGifLoad(
    state: GifLoadRetryState,
    source: string | null,
): GifLoadRetryState {
    if (state.source !== source || source === null) {
        return state;
    }

    return { ...state, failedSource: source };
}

export function retryGifLoad(
    state: GifLoadRetryState,
    source: string | null,
): GifLoadRetryState {
    if (!canRetryGifLoad(state, source)) {
        return state;
    }

    return {
        ...state,
        failedSource: null,
        retryCount: state.retryCount + 1,
        playback: GIF_PLAYBACK_MODE.PLAYING,
    };
}

export function toggleGifPlayback(
    state: GifLoadRetryState,
    source: string | null,
    isPlaying: boolean,
): GifLoadRetryState {
    if (state.source !== source) {
        return state;
    }

    return {
        ...state,
        playback: isPlaying
            ? GIF_PLAYBACK_MODE.STOPPED
            : GIF_PLAYBACK_MODE.PLAYING,
    };
}

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
        playback === GIF_PLAYBACK_MODE.STOPPED ||
        parseHttpsUrl(source) === null
    ) {
        return false;
    }

    if (playback === GIF_PLAYBACK_MODE.PLAYING) {
        return true;
    }

    return !reducedMotion;
}
