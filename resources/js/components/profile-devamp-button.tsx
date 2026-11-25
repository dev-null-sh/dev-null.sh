import { router } from '@inertiajs/react';
import { Music } from 'lucide-react';
import { useRef, useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import type { PlaylistTrack } from '@/lib/devamp';
import { openDevamp, preloadDevamp } from '@/lib/devamp';

export function ProfileDevampButton({
    handle,
    playlist,
}: {
    handle: string;
    playlist: PlaylistTrack[] | undefined;
}) {
    const [isWaiting, setIsWaiting] = useState(false);
    const isFetching = useRef(false);
    const playWhenFetched = useRef(false);
    const isLoaded = playlist !== undefined && playlist.length > 0;

    const play = (tracks: PlaylistTrack[]) => {
        if (tracks.length === 0) {
            toast.error(`@${handle}'s playlist has nothing to play right now.`);

            return;
        }

        void openDevamp(tracks);
    };

    const prepare = () => {
        preloadDevamp();

        if (isFetching.current || isLoaded) {
            return;
        }

        isFetching.current = true;

        router.reload({
            only: ['playlist'],
            onSuccess: (page) => {
                if (playWhenFetched.current) {
                    play((page.props.playlist ?? []) as PlaylistTrack[]);
                }
            },
            onFinish: () => {
                isFetching.current = false;
                playWhenFetched.current = false;
                setIsWaiting(false);
            },
        });
    };

    const click = () => {
        if (isLoaded) {
            void openDevamp(playlist);

            return;
        }

        playWhenFetched.current = true;
        setIsWaiting(true);
        prepare();
    };

    return (
        <Button
            type="button"
            variant="outline"
            size="icon"
            className="hidden [@media(hover:hover)_and_(pointer:fine)]:inline-flex"
            aria-busy={isWaiting}
            onPointerEnter={prepare}
            onFocus={(event) => {
                if (event.currentTarget.matches(':focus-visible')) {
                    prepare();
                }
            }}
            onClick={click}
            aria-label={`Play @${handle}'s Audius playlist in Devamp`}
            title="Play in Devamp, from Audius"
            data-test="profile-devamp-button"
        >
            {isWaiting ? <Spinner /> : <Music />}
        </Button>
    );
}
