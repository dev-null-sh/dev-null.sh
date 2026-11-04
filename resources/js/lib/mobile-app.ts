import { router } from '@inertiajs/react';
import { useSyncExternalStore } from 'react';

/**
 * The bridge to the iOS app POC: a Capacitor shell whose web view loads the site, with an iOS tab bar and sign-in. The
 * app injects its plugins into the page, so the site bundles none of them, only the types of what it calls. The server
 * tells the app's web view apart by its user agent (`isMobileApp`).
 *
 * Temporary: the proof of concept of a mobile app wrapping the site, to be reverted.
 *
 * Signing in needs nothing here: the app catches every visit to /login itself, see `App\Services\AppSignIn`.
 */

interface Listener {
    remove(): Promise<void>;
}

export interface MobileAppTab {
    id: string;
    title: string;
    /** An SF Symbol, or `commit` for the Log's icon. */
    icon: string;
    url: string;
    badge?: number;
    dot?: boolean;
}

export interface MobileAppTabs {
    tabs: MobileAppTab[];
    selected: string | null;
    visible: boolean;
    appearance: 'light' | 'dark';
    /** The color of the selected tab, as `#rrggbb`. */
    tint: string | null;
}

interface TabsPlugin {
    set(tabs: MobileAppTabs): Promise<void>;
    addListener(
        event: 'select',
        listener: (event: {
            id: string;
            url: string;
            reselected: boolean;
        }) => void,
    ): Promise<Listener>;
}

interface Capacitor {
    isNativePlatform(): boolean;
    Plugins: { Tabs?: TabsPlugin };
}

declare global {
    interface Window {
        Capacitor?: Capacitor;
    }
}

function tabsPlugin(): TabsPlugin | null {
    return typeof window !== 'undefined' && window.Capacitor?.isNativePlatform()
        ? (window.Capacitor.Plugins.Tabs ?? null)
        : null;
}

let shownTabs: MobileAppTabs | null = null;
let pendingHide: number | null = null;

export function setMobileAppTabs(tabs: MobileAppTabs): void {
    if (pendingHide !== null) {
        cancelAnimationFrame(pendingHide);
        pendingHide = null;
    }

    shownTabs = tabs;
    void tabsPlugin()?.set(tabs);
}

/**
 * Pages without the nav, such as the onboarding, hide the tab bar: the same tabs are sent, hidden, so the app keeps
 * them for the next page. A frame later, so a page that mounts the nav again doesn't make the bar blink.
 */
export function hideMobileAppTabs(): void {
    if (shownTabs === null || pendingHide !== null) {
        return;
    }

    pendingHide = requestAnimationFrame(() => {
        pendingHide = null;

        if (shownTabs !== null) {
            setMobileAppTabs({ ...shownTabs, visible: false });
        }
    });
}

/**
 * Signing in leaves for GitHub, which the app catches on a full page load only, not on an Inertia visit.
 */
function visit(url: string): void {
    const target = new URL(url, window.location.href);

    if (target.pathname === '/login') {
        window.location.assign(target);

        return;
    }

    router.visit(target.pathname + target.search + target.hash);
}

let isStarted = false;

/**
 * Follows the taps on the app's tabs: a tab is a visit, and tapping the tab already shown scrolls back to its top, as
 * in any iOS app.
 */
export function startMobileApp(): void {
    const tabs = tabsPlugin();

    if (tabs === null || isStarted) {
        return;
    }

    isStarted = true;

    void tabs.addListener('select', ({ url, reselected }) => {
        const target = new URL(url, window.location.href);

        if (reselected && target.pathname === window.location.pathname) {
            window.scrollTo({ top: 0, behavior: 'smooth' });

            return;
        }

        visit(url);
    });
}

function subscribeToTheme(onChange: () => void): () => void {
    const observer = new MutationObserver(onChange);

    observer.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['class', 'style'],
    });

    return () => observer.disconnect();
}

/**
 * The appearance and primary color the page shows, for the tab bar to match, also when the reader changes them.
 */
export function useDocumentTheme(): string {
    return useSyncExternalStore(
        subscribeToTheme,
        () =>
            `${document.documentElement.classList.contains('dark') ? 'dark' : 'light'} ${cssColorToHex(getComputedStyle(document.documentElement).getPropertyValue('--primary')) ?? ''}`,
        () => 'dark ',
    );
}

function subscribeToModals(onChange: () => void): () => void {
    const observer = new MutationObserver(onChange);

    observer.observe(document.body, {
        attributes: true,
        attributeFilter: ['data-scroll-locked'],
    });

    return () => observer.disconnect();
}

/**
 * Whether a modal dialog is open: Radix locks the page's scrolling meanwhile. The tab bar hides under it, as an iOS
 * sheet covers the tab bar, or it would hide the dialog's last buttons.
 */
export function useIsModalOpen(): boolean {
    return useSyncExternalStore(
        subscribeToModals,
        () => document.body.hasAttribute('data-scroll-locked'),
        () => false,
    );
}

let canvasContext: CanvasRenderingContext2D | null | undefined;

/** Any CSS color (`oklch()` included) as `#rrggbb`, which the app reads. */
function cssColorToHex(color: string): string | null {
    if (color.trim() === '') {
        return null;
    }

    canvasContext ??= document.createElement('canvas').getContext('2d', {
        willReadFrequently: true,
    });

    if (!canvasContext) {
        return null;
    }

    canvasContext.clearRect(0, 0, 1, 1);
    canvasContext.fillStyle = color.trim();
    canvasContext.fillRect(0, 0, 1, 1);

    const [red, green, blue] = canvasContext.getImageData(0, 0, 1, 1).data;

    return `#${[red, green, blue].map((value) => value.toString(16).padStart(2, '0')).join('')}`;
}
