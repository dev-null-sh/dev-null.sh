import { Link } from '@inertiajs/react';
import type { ReactNode, SVGAttributes } from 'react';
import CommitController from '@/actions/App/Http/Controllers/CommitController';
import AppLogoIcon from '@/components/app-logo-icon';
import AppWordmark from '@/components/app-wordmark';
import { CodebergIcon } from '@/components/codeberg-icon';
import { GitHubIcon } from '@/components/github-icon';
import { GitLabIcon } from '@/components/gitlab-icon';
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

type ForgeItem = { kind: string; mark: string };

type Forge = {
    name: string;
    Icon: (props: SVGAttributes<SVGElement>) => ReactNode;
    pattern: RegExp;
    items: Record<string, ForgeItem>;
    reservedPaths: Set<string>;
};

type ForgeDefinition = Omit<Forge, 'pattern' | 'reservedPaths'> & {
    host: string;
    owner: string;
    // First path segments that are the forge's own pages rather than an owner, like "github.com/features/actions".
    reservedPaths: string;
};

// A link to a repository, or to one of its issues or pull requests, nothing after its name or number, so it can show as
// its forge writes it. Item paths are lowercased, and map to what they link to and the mark written before the number.
function defineForge({
    host,
    owner,
    reservedPaths,
    ...forge
}: ForgeDefinition): Forge {
    return {
        ...forge,
        pattern: new RegExp(
            `^https?://(?:www\\.)?${host}/(${owner})/([A-Za-z0-9._-]{1,100})(?:/(${Object.keys(forge.items).join('|')})/([1-9][0-9]{0,9}))?$`,
            'i',
        ),
        reservedPaths: new Set(reservedPaths.trim().split(/\s+/)),
    };
}

const issue: ForgeItem = { kind: 'issue', mark: '#' };
const pullRequest: ForgeItem = { kind: 'pull request', mark: '#' };
// Unlike GitHub, GitLab and Codeberg allow dots and underscores in owners. A GitLab subgroup's project stays a URL.
const ownerPattern = '[A-Za-z0-9][A-Za-z0-9._-]{0,99}';

const forges = [
    defineForge({
        name: 'GitHub',
        Icon: GitHubIcon,
        host: 'github\\.com',
        owner: '[A-Za-z0-9](?:[A-Za-z0-9]|-(?=[A-Za-z0-9])){0,38}',
        items: { issues: issue, pull: pullRequest },
        reservedPaths: `
            about apps codespaces collections customer-stories enterprise events explore features issues login marketplace
            new notifications orgs organizations pricing pulls search security settings site sponsors topics trending users
        `,
    }),
    defineForge({
        name: 'GitLab',
        Icon: GitLabIcon,
        host: 'gitlab\\.com',
        owner: ownerPattern,
        items: {
            '-/issues': issue,
            '-/merge_requests': { kind: 'merge request', mark: '!' },
        },
        reservedPaths:
            'admin dashboard explore groups help projects search users',
    }),
    defineForge({
        name: 'Codeberg',
        Icon: CodebergIcon,
        host: 'codeberg\\.org',
        owner: ownerPattern,
        items: { issues: issue, pulls: pullRequest },
        reservedPaths:
            'admin api assets explore issues milestones notifications org pulls repo user',
    }),
];

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

type ForgeLinkTarget = {
    forge: Forge;
    owner: string;
    repository: string;
    item: (ForgeItem & { number: string }) | null;
};

function forgeLinkTo(href: string): ForgeLinkTarget | null {
    for (const forge of forges) {
        const match = forge.pattern.exec(href);

        if (match && !forge.reservedPaths.has(match[1].toLowerCase())) {
            return {
                forge,
                owner: match[1],
                repository: match[2],
                item: match[3]
                    ? {
                          ...forge.items[match[3].toLowerCase()],
                          number: match[4],
                      }
                    : null,
            };
        }
    }

    return null;
}

// Like a dev/null commit link, the whole URL stays in the text, so copying it keeps the link.
function ForgeLink({
    url,
    href,
    target: { forge, owner, repository, item },
    isStatic,
}: {
    url: string;
    href: string;
    target: ForgeLinkTarget;
    isStatic: boolean;
}) {
    const reference = item ? `${item.mark}${item.number}` : '';
    const label = (
        <>
            <span className="text-[0px]">{url}</span>
            <span
                aria-hidden="true"
                className="font-mono text-[0.92em] wrap-anywhere select-none"
            >
                {/* The mark never wraps onto a line of its own. */}
                <span className="whitespace-nowrap text-muted-foreground">
                    <forge.Icon className="mr-[0.2em] inline-block size-[1.15em] align-[-0.25em] text-foreground" />
                    {owner.charAt(0)}
                </span>
                <span className="text-muted-foreground">{owner.slice(1)}/</span>
                {repository}
                {reference}
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
            aria-label={`${owner}/${repository}${item ? ` ${item.kind} ${reference}` : ''} on ${forge.name}`}
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
        const forgeLink = forgeLinkTo(href);

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
            ) : forgeLink ? (
                <ForgeLink
                    key={match.index}
                    url={linkText}
                    href={href}
                    target={forgeLink}
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
