import type { ReactNode } from 'react';

// Keep these rules in sync with app/Actions/FindFirstUrl.php.

// Top-level domains that a bare domain like "nativephp.com" may end with. It is an allowlist because most file
// extensions are also real TLDs. Leave out any that read like code or files (sh, py, rs, md, pl, cc, in, mk, ml, so,
// tf, ps, am, cl, gd, cr, do, zip, mov, ...) or like a property in code (user.id, user.name, app.run, ...).
const topLevelDomains = [
    'com org net edu gov mil info biz io dev app co ai me xyz tech gg tv fm ly to is blog cloud site online design',
    'studio tools software codes wiki news website club ninja rocks academy guide social digital agency fyi lol live',
    'uk de fr nl es it pt br ca au jp ch se no dk fi be at ie nz us eu cz sk hu ro bg gr hr si ee lv lt ru ua tr il za',
    'mx ar kr cn tw hk sg vn th',
]
    .join(' ')
    .split(' ');

// A URL with a scheme, or a bare domain: ASCII labels ending in an allowed TLD, optionally followed by a path, query or
// fragment. A bare domain can't follow a letter, digit, "_", ".", "/", "@" or "-". Its TLD must be all lowercase or all
// uppercase, and must end the word without a "(" or another label after it. There is no case-insensitive flag, so
// that the TLD's case can be checked.
const urlPattern = new RegExp(
    String.raw`\b[Hh][Tt][Tt][Pp][Ss]?:\/\/[^\s<>]+` +
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

function withoutTrailingPunctuation(candidate: string): string {
    let url = candidate;
    let previous: string;

    do {
        previous = url;
        url = url.replace(/[.,;:!?'"]+$/u, '');

        for (const [open, close] of brackets) {
            if (url.endsWith(close) && count(url, open) < count(url, close)) {
                url = url.slice(0, -1);
            }
        }
    } while (url !== previous);

    return url;
}

export function LinkifiedText({ text }: { text: string }) {
    const parts: ReactNode[] = [];
    let position = 0;

    for (const match of text.matchAll(urlPattern)) {
        const linkText = withoutTrailingPunctuation(match[0]);
        const href = schemePattern.test(linkText)
            ? linkText
            : `https://${linkText}`;

        if (href.endsWith('://') || Array.from(href).length > maxUrlLength) {
            continue;
        }

        parts.push(
            text.slice(position, match.index),
            <a
                key={match.index}
                href={href}
                target="_blank"
                rel="noopener noreferrer nofollow ugc"
                className="wrap-anywhere text-primary hover:underline"
            >
                {linkText}
            </a>,
        );

        position = match.index + linkText.length;
    }

    parts.push(text.slice(position));

    return <>{parts}</>;
}
