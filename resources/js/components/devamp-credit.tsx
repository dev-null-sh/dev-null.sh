import { useSyncExternalStore } from 'react';
import { devampTrack, subscribeToDevamp } from '@/lib/devamp';

const openMusicLicense =
    'https://openaudiofoundation.org/open-music-license.pdf';

export function DevampCredit() {
    const track = useSyncExternalStore(
        subscribeToDevamp,
        devampTrack,
        () => null,
    );

    if (track === null) {
        return null;
    }

    return (
        <p
            className="fixed bottom-3 left-1/2 z-40 flex max-w-[max(18rem,min(40rem,calc(100vw-50rem)))] -translate-x-1/2 gap-1 rounded-full border bg-background/95 px-3 py-1 text-xs whitespace-nowrap text-muted-foreground shadow-sm"
            data-test="devamp-credit"
        >
            <a
                href={track.link}
                target="_blank"
                rel="noopener noreferrer"
                className="min-w-0 truncate font-medium text-foreground hover:underline"
            >
                {track.title}
            </a>
            <span className="min-w-0 truncate">© {track.artist},</span>
            <span className="shrink-0">
                from Audius under the{' '}
                <a
                    href={openMusicLicense}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="hover:underline"
                >
                    Open Music License
                </a>
            </span>
        </p>
    );
}
