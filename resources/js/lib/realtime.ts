import type { LiveTransport } from '@inertiajs/core';

export type RealtimeChannel = {
    name: string;
    type: 'public' | 'private';
};

export type RealtimeHandler = (payload: unknown) => void;

/**
 * The part of Laravel Echo this needs, so anything speaking the Pusher protocol fits, and tests can stand in for it.
 */
export interface RealtimeChannelHandle {
    listen(event: string, callback: RealtimeHandler): unknown;
    stopListening(event: string, callback: RealtimeHandler): unknown;
    subscribed(callback: () => void): unknown;
    error(callback: (status: unknown) => void): unknown;
    listenToAll(callback: (event: string, payload: unknown) => void): unknown;
}

export interface RealtimeConnection {
    channel(name: string): RealtimeChannelHandle;
    private(name: string): RealtimeChannelHandle;
    leaveChannel(name: string): void;
    socketId(): string | undefined;
    connector: {
        onConnectionChange(callback: (status: string) => void): () => void;
    };
}

type ChannelEntry = {
    handle: RealtimeChannelHandle;
    events: Map<
        string,
        { dispatch: RealtimeHandler; handlers: Set<RealtimeHandler> }
    >;
    syncs: Set<() => void>;
    isSubscribed: boolean;
    subscribedAt: number | null;
    heardAt: Map<string, number>;
    leaveTimer: ReturnType<typeof setTimeout> | undefined;
};

/**
 * A channel nobody listens to any more is left a moment later, so a page swapping listeners does not rejoin it.
 */
const leaveDelay = 1_000;

/**
 * A listener joining a channel someone already listens on catches up when its data may be older than what the
 * channel heard: the channel was joined, or heard the listener's events, this recently. Data read longer ago that
 * nothing changed since is current.
 */
const lateWindow = 15_000;

/**
 * Pages brought back from history or a prefetch show what was read back then, so every listener joining meanwhile
 * catches up, and live props are read again.
 */
const restoreWindow = 3_000;
let restoredUntil = 0;
const liveRefreshers = new Set<() => void>();

let connection: RealtimeConnection | null = null;
let isConnected = false;
let stopWatchingConnection = () => {};
const channels = new Map<string, ChannelEntry>();
const changeListeners = new Set<() => void>();

const keyOf = (channel: RealtimeChannel): string =>
    channel.type === 'private' ? `private-${channel.name}` : channel.name;

const notifyChange = (): void =>
    changeListeners.forEach((listener) => listener());

/**
 * Starts listening through the given connection, or through none at all, in which case nothing is ever heard and
 * every page keeps polling as it did before live updates.
 */
export function connectRealtime(next: RealtimeConnection | null): void {
    stopWatchingConnection();
    channels.forEach((entry) => clearTimeout(entry.leaveTimer));
    channels.clear();
    connection = next;
    isConnected = false;
    stopWatchingConnection =
        next?.connector.onConnectionChange((status) => {
            const connected = status === 'connected';

            if (!connected) {
                channels.forEach((entry) => {
                    entry.isSubscribed = false;
                    entry.subscribedAt = null;
                });
            }

            if (connected !== isConnected) {
                isConnected = connected;
                notifyChange();
            }
        }) ?? (() => {});
    notifyChange();
}

function entryFor(channel: RealtimeChannel, socket: RealtimeConnection) {
    const key = keyOf(channel);
    const existing = channels.get(key);

    if (existing) {
        clearTimeout(existing.leaveTimer);
        existing.leaveTimer = undefined;

        return existing;
    }

    const handle =
        channel.type === 'private'
            ? socket.private(channel.name)
            : socket.channel(channel.name);
    const entry: ChannelEntry = {
        handle,
        events: new Map(),
        syncs: new Set(),
        isSubscribed: false,
        subscribedAt: null,
        heardAt: new Map(),
        leaveTimer: undefined,
    };

    handle.listenToAll((event) =>
        entry.heardAt.set(event.replace(/^\./, ''), Date.now()),
    );

    // Joining again after a dropped connection is when updates may have been missed, so each listener catches up.
    handle.subscribed(() => {
        entry.isSubscribed = true;
        entry.subscribedAt = Date.now();
        entry.syncs.forEach((sync) => sync());
        notifyChange();
    });
    handle.error(() => {
        entry.isSubscribed = false;
        notifyChange();
    });
    channels.set(key, entry);

    return entry;
}

/**
 * Listens for events on a channel, calling `onSync` whenever the channel is joined, at first and after every
 * reconnection, since updates sent in between were never heard. Returns the function that stops listening.
 */
