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
