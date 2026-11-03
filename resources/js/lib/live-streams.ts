import { listen } from '@/lib/realtime';
import type { LiveStream } from '@/types';

/**
 * What profiles said about their streams since the page read them, which replaces the stream each card, preview
 * and avatar was handed: a person going live or ending a stream shows as such everywhere at once.
 */
const heard = new Map<string, LiveStream | null>();
const listeners = new Set<() => void>();
const watched = new Map<string, { count: number; stop: () => void }>();
let version = 0;

export function liveStreamsVersion(): number {
    return version;
}

export function subscribeToLiveStreams(listener: () => void): () => void {
    listeners.add(listener);

    return () => {
        listeners.delete(listener);
    };
}

export function heardLiveStream(userId: string): LiveStream | null | undefined {
    return heard.get(userId);
}

/**
 * Listens to the person's profile while something shows them. Returns the function that stops listening.
 */
export function watchLiveStream(userId: string): () => void {
    const current = watched.get(userId);

    if (current) {
        current.count++;
    } else {
        watched.set(userId, {
            count: 1,
            stop: listen(
                { name: `profiles.${userId}`, type: 'public' },
                {
                    profile: (payload) => {
                        if (
                            typeof payload === 'object' &&
                            payload !== null &&
                            'live' in payload
                        ) {
                            heard.set(
                                userId,
                                (payload.live as LiveStream | null) ?? null,
                            );
                            version++;
                            listeners.forEach((listener) => listener());
                        }
                    },
                },
            ),
        });
    }

    return () => {
        const entry = watched.get(userId);

        if (entry && --entry.count === 0) {
            entry.stop();
            watched.delete(userId);
        }
    };
}

/**
 * The stream a person is on right now: the one last heard of, or the one the page was handed, and only until it
 * runs out, even on a page left open.
 */
export function currentLiveStream(
    userId: string,
    given: LiveStream | null | undefined,
    now: Date,
): LiveStream | null {
    const stream = heard.has(userId) ? heard.get(userId) : given;

    return stream != null &&
        (stream.ends_at == null || new Date(stream.ends_at) > now)
        ? stream
        : null;
}
