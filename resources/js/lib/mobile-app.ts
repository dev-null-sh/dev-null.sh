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
    /** The color under the status bar, as `#rrggbb`. */
    setTopColor(options: { color: string }): Promise<void>;
    addListener(
        event: 'collapse',
        listener: (event: { collapsed: boolean }) => void,
    ): Promise<Listener>;
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

    // Reading down a page shrinks the tab bar, and the compose button steps aside meanwhile, see `MobileCommitButton`
    void tabs.addListener('collapse', ({ collapsed }) => {
        document.documentElement.toggleAttribute(
            'data-tab-bar-collapsed',
            collapsed,
        );
    });

    followTopColor(tabs);
}

/**
 * The app shows the page below the status bar, and paints the band under it with the color at the very top of the
 * page, so they read as one surface: the header's, or a dialog's dimming over it. The color is measured rather than
 * known, so any page, menu or dialog matches.
 */
function followTopColor(tabs: TabsPlugin): void {
    let sent: string | null = null;
    let frame: number | null = null;

    const send = () => {
        frame = null;

        const color = topColor();

        if (color !== null && color !== sent) {
            sent = color;
            void tabs.setTopColor({ color });
        }
    };
    const measure = () => {
        frame ??= requestAnimationFrame(send);
    };
    // Dialogs fade their dimming in and out...
    const measureAfterTransitions = () => {
        measure();
        setTimeout(measure, 250);
    };

    measure();
    router.on('navigate', measureAfterTransitions);
    window.addEventListener('scroll', measure, { passive: true });
    window.addEventListener('resize', measure);
    new MutationObserver(measureAfterTransitions).observe(
        document.documentElement,
        { attributes: true, attributeFilter: ['class', 'style'] },
    );
    new MutationObserver(measureAfterTransitions).observe(document.body, {
        attributes: true,
        childList: true,
    });
}

/**
 * The color painted at the top of the page: the backgrounds under that point, from the topmost down to the first
 * opaque one, blended together.
 */
function topColor(): string | null {
    const layers: number[][] = [];

    for (const element of document.elementsFromPoint(
        window.innerWidth / 2,
        0,
    )) {
        const layer = cssColorToRgba(getComputedStyle(element).backgroundColor);

        if (layer === null || layer[3] === 0) {
            continue;
        }

        layers.push(layer);

        if (layer[3] === 1) {
            break;
        }
    }

    if (layers.at(-1)?.[3] !== 1) {
        const page = cssColorToRgba(
            getComputedStyle(document.documentElement).getPropertyValue(
                '--background',
            ),
        );

        if (page === null) {
            return null;
        }

        layers.push([page[0], page[1], page[2], 1]);
    }

    const [red, green, blue] = layers.reduceRight(
        (below, [r, g, b, alpha]) => [
            r * alpha + below[0] * (1 - alpha),
            g * alpha + below[1] * (1 - alpha),
            b * alpha + below[2] * (1 - alpha),
        ],
        [0, 0, 0],
    );

    return toHex(red, green, blue);
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

/** Any CSS color (`oklch()` included) as red, green, blue (0-255) and alpha (0-1). */
function cssColorToRgba(color: string): number[] | null {
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

    const [red, green, blue, alpha] = canvasContext.getImageData(
        0,
        0,
        1,
        1,
    ).data;

    return [red, green, blue, alpha / 255];
}

function toHex(red: number, green: number, blue: number): string {
    return `#${[red, green, blue].map((value) => Math.round(value).toString(16).padStart(2, '0')).join('')}`;
}

/** Any CSS color (`oklch()` included) as `#rrggbb`, which the app reads. */
function cssColorToHex(color: string): string | null {
    const rgba = cssColorToRgba(color);

    return rgba === null ? null : toHex(rgba[0], rgba[1], rgba[2]);
}
