import { router } from '@inertiajs/react';
import { useSyncExternalStore } from 'react';
import { signIn } from '@/lib/auth';
import type { LiteMobileAppTabs } from '@/lib/lite-mobile-app-tabs';
import { liteMobileAppTabTarget } from '@/lib/lite-mobile-app-tabs';
import { scrollLogToTop } from '@/lib/log-scroll';
import { login } from '@/routes';

/**
 * The bridge to the lite mobile app in apps/mobile: a Capacitor shell whose web view loads the site, with an iOS tab bar.
 * The app injects its plugins into the page, so the site bundles none of them, only the types of what it calls. The
 * server tells the app's web view apart by its user agent (`isLiteMobileApp`). Not the NativePHP app, see `mobile-apps.ts`.
 */

interface Listener {
    remove(): Promise<void>;
}

interface TabsPlugin {
    set(tabs: LiteMobileAppTabs): Promise<void>;
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

let shownTabs: LiteMobileAppTabs | null = null;
let pendingHide: number | null = null;

export function setLiteMobileAppTabs(tabs: LiteMobileAppTabs): void {
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
export function hideLiteMobileAppTabs(): void {
    if (shownTabs === null || pendingHide !== null) {
        return;
    }

    pendingHide = requestAnimationFrame(() => {
        pendingHide = null;

        if (shownTabs !== null) {
            setLiteMobileAppTabs({ ...shownTabs, visible: false });
        }
    });
}

let isStarted = false;

/**
 * Follows the taps on the app's tabs: a tab is a visit, and tapping the tab already shown scrolls back to its top, as
 * in any iOS app.
 */
export function startLiteMobileApp(): void {
    const tabs = tabsPlugin();

    if (tabs === null || isStarted) {
        return;
    }

    isStarted = true;

    // The app moves to the tapped tab right away: a visit that doesn't change the page must move it back
    const showCurrentTab = () => {
        if (shownTabs !== null) {
            void tabs.set(shownTabs);
        }
    };

    void tabs.addListener('select', ({ id, url, reselected }) => {
        const target = liteMobileAppTabTarget(url, window.location.href);

        if (target === null) {
            showCurrentTab();

            return;
        }

        if (reselected && target.pathname === window.location.pathname) {
            if (id === 'log') {
                scrollLogToTop();
            } else {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }

            return;
        }

        // A guest's tabs lead to signing in, which an Inertia visit cannot follow to GitHub
        if (target.pathname === login().url) {
            showCurrentTab();
            signIn();

            return;
        }

        router.visit(target.pathname + target.search + target.hash, {
            onFinish: showCurrentTab,
        });
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
    // Dialogs fade their dimming in and out, and view transitions cover the page until they end...
    const measureAfterTransitions = () => {
        measure();
        setTimeout(measure, 250);
        setTimeout(measure, 600);
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
 * Whether a modal dialog is open: Radix locks the page's scrolling meanwhile.
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
