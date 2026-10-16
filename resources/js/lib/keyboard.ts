import { useSyncExternalStore } from 'react';

export function isApplePlatform(): boolean {
    if (typeof navigator === 'undefined') {
        return false;
    }

    const platform =
        (navigator as Navigator & { userAgentData?: { platform?: string } })
            .userAgentData?.platform ?? navigator.platform;

    return /mac|iphone|ipad|ipod/i.test(platform);
}

/**
 * Either ⌘ or Ctrl counts, whatever the platform; only the labels differ.
 */
export function hasModKey(event: { metaKey: boolean; ctrlKey: boolean }) {
    return event.metaKey || event.ctrlKey;
}

const subscribe = () => () => {};

/**
 * The server can't know the client's platform, so it renders Ctrl and the client swaps in ⌘ after hydrating.
 */
export function useIsApplePlatform(): boolean {
    return useSyncExternalStore(subscribe, isApplePlatform, () => false);
}

export type KeyLabels = {
    mod: string;
    shift: string;
    enter: string;
    backspace: string;
    /** Writes a key combination the way the platform does, e.g. ⌘K or Ctrl+K. */
    combo: (...keys: string[]) => string;
};

const appleKeyLabels: KeyLabels = {
    mod: '⌘',
    shift: '⇧',
    enter: '↵',
    backspace: '⌫',
    combo: (...keys) => keys.join(''),
};

const otherKeyLabels: KeyLabels = {
    mod: 'Ctrl',
    shift: 'Shift',
    enter: 'Enter',
    backspace: 'Backspace',
    combo: (...keys) => keys.join('+'),
};

export function useKeyLabels(): KeyLabels {
    return useIsApplePlatform() ? appleKeyLabels : otherKeyLabels;
}
