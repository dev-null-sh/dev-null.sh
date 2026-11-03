import type {
    RealtimeChannelHandle,
    RealtimeConnection,
    RealtimeHandler,
} from '../../resources/js/lib/realtime.ts';

export type FakeChannel = RealtimeChannelHandle & {
    emit: (event: string, payload?: unknown) => void;
    join: () => void;
    reject: () => void;
    listeners: Map<string, Set<RealtimeHandler>>;
};

export function fakeConnection() {
    const joined = new Map<string, FakeChannel>();
    const left: string[] = [];
    let status: (status: string) => void = () => {};

    const open = (name: string): FakeChannel => {
        const existing = joined.get(name);

        if (existing) {
            return existing;
        }

        const listeners = new Map<string, Set<RealtimeHandler>>();
        const subscribed: (() => void)[] = [];
        const errors: ((status: unknown) => void)[] = [];
        const channel: FakeChannel = {
            listeners,
            listen(event, callback) {
                listeners.set(
                    event,
                    new Set([...(listeners.get(event) ?? []), callback]),
                );
            },
            stopListening(event, callback) {
                listeners.get(event)?.delete(callback);
            },
            subscribed: (callback) => subscribed.push(callback),
            error: (callback) => errors.push(callback),
            emit: (event, payload = {}) =>
                listeners.get(`.${event}`)?.forEach((listener) => {
                    listener(payload);
                }),
            join: () => subscribed.forEach((callback) => callback()),
            reject: () => errors.forEach((callback) => callback(403)),
        };
        joined.set(name, channel);

        return channel;
    };

    const connection: RealtimeConnection = {
        channel: open,
        private: (name) => open(`private-${name}`),
        leaveChannel: (name) => {
            left.push(name);
            joined.delete(name);
        },
        socketId: () => '1.2',
        connector: {
            onConnectionChange: (callback) => {
                status = callback;

                return () => {};
            },
        },
    };

    return {
        connection,
        joined,
        left,
        setStatus: (next: string) => status(next),
    };
}


/**
 * Connects, joins every channel listened to so far, and returns what to emit on them.
 */
export function joinAll(fake: ReturnType<typeof fakeConnection>): void {
    fake.setStatus('connected');
    fake.joined.forEach((channel) => channel.join());
}
