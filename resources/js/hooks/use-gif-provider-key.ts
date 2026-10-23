import { useMemo, useSyncExternalStore } from 'react';
import {
    readGifProviderKey,
    subscribeGifProviderKey,
} from '@/lib/gif-provider-key';
import type { GifProviderKey } from '@/lib/gif-provider-key';

const getServerSnapshot = (): null => null;

export function useGifProviderKey(
    userId: string | null,
): GifProviderKey | null {
    const subscribe = useMemo(
        () => (listener: () => void) =>
            userId === null
                ? () => undefined
                : subscribeGifProviderKey(userId, listener),
        [userId],
    );
    const getSnapshot = useMemo(
        () => () => (userId === null ? null : readGifProviderKey(userId)),
        [userId],
    );

    return useSyncExternalStore(subscribe, getSnapshot, getServerSnapshot);
}
