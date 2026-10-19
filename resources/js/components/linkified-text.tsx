import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import CommitController from '@/actions/App/Http/Controllers/CommitController';
import AppLogoIcon from '@/components/app-logo-icon';
import AppWordmark from '@/components/app-wordmark';
import { GitHubIcon } from '@/components/github-icon';
import { UserPreview } from '@/components/user-preview';
import { typedProse } from '@/lib/code';
import { mentionPattern } from '@/lib/mentions';
import { shortenUrl } from '@/lib/urls';
import { shortHash } from '@/lib/utils';
import { show } from '@/routes/mention';
import type { BodySegment, Commit } from '@/types';

// Keep these rules in sync with app/Services/Urls.php and app/Services/Mentions.php.

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
// A link to a commit on dev/null itself, nothing after its id, so it can show as the author and the commit's hash.
const commitLinkPattern =
    /^https?:\/\/(?:www\.)?dev-null\.sh\/@([A-Za-z0-9](?:[A-Za-z0-9]|-(?=[A-Za-z0-9])){0,38})\/commits\/([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})$/i;
// A link to a GitHub repository, or to one of its issues or pull requests, nothing after its name or number, so it can
// show as GitHub writes it.
const gitHubLinkPattern =
    /^https?:\/\/(?:www\.)?github\.com\/([A-Za-z0-9](?:[A-Za-z0-9]|-(?=[A-Za-z0-9])){0,38})\/([A-Za-z0-9._-]{1,100})(?:\/(issues|pull)\/([1-9][0-9]{0,9}))?$/i;
