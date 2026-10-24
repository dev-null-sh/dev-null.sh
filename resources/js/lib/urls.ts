// Keep URL recognition in sync with app/Services/Urls.php. Bare domains deliberately exclude file-like TLDs.
const topLevelDomains = [
    'com org net edu gov mil info biz io dev app co ai me xyz tech gg tv fm ly to is blog cloud site online design',
    'studio tools software codes wiki news website club ninja rocks academy guide social digital agency fyi lol live',
    'uk de fr nl es it pt br ca au jp ch se no dk fi be at ie nz us eu cz sk hu ro bg gr hr si ee lv lt ru ua tr il za',
    'mx ar kr cn tw hk sg vn th',
]
    .join(' ')
    .split(' ');

// Schemes cannot follow Unicode letters, numbers, marks or connector punctuation. Avoid JS/PHP differences in \b.
export const urlPattern = new RegExp(
    String.raw`(?<![\p{L}\p{N}\p{M}\p{Pc}])[Hh][Tt][Tt][Pp][Ss]?:\/\/[^\s<>]+` +
        String.raw`|(?<![\p{L}\p{N}_.\/@-])(?:[A-Za-z0-9](?:[A-Za-z0-9-]*[A-Za-z0-9])?\.)+` +
        String.raw`(?:${[...topLevelDomains, ...topLevelDomains.map((domain) => domain.toUpperCase())].join('|')})` +
        String.raw`(?![\p{L}\p{N}_(-]|\.[A-Za-z0-9])(?:[\/?#][^\s<>]*)?`,
    'gu',
);
const schemePattern = /^https?:\/\//i;
const maxUrlLength = 2048;
const brackets = [
    ['(', ')'],
    ['[', ']'],
] as const;

function count(text: string, character: string): number {
    return text.split(character).length - 1;
}

export function withoutTrailingPunctuation(candidate: string): string {
    let url = candidate;
    let previous: string;

    do {
        previous = url;
        // The ellipsis ends a feed excerpt, and smart punctuation closes quotes with ” and ’.
        url = url.replace(/[.,;:!?'"…”’]+$/u, '');

        for (const [open, close] of brackets) {
            if (url.endsWith(close) && count(url, open) < count(url, close)) {
                url = url.slice(0, -1);
            }
        }
    } while (url !== previous);

    return url;
}

export function hrefFor(linkText: string): string | null {
    const href = schemePattern.test(linkText)
        ? linkText
        : `https://${linkText}`;

    return href.endsWith('://') || Array.from(href).length > maxUrlLength
        ? null
        : href;
}

// Keep in sync with app/Services/Urls.php: like X, a URL shows without its scheme or "www.", with the whole host, and
// with at most 20 characters after it, the last of which is an ellipsis when it's cut. A cut never splits a
// percent-encoded character.
const maxShortenedPathLength = 20;

export type ShortenedUrl = {
    hiddenStart: string;
    shown: string;
    hiddenEnd: string;
};

/**
 * Splits a URL into what is shown and what is hidden around it. The ellipsis is shown whenever the end is hidden.
 */
export function shortenUrl(url: string): ShortenedUrl {
    const hiddenStart =
        /^(?:https?:\/\/)?(?:www\.(?=[^/?#]*\.))?/i.exec(url)?.[0] ?? '';
    const rest = url.slice(hiddenStart.length);
    const host = rest.replace(/[/?#][\s\S]*$/u, '');
    const path = Array.from(rest.slice(host.length));

    if (path.length <= maxShortenedPathLength) {
        return { hiddenStart, shown: rest, hiddenEnd: '' };
    }

    const kept = path
        .slice(0, maxShortenedPathLength - 1)
        .join('')
        .replace(/%[0-9A-Fa-f]?$/, '');

    return {
        hiddenStart,
        shown: host + kept,
        hiddenEnd: rest.slice(host.length + kept.length),
    };
}

export function hostname(url: string): string {
    try {
        return new URL(url).hostname.replace(/^www\./, '');
    } catch {
        return url;
    }
}

/**
 * GitHub serves avatars at 460px unless asked for less, so they are fetched at the size they are drawn.
 */
export function sizedAvatarUrl(url: string, pixels: number): string {
    try {
        const avatar = new URL(url);

        if (avatar.hostname !== 'avatars.githubusercontent.com') {
            return url;
        }

        avatar.searchParams.set('s', String(pixels));

        return avatar.toString();
    } catch {
        return url;
    }
}
