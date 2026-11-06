export interface WebViewAppTab {
    id: string;
    title: string;
    /** An SF Symbol, or `commit` for the Log's icon. */
    icon: string;
    url: string;
    badge?: number;
}

export interface WebViewAppTabs {
    tabs: WebViewAppTab[];
    selected: string | null;
    visible: boolean;
    appearance: 'light' | 'dark';
    /** The color of the selected tab, as `#rrggbb`. */
    tint: string | null;
}

/** Profile, Settings and the rest are in the account menu of the header. */
const TABS: Record<string, { icon: string; paths: RegExp }> = {
    Log: { icon: 'commit', paths: /^\/(log(\/|$)|$)/ },
    Notifications: { icon: 'bell', paths: /^\/notifications(\/|$)/ },
    Remotes: {
        icon: 'bubble.left.and.bubble.right',
        paths: /^\/remotes(\/|$)/,
    },
};

/**
 * What the web view app's tab bar shows on a page. It only shows in the sections of its tabs: elsewhere, a profile, a
 * commit or the settings show over it, as iOS screens pushed over a tab do, and the edge swipe goes back. It also hides
 * under a modal dialog, as an iOS sheet covers the tab bar, or it would hide the dialog's last buttons.
 */
export function webViewAppTabs(
    items: {
        title: string;
        url: string;
        badge?: number;
    }[],
    path: string,
    page: {
        appearance: 'light' | 'dark';
        tint: string | null;
        isModalOpen: boolean;
    },
): WebViewAppTabs {
    const tabs = items.filter((item) => Object.hasOwn(TABS, item.title));
    const selected = tabs.find((item) => TABS[item.title].paths.test(path));

    return {
        tabs: tabs.map((item) => ({
            id: item.title.toLowerCase(),
            title: item.title,
            icon: TABS[item.title].icon,
            url: item.url,
            badge: item.badge,
        })),
        selected: selected?.title.toLowerCase() ?? null,
        visible: selected !== undefined && !page.isModalOpen,
        appearance: page.appearance,
        tint: page.tint,
    };
}

/**
 * The page of the site a tab leads to, or null if it leaves the site. The app checks too, but it parses URLs differently
 * and the site is the one visiting them: `/.//example.com` is a path to the app and another site to the browser.
 */
export function webViewAppTabTarget(url: string, page: string): URL | null {
    let target: URL;

    try {
        target = new URL(url, page);
    } catch {
        return null;
    }

    return target.origin === new URL(page).origin &&
        !target.pathname.startsWith('//')
        ? target
        : null;
}
