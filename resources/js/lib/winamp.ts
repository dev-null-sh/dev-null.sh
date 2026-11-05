import { toast } from 'sonner';
import type Webamp from 'webamp';

export type WinampTrack = {
    artist: string;
    title: string;
    duration: number;
    url: string;
};

export const winampMedia = '(hover: hover) and (pointer: fine)';

const audius = (id: string) =>
    `https://api.audius.co/v1/tracks/${id}/stream?app_name=devnull`;

const lofiBeats: WinampTrack[] = [
    {
        artist: 'Broey.',
        title: 'Paradise',
        duration: 143,
        url: audius('emKAp'),
    },
    {
        artist: 'harris cole',
        title: 'game over!',
        duration: 207,
        url: audius('w76Mk'),
    },
    { artist: 'Byte', title: 'snow', duration: 120, url: audius('k4mWX') },
    { artist: 'Milkz', title: 'astray', duration: 95, url: audius('D8a7l') },
    { artist: 'j3ra', title: 'Flourish', duration: 73, url: audius('nlzRQ') },
    {
        artist: 'harris cole',
        title: "louie's lullaby",
        duration: 172,
        url: audius('z863j'),
    },
];

let Winamp: typeof Webamp | null = null;
let importing: Promise<typeof Webamp> | null = null;
let player: Webamp | null = null;
let opening: Promise<Webamp | null> | null = null;

const toWebamp = (tracks: WinampTrack[]) =>
    tracks.map(({ artist, title, duration, url }) => ({
        metaData: { artist, title },
        duration,
        url,
    }));

function play(webamp: Webamp | null, tracks?: WinampTrack[]) {
    if (webamp !== null && tracks !== undefined) {
        webamp.setTracksToPlay(toWebamp(tracks));
    }

    return webamp;
}

function importWinamp(): Promise<typeof Webamp> {
    importing ??= import('webamp')
        .then(({ default: loaded }) => {
            Winamp = loaded;

            return loaded;
        })
        .finally(() => {
            importing = null;
        });

    return importing;
}

function start(
    Loaded: typeof Webamp,
    tracks?: WinampTrack[],
): Promise<Webamp | null> {
    if (!Loaded.browserIsSupported()) {
        return Promise.resolve(null);
    }

    const desktop = document.createElement('div');
    desktop.dataset.winamp = '';
    document.body.append(desktop);

    const webamp = new Loaded({
        initialTracks: tracks === undefined ? toWebamp(lofiBeats) : undefined,
        enableMediaSession: true,
        zIndex: 40,
    });

    play(webamp, tracks);

    return webamp.renderInto(desktop).then(
        () => {
            player = webamp;

            return webamp;
        },
        (error: unknown) => {
            webamp.dispose();
            desktop.remove();

            throw error;
        },
    );
}

function load(tracks?: WinampTrack[]): Promise<Webamp | null> {
    if (player !== null) {
        player.reopen();

        return Promise.resolve(play(player, tracks));
    }

    if (opening !== null) {
        return opening.then((webamp) => play(webamp, tracks));
    }

    opening = (
        Winamp === null
            ? importWinamp().then((loaded) => start(loaded, tracks))
            : start(Winamp, tracks)
    ).finally(() => {
        opening = null;
    });

    return opening;
}

export function preloadWinamp(): void {
    if (Winamp === null) {
        void importWinamp().catch(() => undefined);
    }
}

export async function openWinamp(tracks?: WinampTrack[]): Promise<void> {
    try {
        if ((await load(tracks)) === null) {
            toast.error("Winamp doesn't play in this browser.");
        }
    } catch {
        toast.error('Could not open Winamp. Please try again.');
    }
}
