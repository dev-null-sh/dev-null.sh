export type InAppBrowser = {
    /** The app showing the page, when its user agent tells. */
    app: string | null;
    platform: 'ios' | 'android';
};

const APPS: [RegExp, string][] = [
    [/Twitter/i, 'X'],
    [/Instagram/, 'Instagram'],
    [/FBAN|FBAV|FB_IAB/, 'Facebook'],
    [/LinkedInApp/, 'LinkedIn'],
    [/Barcelona/, 'Threads'],
    [/BytedanceWebview|musical_ly/, 'TikTok'],
    [/Snapchat/, 'Snapchat'],
];

/**
 * The browser an app opens links in on a phone, rather than the phone's own browser. It is signed in to nothing, so
 * signing in with GitHub there means typing a password and a 2FA code, without the passkey or saved password Safari or
 * Chrome would offer. Known apps name themselves in the user agent; other web views drop Safari's token on iOS, or
 * add `wv` on Android. Apps that open links in Safari's own view look like Safari and aren't told apart.
 */
export function inAppBrowser(userAgent: string): InAppBrowser | null {
    const platform = /iPhone|iPad|iPod/.test(userAgent)
        ? 'ios'
        : /Android/.test(userAgent)
          ? 'android'
          : null;

    if (platform === null) {
        return null;
    }

    const app = APPS.find(([pattern]) => pattern.test(userAgent))?.[1] ?? null;
    const isWebView =
        app !== null ||
        (platform === 'ios'
            ? !/Safari\//.test(userAgent)
            : /; wv\)/.test(userAgent));

    return isWebView ? { app, platform } : null;
}
