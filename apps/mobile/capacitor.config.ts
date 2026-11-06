import type { CapacitorConfig } from '@capacitor/cli';

/**
 * The app is a shell around the site: its web view loads dev-null.sh, and the
 * site adapts to it when the user agent carries `DevNullLite/`. Deploying the
 * site updates the app, without going through the App Store.
 *
 * CAP_SERVER_URL points the app to another copy of the site, for example a
 * local one: `CAP_SERVER_URL=http://localhost:8000 bun run sync`.
 */
// The trailing slash matters: Capacitor keeps in the web view every URL starting
// with this one, so without it https://dev-null.shop would count as the site
const url = `${(process.env.CAP_SERVER_URL ?? 'https://dev-null.sh').replace(/\/+$/, '')}/`;

/** The same as MARKETING_VERSION in the Xcode project. */
const version = '1.0';

const config: CapacitorConfig = {
    // Permanent once a build is uploaded, and never names the store's "lite"
    appId: 'sh.devnull.app',
    appName: '/dev/null',
    webDir: 'www',
    // For the site's UI only: anything can send it, so it never authorizes
    appendUserAgent: `DevNullLite/${version}`,
    server: {
        url,
        cleartext: url.startsWith('http://'),
        // Shown when the site can't load: no network, mostly
        errorPath: 'offline.html',
    },
    ios: {
        // The site pads the notch and the home bar itself (viewport-fit=cover)
        contentInset: 'never',
        // Long-pressing a link opens the system preview, as in Safari
        allowsLinkPreview: true,
    },
};

export default config;
