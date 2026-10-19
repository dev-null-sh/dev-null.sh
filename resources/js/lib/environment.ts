export type IssueEnvironment = {
    browser: string;
    os: string;
    viewport: string;
    pixel_ratio: string;
    language: string;
    color_scheme: string;
    page: string;
};

type UserAgentData = {
    platform?: string;
    brands?: { brand: string; version: string }[];
};

const browsers: [string, RegExp][] = [
    ['Edge', /Edg\/([\d.]+)/],
    ['Opera', /OPR\/([\d.]+)/],
    ['Firefox', /Firefox\/([\d.]+)/],
    ['Chrome', /(?:Chrome|CriOS)\/([\d.]+)/],
    ['Safari', /Version\/([\d.]+).*Safari/],
];

const systems: [string, RegExp][] = [
    ['iOS', /iPhone|iPad|iPod/],
    ['Android', /Android/],
    ['Windows', /Windows/],
    ['macOS', /Mac OS X|Macintosh/],
    ['Linux', /Linux|X11/],
];

/**
 * What maintainers need to reproduce a report, kept coarse as it ends up in a public issue.
 */
export function collectEnvironment(): IssueEnvironment {
    const agent = navigator.userAgent;
    const data = (navigator as Navigator & { userAgentData?: UserAgentData })
        .userAgentData;
    const match = browsers
        .map(([name, pattern]) => [name, agent.match(pattern)] as const)
        .find(([, found]) => found !== null);

    return {
        browser: match ? `${match[0]} ${match[1]?.[1]}` : 'Unknown',
        os:
            data?.platform ||
            systems.find(([, pattern]) => pattern.test(agent))?.[0] ||
            'Unknown',
        viewport: `${window.innerWidth}x${window.innerHeight}`,
        pixel_ratio: String(window.devicePixelRatio),
        language: navigator.language,
        color_scheme: window.matchMedia('(prefers-color-scheme: dark)').matches
            ? 'dark'
            : 'light',
        page: window.location.pathname,
    };
}
