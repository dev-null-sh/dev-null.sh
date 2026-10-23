import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import CommitController from '@/actions/App/Http/Controllers/CommitController';
import AppLogoIcon from '@/components/app-logo-icon';
import AppWordmark from '@/components/app-wordmark';
import { GitHubIcon } from '@/components/github-icon';
import { UserPreview } from '@/components/user-preview';
import { typedProse } from '@/lib/code';
import { mentionPattern } from '@/lib/mentions';
import {
    hrefFor,
    shortenUrl,
    urlPattern,
    withoutTrailingPunctuation,
} from '@/lib/urls';
import { shortHash } from '@/lib/utils';
import { show } from '@/routes/mention';
import type { BodySegment, Commit } from '@/types';

// A link to a commit on dev/null itself, nothing after its id, so it can show as the author and the commit's hash.
const commitLinkPattern =
    /^https?:\/\/(?:www\.)?dev-null\.sh\/@([A-Za-z0-9](?:[A-Za-z0-9]|-(?=[A-Za-z0-9])){0,38})\/commits\/([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})$/i;
const gitHubHostPattern = /^(?:www\.)?github\.com$/i;
const gitHubOwnerPattern =
    /^[A-Za-z0-9](?:[A-Za-z0-9]|-(?=[A-Za-z0-9])){0,38}$/;
const gitHubRepositoryPattern = /^[A-Za-z0-9._-]{1,100}$/;
const gitHubNumberPattern = /^[1-9][0-9]{0,9}$/;
const gitHubShaPattern = /^[0-9a-f]{7,40}$/i;
// Two refs compared, "base...head" or "base..head", each a branch, a tag or a commit.
const gitHubComparisonPattern =
    /^([A-Za-z0-9_][A-Za-z0-9._/-]*?)(\.{2,3})([A-Za-z0-9_][A-Za-z0-9._/-]*)$/;
const gitHubTagPattern =
    /^[A-Za-z0-9_][A-Za-z0-9._+-]*(?:\/[A-Za-z0-9._+-]+)*$/;
// First path segments that are GitHub's own pages rather than an owner, like "github.com/features/actions".
const gitHubReservedPaths = new Set(
    [
        'about apps codespaces collections customer-stories enterprise events explore features issues login marketplace',
        'new notifications orgs organizations pricing pulls search security settings site sponsors topics trending users',
    ]
        .join(' ')
        .split(' '),
);

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

/**
 * What a GitHub link points at inside a repository, as GitHub writes it after the repository's name ("#42",
 * "@4fa008f") and as a screen reader reads it ("issue #42", "commit 4fa008f").
 */
type GitHubItem = { suffix: string; description: string };

type GitHubTarget = {
    owner: string;
    repository: string;
    item: GitHubItem | null;
};

// A commit as GitHub shortens it; any other ref as it is.
function shortRef(ref: string): string {
    return gitHubShaPattern.test(ref) ? ref.slice(0, 7) : ref;
}

function gitHubItem(path: string[]): GitHubItem | null {
    const [first, ...rest] = path;
    const kind = first.toLowerCase();

    if (
        (kind === 'issues' || kind === 'pull') &&
        rest.length === 1 &&
        gitHubNumberPattern.test(rest[0])
    ) {
        return {
            suffix: `#${rest[0]}`,
            description: `${kind === 'pull' ? 'pull request' : 'issue'} #${rest[0]}`,
        };
    }

    if (
        kind === 'commit' &&
        rest.length === 1 &&
        gitHubShaPattern.test(rest[0])
    ) {
        const commit = shortRef(rest[0]);

        return { suffix: `@${commit}`, description: `commit ${commit}` };
    }

    const tag = rest.slice(1).join('/');

    if (
        kind === 'releases' &&
        rest[0] === 'tag' &&
        gitHubTagPattern.test(tag)
    ) {
        return { suffix: `@${tag}`, description: `release ${tag}` };
    }

    const comparison =
        kind === 'compare'
            ? gitHubComparisonPattern.exec(rest.join('/'))
            : null;

    if (comparison && !comparison[3].includes('..')) {
        const refs = `${shortRef(comparison[1])}${comparison[2]}${shortRef(comparison[3])}`;

        return { suffix: `@${refs}`, description: `comparison ${refs}` };
    }

    return null;
}

/**
 * A link to a GitHub repository, or to an issue, a pull request, a commit, a comparison or a release in it, nothing
 * after them, so it can show as GitHub writes it.
 */
function gitHubLinkTo(href: string): GitHubTarget | null {
    let url: URL;

    try {
        url = new URL(href);
    } catch {
        return null;
    }

    const [owner, repository, ...path] = url.pathname.slice(1).split('/');

    if (
        !gitHubHostPattern.test(url.hostname) ||
        url.username !== '' ||
        url.password !== '' ||
        url.port !== '' ||
        url.search !== '' ||
        url.hash !== '' ||
        !gitHubOwnerPattern.test(owner) ||
        gitHubReservedPaths.has(owner.toLowerCase()) ||
        repository === undefined ||
        !gitHubRepositoryPattern.test(repository)
    ) {
        return null;
    }

    if (path.length === 0) {
        return { owner, repository, item: null };
    }

    const item = gitHubItem(path);

    return item ? { owner, repository, item } : null;
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
                {item?.suffix}
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
            aria-label={`${owner}/${repository}${item ? ` ${item.description}` : ''} on GitHub`}
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
