import { toast } from 'sonner';
import type Webamp from 'webamp';

export type PlaylistTrack = {
    artist: string;
    title: string;
    duration: number;
    url: string;
    link: string;
};

export const devampMedia = '(hover: hover) and (pointer: fine)';

const audius = (id: string) =>
    `https://api.audius.co/v1/tracks/${id}/stream?app_name=devnull`;

const chillingAtWork: PlaylistTrack[] = [
    {
        artist: 'kasp (fka stripess)',
        title: 'Austin Millz - Ride (stripess flip)',
        duration: 271,
        url: audius('P95dJ'),
        link: 'https://audius.co/stripess/austin-millz-ride-stripess-flip',
    },
    {
        artist: 'Emmit Fenn',
        title: 'Into The Deep',
        duration: 159,
        url: audius('KVogl'),
        link: 'https://audius.co/EmmitFenn/into-the-deep',
    },
    {
        artist: 'Feldt Fox',
        title: '7K in the sub type beat - Threats',
        duration: 206,
        url: audius('qG51E'),
        link: 'https://audius.co/FELDT/7k-in-the-sub-type-beat-threats',
    },
    {
        artist: 'Tom Woods',
        title: 'Lesson Learned',
        duration: 156,
        url: audius('0A02k'),
        link: 'https://audius.co/tomwoods/lesson-learned',
    },
    {
        artist: 'AZURA',
        title: "Lion in the Clouds (AZURA's Electro Remix)",
        duration: 269,
        url: audius('rz7wQ'),
        link: 'https://audius.co/musicazura/lion-in-the-clouds-azuras-electro-remix',
    },
    {
        artist: 'ruku',
        title: 'for you',
        duration: 196,
        url: audius('E7wE7'),
        link: 'https://audius.co/ruku/for-you-285827',
    },
    {
        artist: 'Kevin Paris',
        title: 'Your Love Is Home (Acoustic)',
        duration: 273,
        url: audius('k2WNB'),
        link: 'https://audius.co/kevinparismusic/your-love-is-home-acoustic-253087',
    },
    {
        artist: 'harris cole',
        title: 'hey, september',
        duration: 220,
        url: audius('4j2VZ'),
        link: 'https://audius.co/harriscole/hey-september-106321',
    },
    {
        artist: 'Mike Shinoda',
        title: 'Skrrt Skrrt',
        duration: 137,
        url: audius('A9lNq'),
        link: 'https://audius.co/mikeshinoda/skrrt-skrrt',
    },
    {
        artist: 'Bryann',
        title: 'Longe',
        duration: 187,
        url: audius('VQjdb'),
        link: 'https://audius.co/Bryannonly/longe-393860',
    },
    {
        artist: 'RAfeeki',
        title: 'RAfeeki x Parrotice Feat. Jenny Thompson - Magic In The Air',
        duration: 211,
        url: audius('6aKp9'),
        link: 'https://audius.co/RAfeekiOfficial/rafeeki-x-parrotice-feat.-jenny-thompson-magic-in-the-air-218140',
    },
    {
        artist: 'josh pan',
        title: "I CAN'T FEEL MY FACE by josh pan & X&G",
        duration: 237,
        url: audius('g62gx'),
        link: 'https://audius.co/joshpan/i-cant-feel-my-face-by-josh-pan-xg-169559',
    },
    {
        artist: 'KUMO Collective',
        title: 'midnight alpha, madj - 2082',
        duration: 218,
        url: audius('JbXXp'),
        link: 'https://audius.co/kumocollective/midnight-alpha-madj-2082-374407',
    },
    {
        artist: 'Invsynths',
        title: 'inVsynths - Deep Night',
        duration: 319,
        url: audius('MlO2x'),
        link: 'https://audius.co/Invbeats/invsynths-deep-night-457238',
    },
    {
        artist: 'WATA',
        title: 'Lesson 52',
        duration: 317,
        url: audius('WYw30'),
        link: 'https://audius.co/w4t4/lesson-52',
    },
    {
        artist: 'Bamf',
        title: 'Dawn',
        duration: 156,
        url: audius('nVJaN'),
        link: 'https://audius.co/Bamf/dawn-51960',
    },
    {
        artist: 'WATA',
        title: 'Pendulum',
        duration: 154,
        url: audius('7l1oP'),
        link: 'https://audius.co/w4t4/pendulum-208169',
    },
    {
        artist: 'takeo',
        title: 'missing her',
        duration: 126,
        url: audius('n3AvJ'),
        link: 'https://audius.co/tysu/missing-her-2423',
    },
    {
        artist: 'midnight alpha.',
        title: 'In the Woods.',
        duration: 130,
        url: audius('YoVAJ'),
        link: 'https://audius.co/midnightalpha/in-the-woods.-103800',
    },
    {
        artist: 'harris cole',
        title: 'fsh (AUDIUS EXCLUSIVE)',
        duration: 154,
        url: audius('KK2rO'),
        link: 'https://audius.co/harriscole/fsh-audius-exclusive-140992',
    },
    {
        artist: 'Adore',
        title: 'Memories',
        duration: 185,
        url: audius('GEmdm'),
        link: 'https://audius.co/adore/memories-206818',
    },
    {
        artist: 'SUPPER TRANKKI',
        title: 'Tarab',
        duration: 211,
        url: audius('gzalW'),
        link: 'https://audius.co/supertrankki/tarab',
    },
    {
        artist: 'Good Society',
        title: 'GEOTHEORY - INTRODUCTION TO YOUR SOUL',
        duration: 60,
        url: audius('642z9'),
        link: 'https://audius.co/goodsociety/geotheory-introduction-to-your-soul-105940',
    },
];

