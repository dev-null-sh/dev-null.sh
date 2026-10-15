import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { mentionPattern } from '@/lib/mentions';
import { show } from '@/routes/user';

// Keep these rules in sync with app/Actions/FindFirstUrl.php and app/Actions/FindMentions.php.

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
// A GitHub username after an "@" that doesn't follow a letter, digit, "_", ".", "/", "@" or "-", and doesn't run
// into a path or a domain, so emails, "@inertiajs/react" and "@vue.js" are not mentions.
const mentionPattern =
    /(?<![\p{L}\p{N}_./@-])@([A-Za-z0-9](?:[A-Za-z0-9]|-(?=[A-Za-z0-9])){0,38})(?![A-Za-z0-9_@/-]|\.[A-Za-z0-9])/gu;
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

type Segment =
    | { type: 'text'; value: string; index: number }
    | { type: 'url'; value: string; href: string; index: number }
    | { type: 'mention'; value: string; username: string; index: number };

function segmentsFor(text: string): Segment[] {
    const matches: Segment[] = [];

    for (const match of text.matchAll(urlPattern)) {
        const linkText = withoutTrailingPunctuation(match[0]);
        const href = schemePattern.test(linkText)
            ? linkText
            : `https://${linkText}`;

        if (href.endsWith('://') || Array.from(href).length > maxUrlLength) {
            continue;
        }

        matches.push({
            type: 'url',
            value: linkText,
            href,
            index: match.index,
        });
    }

    for (const match of text.matchAll(mentionPattern)) {
        matches.push({
            type: 'mention',
            value: match[0],
            username: match[1],
            index: match.index,
        });
    }

    matches.sort((a, b) => a.index - b.index);

    const segments: Segment[] = [];
    let position = 0;

    for (const match of matches) {
        if (match.index < position) {
            continue;
        }

        if (match.index > position) {
            segments.push({
                type: 'text',
                value: text.slice(position, match.index),
                index: position,
            });
        }

        segments.push(match);
        position = match.index + match.value.length;
    }

    if (position < text.length) {
        segments.push({
            type: 'text',
            value: text.slice(position),
            index: position,
        });
    }

    return segments;
}

export function LinkifiedText({ text }: { text: string }) {
    const parts: ReactNode[] = [];

    for (const segment of segmentsFor(text)) {
        if (segment.type === 'text') {
            parts.push(segment.value);
            continue;
        }

        if (segment.type === 'url') {
            parts.push(
                <a
                    key={`url-${segment.index}`}
                    href={segment.href}
                    target="_blank"
                    rel="noopener noreferrer nofollow ugc"
                    className="wrap-anywhere text-primary hover:underline"
                >
                    {segment.value}
                </a>,
            );
            continue;
        }

        parts.push(
            <Link
                key={`mention-${segment.index}`}
                href={show({ username: segment.username })}
                className="font-medium text-primary hover:underline"
                data-test="mention"
            >
                {segment.value}
            </Link>,
        );
    }

    return <>{parts}</>;
}
