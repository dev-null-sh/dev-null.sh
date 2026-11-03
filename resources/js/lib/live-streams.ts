import { index } from '@/actions/App/Http/Controllers/LiveStreamController';
import { listen, pageReadTime } from '@/lib/realtime';
import type { LiveStream } from '@/types';

/**
 * What was heard about each person's stream, and when: it replaces the stream each card, preview and avatar was
 * handed only when heard after the page read that, so a person going live or ending a stream shows as such everywhere
 * at once, while a page read since shows what it read.
 */
const heard = new Map<string, { stream: LiveStream | null; at: number }>();
const listeners = new Set<() => void>();
const watched = new Map<string, { count: number; stop: () => void }>();
let version = 0;

// When each stream on now runs out, as the last page said: pages show people's streams without their end.
let deadlines: Record<string, string> = {};

/**
 * Called with each page's word on when the streams on now run out. A page that didn't say keeps the last word.
 */
export function rememberLiveDeadlines(
    next: Record<string, string> | undefined,
): void {
    if (next === undefined || next === deadlines) {
        return;
    }

    deadlines = next;
    version++;
    listeners.forEach((listener) => listener());
}

/**
 * A stream as a page handed it, with when it runs out if the page said so.
 */
export function handedLiveStream(
    userId: string,
    handed: LiveStream | null | undefined,
): LiveStream | null | undefined {
    const endsAt = deadlines[userId];

    return handed != null &&
        handed.ends_at === undefined &&
        endsAt !== undefined
        ? { ...handed, ends_at: endsAt }
        : handed;
}

export function liveStreamsVersion(): number {
    return version;
}

export function subscribeToLiveStreams(listener: () => void): () => void {
    listeners.add(listener);

    return () => {
        listeners.delete(listener);
    };
}

function hear(userId: string, stream: LiveStream | null, at: number): void {
    // An answer read before something heard since is older than it.
    if ((heard.get(userId)?.at ?? -Infinity) > at) {
        return;
    }

    heard.set(userId, { stream, at });
    version++;
    listeners.forEach((listener) => listener());
}

let waiting = new Set<string>();
let timer: ReturnType<typeof setTimeout> | undefined;
// Streams handed without their end and not answered yet, with how many reads for them failed: each is read again,
// less and less often, until it is answered or nothing shows the person any more.
const undated = new Map<string, number>();
const retryDelay = (failures: number) =>
    Math.min(15_000 * 2 ** (failures - 1), 300_000);

/**
 * Asks whether the people whose profiles were joined, at first or again, are live right now, together with everyone
 * else joined at about the same time: a start or an end missed while not listening shows as it is.
 */
function readLater(userId: string): void {
    waiting.add(userId);
    timer ??= setTimeout(() => {
        timer = undefined;
        const ids = [...waiting];
        waiting = new Set();

        for (let offset = 0; offset < ids.length; offset += 100) {
            void read(ids.slice(offset, offset + 100));
        }
    }, 50);
}

async function read(ids: string[]): Promise<void> {
    const at = Date.now();

    try {
        const response = await fetch(index.url({ query: { ids } }), {
            headers: { Accept: 'application/json' },
            cache: 'no-store',
        });

        if (!response.ok) {
            throw new Error('Unable to read who is live');
        }

        const { streams } = (await response.json()) as {
            streams: Record<string, LiveStream | null>;
        };

        ids.forEach((id) => {
            undated.delete(id);
            hear(id, streams[id] ?? null, at);
        });
    } catch {
        // What the page read stands until the next join, except a stream handed without its end, read again later.
        ids.filter((id) => undated.has(id)).forEach(retryLater);
    }
}

function retryLater(userId: string): void {
    const failures = (undated.get(userId) ?? 0) + 1;
    undated.set(userId, failures);
    setTimeout(() => {
        if (undated.has(userId) && watched.has(userId)) {
            readLater(userId);
        }
    }, retryDelay(failures));
}

/**
 * Listens to the person's profile while something shows them. Returns the function that stops listening. A stream
 * the page was handed without when it runs out, as pages show people, is read once, with the others asked about
 * meanwhile, so it stops showing then even on a page left open without hearing anything.
 */
export function watchLiveStream(
    userId: string,
    isHandedUndated = false,
): () => void {
    if (isHandedUndated && !undated.has(userId)) {
        undated.set(userId, 0);
        readLater(userId);
    }

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
                            hear(
                                userId,
                                (payload.live as LiveStream | null) ?? null,
                                Date.now(),
                            );
                        }
                    },
                },
                () => readLater(userId),
            ),
        });
    }

    return () => {
        const entry = watched.get(userId);

        if (entry && --entry.count === 0) {
            entry.stop();
            watched.delete(userId);
            undated.delete(userId);
        }
    };
}

/**
 * The stream a person is on right now: the one last heard of if heard after the page was read, or the one the page
 * was handed, and only until it runs out, even on a page left open.
 */
export function currentLiveStream(
    userId: string,
    given: LiveStream | null | undefined,
    now: Date,
): LiveStream | null {
    const latest = heard.get(userId);
    const readAt = pageReadTime();
    const stream =
        latest !== undefined && (readAt === null || latest.at >= readAt)
            ? latest.stream
            : given;

    return stream != null &&
        (stream.ends_at == null || new Date(stream.ends_at) > now)
        ? stream
        : null;
}
