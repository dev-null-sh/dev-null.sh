import { useSyncExternalStore } from 'react';

const subscribe = () => () => undefined;

/**
 * Whether this device has a system share sheet. The server and the first render say no, so hydration matches.
 */
export function useCanShare(): boolean {
    return useSyncExternalStore(
        subscribe,
        () => typeof navigator.share === 'function',
        () => false,
    );
}