// First path segments that are GitHub's own pages rather than an owner, like "github.com/features/actions".
const gitHubReservedPaths = new Set(
    [
        'about apps codespaces collections customer-stories enterprise events explore features issues login marketplace',
        'new notifications orgs organizations pricing pulls search security settings site sponsors topics trending users',
    ]
        .join(' ')
        .split(' '),
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

function hrefFor(linkText: string): string | null {
    const href = schemePattern.test(linkText)
        ? linkText
        : `https://${linkText}`;

    return href.endsWith('://') || Array.from(href).length > maxUrlLength
        ? null
        : href;
}

/**
 * Every link in the prose being typed, in order and once each, as the server finds them for the link card.
 */
export function typedLinks(text: string): string[] {
    const links = new Set<string>();

    for (const prose of typedProse(text)) {
        for (const match of prose.matchAll(urlPattern)) {
            const href = hrefFor(withoutTrailingPunctuation(match[0]));

            if (href !== null) {
                links.add(href);
            }
        }
    }

    return [...links];
}

/**
 * Whether the prose outside code mentions someone, by the same rules that link a mention.
 */
export function hasMention(segments: BodySegment[]): boolean {
    return segments.some(
        (segment) =>
            segment.type === 'text' &&
            segment.text.search(mentionPattern) !== -1,
    );
}

// Like X, the hidden parts of a shortened URL stay in the text, so copying it or a screen reader gets the whole URL.
function ShortenedUrl({ url }: { url: string }) {
    const { hiddenStart, shown, hiddenEnd } = shortenUrl(url);
    const shownCharacters = Array.from(shown);

    return (
        <>
            {hiddenStart && <span className="text-[0px]">{hiddenStart}</span>}
            {hiddenEnd ? (
                <>
                    {shownCharacters.slice(0, -1).join('')}
                    {/* The ellipsis never wraps onto a line of its own. */}
                    <span className="whitespace-nowrap">
                        {shownCharacters.at(-1)}
                        <span className="text-[0px]">{hiddenEnd}</span>
                        <span aria-hidden="true" className="select-none">
                            …
                        </span>
                    </span>
                </>
            ) : (
                shown
            )}
        </>
    );
}

// Like a shortened URL, the whole URL stays in the text, so copying it keeps the link.
function CommitLink({
    url,
    username,
    commit,
    isStatic,
}: {
    url: string;
    username: string;
    commit: string;
    isStatic: boolean;
}) {
    const hash = shortHash(commit);
    const label = (
        <>
            <span className="text-[0px]">{url}</span>
            <span
                aria-hidden="true"
                className="font-mono text-[0.92em] wrap-anywhere select-none"
            >
                {/* The mark never wraps onto a line of its own. */}
                <span className="whitespace-nowrap text-muted-foreground">
                    <AppLogoIcon className="mr-[0.2em] inline-block size-[1.15em] fill-foreground align-[-0.25em]" />
                    {username.charAt(0)}
                </span>
                <span className="text-muted-foreground">
                    {username.slice(1)}/
                </span>
                {hash}
            </span>
        </>
    );

    if (isStatic) {
        return (
            <span title={url} className="text-primary">
                {label}
            </span>
        );
    }

    return (
        <Link
            href={CommitController.show({ user: username, commit })}
            title={url}
            aria-label={`@${username}’s commit ${hash}`}
            className="text-primary hover:underline"
        >
            {label}
        </Link>
    );
}

type GitHubItem = { kind: 'issue' | 'pull request'; number: string };

function gitHubLinkTo(href: string): {
    owner: string;
    repository: string;
    item: GitHubItem | null;
} | null {
    const match = gitHubLinkPattern.exec(href);

    if (!match || gitHubReservedPaths.has(match[1].toLowerCase())) {
        return null;
    }

    return {
        owner: match[1],
        repository: match[2],
        item: match[3]
            ? {
                  kind:
                      match[3].toLowerCase() === 'pull'
                          ? 'pull request'
                          : 'issue',
                  number: match[4],
              }
            : null,
    };
}

// Like a dev/null commit link, the whole URL stays in the text, so copying it keeps the link.
function GitHubLink({
    url,
    href,
    owner,
    repository,
    item,
    isStatic,
}: {
    url: string;
    href: string;
    owner: string;
    repository: string;
    item: GitHubItem | null;
    isStatic: boolean;
}) {
    const label = (
        <>
            <span className="text-[0px]">{url}</span>
            <span
                aria-hidden="true"
                className="font-mono text-[0.92em] wrap-anywhere select-none"
            >
                {/* The mark never wraps onto a line of its own. */}
                <span className="whitespace-nowrap text-muted-foreground">
                    <GitHubIcon className="mr-[0.2em] inline-block size-[1.15em] align-[-0.25em] text-foreground" />
                    {owner.charAt(0)}
                </span>
                <span className="text-muted-foreground">{owner.slice(1)}/</span>
                {repository}
                {item && `#${item.number}`}
            </span>
        </>
    );

    if (isStatic) {
        return (
            <span title={href} className="text-primary">
                {label}
            </span>
        );
    }

    return (
        <a
            href={href}
            target="_blank"
            rel="noopener noreferrer nofollow ugc"
            title={href}
            aria-label={`${owner}/${repository}${item ? ` ${item.kind} #${item.number}` : ''} on GitHub`}
            className="text-primary hover:underline"
        >
            {label}
        </a>
    );
}

function withPasteis(text: string, pasteisMode: boolean): ReactNode {
    if (!pasteisMode) {
        return text;
    }

    return text
        .split(
            /(?<![\p{L}\p{M}\p{N}_])(pastel|pasteis|pastéis)(?![\p{L}\p{M}\p{N}_])/giu,
        )
        .map((part, index) =>
            index % 2 === 0 ? (
                part
            ) : (
                <img
                    key={index}
                    src="/pastel-de-nata.svg"
                    alt={part}
                    title={part}
                    width={20}
                    height={20}
                    className="inline-block size-[1.25em] object-contain align-[-0.2em]"
                />
            ),
        );
}

function withWordmarks(text: string, pasteisMode: boolean): ReactNode[] {
    return text
        .split(
            /(?<![\p{L}\p{M}\p{N}_./-])(\/dev\/null)(?![\p{L}\p{M}\p{N}_/-])/u,
        )
        .map((part, index) =>
            index % 2 === 0 ? (
                withPasteis(part, pasteisMode)
            ) : (
                <span
                    key={`wordmark-${index}`}
                    title={part}
                    className="font-semibold tracking-tight whitespace-nowrap"
                >
                    <AppWordmark />
                </span>
            ),
        );
}

function withMentions(
    text: string,
    offset: number,
    mentions: Commit['mentions'],
    pasteisMode: boolean,
    isStatic: boolean,
): ReactNode[] {
    const parts: ReactNode[] = [];
    let position = 0;

    for (const match of text.matchAll(mentionPattern)) {
        const username = match[1].toLowerCase();
        const userId =
            mentions && Object.hasOwn(mentions, username)
                ? mentions[username]
                : null;

        parts.push(
            ...withWordmarks(text.slice(position, match.index), pasteisMode),
            isStatic || !userId ? (
                match[0]
            ) : (
                <UserPreview
                    key={`mention-${offset + match.index}`}
                    user={{ username: match[1] }}
                    userId={userId}
                >
                    <Link
                        href={show(userId)}
                        className="font-medium text-primary hover:underline"
                        data-test="mention"
                    >
                        {match[0]}
                    </Link>
                </UserPreview>
            ),
        );

        position = match.index + match[0].length;
    }

    parts.push(...withWordmarks(text.slice(position), pasteisMode));

    return parts;
}

export function LinkifiedText({
    text,
    mentions,
    pasteisMode = false,
    isStatic = false,
}: {
    text: string;
    mentions: Commit['mentions'];
    pasteisMode?: boolean;
    isStatic?: boolean;
}) {
    const parts: ReactNode[] = [];
    let position = 0;

    for (const match of text.matchAll(urlPattern)) {
        const linkText = withoutTrailingPunctuation(match[0]);
        const href = hrefFor(linkText);

        if (href === null) {
            continue;
        }

        const commitLink = commitLinkPattern.exec(linkText);
        const gitHubLink = gitHubLinkTo(href);

        parts.push(
            ...withMentions(
                text.slice(position, match.index),
                position,
                mentions,
                pasteisMode,
                isStatic,
            ),
            commitLink ? (
                <CommitLink
                    key={match.index}
                    url={linkText}
                    username={commitLink[1]}
                    commit={commitLink[2].toLowerCase()}
                    isStatic={isStatic}
                />
            ) : gitHubLink ? (
                <GitHubLink
                    key={match.index}
                    url={linkText}
                    href={href}
                    owner={gitHubLink.owner}
                    repository={gitHubLink.repository}
                    item={gitHubLink.item}
                    isStatic={isStatic}
                />
            ) : isStatic ? (
                linkText
            ) : (
                <a
                    key={match.index}
                    href={href}
                    target="_blank"
                    rel="noopener noreferrer nofollow ugc"
                    title={href}
                    className="wrap-anywhere text-primary hover:underline"
                >
                    <ShortenedUrl url={linkText} />
                </a>
            ),
        );

        position = match.index + linkText.length;
    }

    parts.push(
        ...withMentions(
            text.slice(position),
            position,
            mentions,
            pasteisMode,
            isStatic,
        ),
    );

    return <>{parts}</>;
}