let Imported: typeof Webamp | null = null;
let importing: Promise<typeof Webamp> | null = null;
let player: Webamp | null = null;
let opening: Promise<Webamp | null> | null = null;
let loaded: PlaylistTrack | null = null;
let isOpen = false;
const known = new Map<string, PlaylistTrack>();
const listeners = new Set<() => void>();

function show(open: boolean, track = loaded) {
    isOpen = open;
    loaded = track;
    listeners.forEach((listener) => listener());
}

function toWebamp(tracks: PlaylistTrack[]) {
    for (const track of tracks) {
        known.set(track.url, track);
    }

    return tracks.map(({ artist, title, duration, url }) => ({
        metaData: { artist, title },
        duration,
        url,
    }));
}

function play(webamp: Webamp | null, tracks?: PlaylistTrack[]) {
    if (webamp !== null && tracks !== undefined) {
        webamp.setTracksToPlay(toWebamp(tracks));
        show(isOpen, tracks.at(0) ?? null);
    }

    return webamp;
}

function importWebamp(): Promise<typeof Webamp> {
    importing ??= import('webamp')
        .then(({ default: loaded }) => {
            Imported = loaded;

            return loaded;
        })
        .finally(() => {
            importing = null;
        });

    return importing;
}

function start(
    Loaded: typeof Webamp,
    tracks?: PlaylistTrack[],
): Promise<Webamp | null> {
    if (!Loaded.browserIsSupported()) {
        return Promise.resolve(null);
    }

    const desktop = document.createElement('div');
    desktop.dataset.devamp = '';
    document.body.append(desktop);

    const webamp = new Loaded({
        initialTracks:
            tracks === undefined ? toWebamp(chillingAtWork) : undefined,
        enableMediaSession: true,
        zIndex: 40,
    });

    loaded = tracks === undefined ? chillingAtWork[0] : null;
    webamp.onTrackDidChange((track) => {
        if (track !== null) {
            show(isOpen, known.get(track.url) ?? null);
        }
    });
    webamp.onClose(() => {
        show(false);
    });
    play(webamp, tracks);

    return webamp.renderInto(desktop).then(
        () => {
            player = webamp;
            show(true);

            return webamp;
        },
        (error: unknown) => {
            webamp.dispose();
            desktop.remove();

            throw error;
        },
    );
}

function load(tracks?: PlaylistTrack[]): Promise<Webamp | null> {
    if (player !== null) {
        player.reopen();
        show(true);

        return Promise.resolve(play(player, tracks));
    }

    if (opening !== null) {
        return opening.then((webamp) => play(webamp, tracks));
    }

    opening = (
        Imported === null
            ? importWebamp().then((loaded) => start(loaded, tracks))
            : start(Imported, tracks)
    ).finally(() => {
        opening = null;
    });

    return opening;
}

export function subscribeToDevamp(listener: () => void): () => void {
    listeners.add(listener);

    return () => {
        listeners.delete(listener);
    };
}

export function devampTrack(): PlaylistTrack | null {
    return isOpen ? loaded : null;
}

export function preloadDevamp(): void {
    if (Imported === null) {
        void importWebamp().catch(() => undefined);
    }
}

export async function openDevamp(tracks?: PlaylistTrack[]): Promise<void> {
    try {
        if ((await load(tracks)) === null) {
            toast.error("Devamp doesn't play in this browser.");
        }
    } catch {
        toast.error('Could not open Devamp. Please try again.');
    }
}
