import { useEffect, useState, useSyncExternalStore } from 'react';
import { useNow } from '@/hooks/use-now';
import {
    currentLiveStream,
    handedLiveStream,
    liveStreamsVersion,
    subscribeToLiveStreams,
    watchLiveStream,
} from '@/lib/live-streams';
import type { LiveStream } from '@/types';

/**
 * Re-renders when any shown person's stream is heard to start or end.
 */
export function useLiveStreamsVersion(): number {
    return useSyncExternalStore(
        subscribeToLiveStreams,
        liveStreamsVersion,
        () => 0,
    );
}

/**
 * The stream the person is on right now, wherever they show: live as soon as they go live, and no longer once the
 * stream ends or runs out. Organizations never stream.
 */
export function useLiveStream(
    user: { id?: string; live?: LiveStream | null } | null | undefined,
): LiveStream | null {
    const id = user?.id;
    const now = useNow();

    useLiveStreamsVersion();
    const handed =
        id === undefined ? user?.live : handedLiveStream(id, user?.live);
    const isUndated = handed != null && handed.ends_at === undefined;
    useEffect(
        () => (id === undefined ? undefined : watchLiveStream(id, isUndated)),
        [id, isUndated],
    );

    return id === undefined ? null : currentLiveStream(id, handed, now);
}

/**
 * The people a live list shows: those live when the page opened, for as long as it stays open, so nobody joining or
 * leaving moves what is around it while it is read. Each one's stream stays current, and the next page lists whoever
 * is live by then.
 */
export function useListedLiveStreams<
    T extends { id: string; live?: LiveStream | null },
>(users: T[], pageUrl: string): { user: T; live: LiveStream | null }[] {
    const now = useNow();
    const handedOf = (user: T) => handedLiveStream(user.id, user.live);
    const [listed, setListed] = useState<{ url: string; users: T[] } | null>(
        null,
    );
    const shown =
        listed?.url === pageUrl
            ? listed.users
            : users.filter(
                  (user) =>
                      currentLiveStream(user.id, handedOf(user), now) !== null,
              );

    if (listed?.url !== pageUrl) {
        setListed({ url: pageUrl, users: shown });
    }

    useLiveStreamsVersion();
    const ids = shown.map((user) => user.id).join(' ');
    const undated = shown
        .filter((user) => {
            const handed = handedOf(user);

            return handed != null && handed.ends_at === undefined;
        })
        .map((user) => user.id)
        .join(' ');
    useEffect(() => {
        const handed = new Set(undated.split(' '));
        const stops =
            ids === ''
                ? []
                : ids
                      .split(' ')
                      .map((id) => watchLiveStream(id, handed.has(id)));

        return () => stops.forEach((stop) => stop());
    }, [ids, undated]);

    return shown.map((listedUser) => {
        const user =
            users.find((candidate) => candidate.id === listedUser.id) ??
            listedUser;

        return {
            user,
            live: currentLiveStream(user.id, handedOf(user), now),
        };
    });
}
