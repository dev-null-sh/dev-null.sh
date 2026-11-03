import { useEffect, useSyncExternalStore } from 'react';
import { useNow } from '@/hooks/use-now';
import {
    currentLiveStream,
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
    useEffect(() => (id === undefined ? undefined : watchLiveStream(id)), [id]);

    return id === undefined ? null : currentLiveStream(id, user?.live, now);
}
