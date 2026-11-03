import { useEffect, useEffectEvent, useSyncExternalStore } from 'react';
import type { RealtimeChannel } from '@/lib/realtime';
import { isListening, listen, onRealtimeChange } from '@/lib/realtime';

/**
 * Whether updates on the channel are being heard, for polling to back them up only when they aren't.
 */
export function useIsListening(channel: RealtimeChannel | null): boolean {
    return useSyncExternalStore(
        onRealtimeChange,
        () => channel !== null && isListening(channel),
        () => false,
    );
}

/**
 * Listens for the given events while the channel is set. `onSync` runs whenever the channel is joined again after
 * a dropped connection, when updates may have been missed; the first join is left out, since what the page shows was
 * just read.
 */
export function useRealtime(
    channel: RealtimeChannel | null,
    events: string[],
    onEvent: (event: string, payload: unknown) => void,
    onSync?: () => void,
): void {
    const handle = useEffectEvent(onEvent);
    const sync = useEffectEvent(() => onSync?.());
    const name = channel?.name;
    const type = channel?.type;
    const listened = events.join(' ');

    useEffect(() => {
        if (name === undefined || type === undefined) {
            return;
        }

        // Already joined by someone else, the next join is after a dropped connection.
        let hasJoined = isListening({ name, type });

        return listen(
            { name, type },
            Object.fromEntries(
                listened
                    .split(' ')
                    .map((event) => [
                        event,
                        (payload: unknown) => handle(event, payload),
                    ]),
            ),
            () => {
                if (hasJoined) {
                    sync();
                }

                hasJoined = true;
            },
        );
    }, [name, type, listened]);
}

/**
 * The ids an update names, under the given key.
 */
export function idsOf(payload: unknown, key = 'ids'): string[] {
    const ids =
        typeof payload === 'object' && payload !== null
            ? (payload as Record<string, unknown>)[key]
            : undefined;

    return Array.isArray(ids)
        ? ids.filter((id): id is string => typeof id === 'string')
        : [];
}
