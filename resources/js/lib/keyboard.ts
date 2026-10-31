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

/**
 * The key a letter shortcut reads. Latin layouts (QWERTY, AZERTY, QWERTZ…) keep the letter they type, but a Cyrillic,
 * Greek, Hebrew or Arabic layout types "л" on the K key (or "؛" on Arabic Shift+P), so there the physical key decides,
 * like on GitHub or Gmail. AltGr, which Windows reports as Ctrl+Alt, types its character on purpose ("ķ" on Latvian).
 */
export function shortcutKey(event: {
    key: string;
    code: string;
    altKey?: boolean;
}): string {
    const physical = /^Key([A-Z])$/u.exec(event.code);

    if (physical && !event.altKey && /^\P{ASCII}$/u.test(event.key)) {
        return physical[1].toLowerCase();
    }

    return event.key;
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