export function listen(
    channel: RealtimeChannel,
    events: Record<string, RealtimeHandler>,
    onSync?: () => void,
): () => void {
    const socket = connection;

    if (socket === null) {
        return () => {};
    }

    const key = keyOf(channel);
    const entry = entryFor(channel, socket);

    Object.entries(events).forEach(([event, handler]) => {
        const listening = entry.events.get(event);

        if (listening) {
            listening.handlers.add(handler);

            return;
        }

        const handlers = new Set([handler]);
        const dispatch: RealtimeHandler = (payload) =>
            handlers.forEach((listener) => listener(payload));

        entry.events.set(event, { dispatch, handlers });
        entry.handle.listen(`.${event}`, dispatch);
    });

    if (onSync) {
        entry.syncs.add(onSync);

        if (entry.isSubscribed && mayHaveMissed(entry, Object.keys(events))) {
            queueMicrotask(onSync);
        }
    }

    let isListening = true;

    return () => {
        if (!isListening || channels.get(key) !== entry) {
            return;
        }

        isListening = false;

        Object.entries(events).forEach(([event, handler]) => {
            const listening = entry.events.get(event);

            listening?.handlers.delete(handler);

            if (listening?.handlers.size === 0) {
                entry.events.delete(event);
                entry.handle.stopListening(`.${event}`, listening.dispatch);
            }
        });

        if (onSync) {
            entry.syncs.delete(onSync);
        }

        if (entry.events.size === 0 && entry.syncs.size === 0) {
            entry.leaveTimer = setTimeout(() => {
                channels.delete(key);
                socket.leaveChannel(key);
                notifyChange();
            }, leaveDelay);
        }
    };
}

function mayHaveMissed(entry: ChannelEntry, events: string[]): boolean {
    const now = Date.now();

    return (
        now < restoredUntil ||
        entry.subscribedAt === null ||
        now - entry.subscribedAt < lateWindow ||
        (events.length === 0
            ? [...entry.heardAt.values()]
            : events.map((event) => entry.heardAt.get(event) ?? -Infinity)
        ).some((heardAt) => now - heardAt < lateWindow)
    );
}

/**
 * Called when a page comes back from history or a prefetch: what it shows was read back then.
 */
export function catchUpAfterRestore(): void {
    restoredUntil = Date.now() + restoreWindow;
    liveRefreshers.forEach((refresh) => refresh());
}

/**
 * Whether updates on the channel are being heard right now, so whoever relies on them can stop polling.
 */
export function isListening(channel: RealtimeChannel): boolean {
    return isConnected && channels.get(keyOf(channel))?.isSubscribed === true;
}

/**
 * Calls back whenever the connection or a channel's subscription changes, for pollers to pick their pace again.
 */
export function onRealtimeChange(listener: () => void): () => void {
    changeListeners.add(listener);

    return () => {
        changeListeners.delete(listener);
    };
}

/**
 * Inertia's live props listen through the same channels, so a page and the stores beside it share one
 * subscription. Joining a channel counts as an update, so props read before the page was listening catch up.
 */
export const liveTransport: LiveTransport = {
    subscribe: (channel, event, handler) => {
        const refresh = () => handler({});
        const stop = listen(
            {
                name: channel.name,
                type: channel.type === 'private' ? 'private' : 'public',
            },
            { [event]: handler },
            refresh,
        );

        liveRefreshers.add(refresh);

        return () => {
            liveRefreshers.delete(refresh);
            stop();
        };
    },
    socketId: () => connection?.socketId() ?? null,
};

export function userChannel(userId: string): RealtimeChannel {
    return { name: `users.${userId}`, type: 'private' };
}

export function commitChannel(commitId: string): RealtimeChannel {
    return { name: `commits.${commitId}`, type: 'public' };
}

export const logChannel: RealtimeChannel = { name: 'log', type: 'public' };

/**
 * Runs `refresh` once for a burst of requests, never twice at the same time, and once more afterwards when asked
 * again while it ran, so the last request is always followed by a refresh that started after it.
 */
export function coalesce(
    refresh: () => Promise<unknown>,
    delay = 200,
): { request: () => void; cancel: () => void } {
    let timer: ReturnType<typeof setTimeout> | undefined;
    let isRunning = false;
    let isRequestedAgain = false;
    let isCancelled = false;

    const schedule = () => {
        timer ??= setTimeout(() => {
            timer = undefined;
            isRunning = true;

            void refresh()
                .catch(() => undefined)
                .finally(() => {
                    isRunning = false;

                    if (isRequestedAgain && !isCancelled) {
                        isRequestedAgain = false;
                        schedule();
                    }
                });
        }, delay);
    };

    return {
        request: () => {
            if (isCancelled) {
                return;
            }

            if (isRunning) {
                isRequestedAgain = true;

                return;
            }

            schedule();
        },
        cancel: () => {
            isCancelled = true;
            clearTimeout(timer);
        },
    };
}
