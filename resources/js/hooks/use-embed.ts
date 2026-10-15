import type { RefObject } from 'react';
import {
    useCallback,
    useEffect,
    useId,
    useRef,
    useSyncExternalStore,
} from 'react';

const listeners = new Set<() => void>();
let openEmbed: string | null = null;

const subscribe = (listener: () => void) => {
    listeners.add(listener);

    return () => {
        listeners.delete(listener);
    };
};

const setOpenEmbed = (id: string | null) => {
    openEmbed = id;
    listeners.forEach((notify) => notify());
};

export type Embed = {
    isOpen: boolean;
    open: () => void;
    close: () => void;
    dismiss: () => void;
    bannerRef: RefObject<HTMLButtonElement | null>;
};

/**
 * Only one embed on the page is ever open: opening one closes the other. Dismissing an embed closes it and puts
 * focus back on its banner, while closing it (when it scrolls away) leaves focus where it is.
 */
export function useEmbed(): Embed {
    const id = useId();
    const bannerRef = useRef<HTMLButtonElement>(null);
    const isOpen = useSyncExternalStore(
        subscribe,
        () => openEmbed === id,
        () => false,
    );

    const open = useCallback(() => setOpenEmbed(id), [id]);

    const close = useCallback(() => {
        if (openEmbed === id) {
            setOpenEmbed(null);
        }
    }, [id]);

    const dismiss = useCallback(() => {
        close();
        requestAnimationFrame(() => bannerRef.current?.focus());
    }, [close]);

    useEffect(() => close, [close]);

    return { isOpen, open, close, dismiss, bannerRef };
}
