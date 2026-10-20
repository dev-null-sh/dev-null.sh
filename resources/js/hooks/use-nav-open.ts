import { usePage } from '@inertiajs/react';
import { useSyncExternalStore } from 'react';
import { setCookie } from '@/hooks/use-appearance';
import { runNavCollapse } from '@/lib/nav-collapse';

let chosenOpen: boolean | null = null;
let layoutOpen: boolean | null = null;
const listeners = new Set<() => void>();
const notify = (): void => listeners.forEach((listener) => listener());

const subscribe = (listener: () => void): (() => void) => {
    listeners.add(listener);

    return () => listeners.delete(listener);
};

/**
 * Once toggled, the choice lives here rather than in the page props, so toggling re-renders only the nav and the
 * layout, not the page, and stale prefetched props cannot flip it back. The choice applies at once, so quick clicks
 * each flip it; the layout follows when the animation gets to it.
 */
export function useNavOpen() {
    const { sidebarOpen } = usePage().props;
    const chosen = useSyncExternalStore(
        subscribe,
        () => chosenOpen,
        () => null,
    );
    const layout = useSyncExternalStore(
        subscribe,
        () => layoutOpen,
        () => null,
    );

    const setNavOpen = (open: boolean): void => {
        setCookie('sidebar_state', String(open));
        chosenOpen = open;
        notify();

        runNavCollapse(open, () => {
            layoutOpen = open;
            notify();
        });
    };

    return {
        isNavOpen: chosen ?? sidebarOpen,
        isLayoutOpen: layout ?? sidebarOpen,
        setNavOpen,
    };
}
