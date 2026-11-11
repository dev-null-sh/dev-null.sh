import { Link, usePage } from '@inertiajs/react';
import { Fragment, useState } from 'react';
import type { ReactNode, SVGAttributes } from 'react';
import CommitController from '@/actions/App/Http/Controllers/CommitController';
import SearchController from '@/actions/App/Http/Controllers/SearchController';
import { show as showProfile } from '@/actions/App/Http/Controllers/UserController';
import AppLogoIcon from '@/components/app-logo-icon';
import AppWordmark from '@/components/app-wordmark';
import { CertificateText } from '@/components/certificate-text';
import { CodebergIcon } from '@/components/codeberg-icon';
import { GitHubIcon } from '@/components/github-icon';
import { GitLabIcon } from '@/components/gitlab-icon';
import { UserPreview } from '@/components/user-preview';
import { typedProse, withoutCode } from '@/lib/code';
import type { Color } from '@/lib/colors';
import { colorsIn } from '@/lib/colors';
import { mentionPattern } from '@/lib/mentions';
import {
    hrefFor,
    shortenUrl,
    urlPattern,
    withoutTrailingPunctuation,
} from '@/lib/urls';
import { cn, formatRelativeTime, shortHash } from '@/lib/utils';
import { show } from '@/routes/mention';
import { show as showOrganizationMention } from '@/routes/organization-mention';
import type { BodySegment, Commit } from '@/types';

// A link to a commit on dev/null itself, nothing after its id, so it can show as the author and the commit's hash.
const commitLinkPattern =
    /^https?:\/\/(?:www\.)?dev-null\.sh\/@([A-Za-z0-9](?:[A-Za-z0-9]|-(?=[A-Za-z0-9])){0,38})\/commits\/([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})$/i;
const hashtagPattern =
    /(?<![\p{L}\p{M}\p{N}_&/#>-])#\p{L}[\p{L}\p{M}\p{N}_]*/gu;
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
        'about account apps blog codespaces collections contact copilot customer-stories dashboard education enterprise',
        'enterprises events explore features git-guides home issues join login logout marketplace mobile models new',
        'notifications orgs organizations partners premium-support pricing pulls readme resources search security',
        'settings signup site solutions sponsors stars team topics trending users watching',
    ]
        .join(' ')
        .split(' '),
);

type ForgeItem = { kind: string; mark: string };

type ForgeBrand = {
    name: string;
    Icon: (props: SVGAttributes<SVGElement>) => ReactNode;
};

type Forge = ForgeBrand & {
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

const gitHub: ForgeBrand = { name: 'GitHub', Icon: GitHubIcon };

// GitHub links are read by gitHubLinkTo, which knows more of them.
const forges = [
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
    const { hiddenStart, shown, hiddenEnd, cut } = shortenUrl(url);
    const shownCharacters = Array.from(shown);

    return (
        <>
            {hiddenStart && <span className="text-[0px]">{hiddenStart}</span>}
            {cut ? (
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
                <>
                    {shown}
                    {hiddenEnd && (
                        <span className="text-[0px]">{hiddenEnd}</span>
                    )}
                </>
            )}
        </>
    );
}

// Like a shortened URL, the whole URL stays in the text, so copying it keeps the link.
function CommitLink({
    url,
    handle,
    commit,
    isStatic,
}: {
    url: string;
    handle: string;
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
                    {handle.charAt(0)}
                </span>
                <span className="text-muted-foreground">
                    {handle.slice(1)}/
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
            prefetch
            href={CommitController.show({ profile: handle, commit })}
            title={url}
            aria-label={`@${handle}’s commit ${hash}`}
            className="text-primary hover:underline"
        >
            {label}
        </Link>
    );
}

/**
 * What a forge link points at inside a repository, as its forge writes it after the repository's name ("#42", "!42",
 * "@4fa008f") and as a screen reader reads it ("issue #42", "merge request !42", "commit 4fa008f").
 */
type ForgeLinkItem = { suffix: string; description: string };

type ForgeLinkTarget = {
    forge: ForgeBrand;
    owner: string;
    repository: string | null;
    item: ForgeLinkItem | null;
};

// A commit as GitHub shortens it; any other ref as it is.
function shortRef(ref: string): string {
    return gitHubShaPattern.test(ref) ? ref.slice(0, 7) : ref;
}

const gitHubThreads: Record<string, string> = {
    issues: 'issue',
    pull: 'pull request',
    discussions: 'discussion',
};

// The anchors GitHub gives a comment or a review in an issue, a pull request or a discussion.
const gitHubThreadAnchors: Record<string, [RegExp, string][]> = {
    issues: [[/^#issuecomment-[1-9][0-9]*$/, 'comment']],
    pull: [
        [/^#(?:issuecomment-|discussion_r)[1-9][0-9]*$/, 'comment'],
        [/^#pullrequestreview-[1-9][0-9]*$/, 'review'],
    ],
    discussions: [[/^#discussioncomment-[1-9][0-9]*$/, 'comment']],
};

/**
 * An issue, a pull request or a discussion, or one of their comments or reviews, as GitHub writes it: "#42", or
 * "#42 (comment)".
 */
function gitHubThread(
    kind: string,
    rest: string[],
    hash: string,
): ForgeLinkItem | null {
    const thread = gitHubThreads[kind];

    if (
        thread === undefined ||
        rest.length !== 1 ||
        !gitHubNumberPattern.test(rest[0])
    ) {
        return null;
    }

    const reference = `${thread} #${rest[0]}`;

    if (hash === '') {
        return { suffix: `#${rest[0]}`, description: reference };
    }

    const anchor = gitHubThreadAnchors[kind].find(([pattern]) =>
        pattern.test(hash),
    )?.[1];

    return anchor
        ? {
              suffix: `#${rest[0]} (${anchor})`,
              description: `${anchor} on ${reference}`,
          }
        : null;
}

const gitHubLinesPattern = /^#L([1-9][0-9]*)(?:-L([1-9][0-9]*))?$/;

/**
 * A file, maybe some of its lines, or a folder, by its last name only so it stays short: "TheAlgorithm.php",
 * "TheAlgorithm.php:10-20" or "Services/". The branch or commit it was read at only stays in the URL.
 */
function gitHubFile(
    kind: 'blob' | 'tree',
    path: string[],
    hash: string,
): ForgeLinkItem | null {
    let names: string[];

    try {
        names = path.map((segment) => decodeURIComponent(segment));
    } catch {
        return null;
    }

    const name = names[names.length - 1];
    const full = names.join('/');

    if (kind === 'tree') {
        return hash === ''
            ? { suffix: `:${name}/`, description: `folder ${full}` }
            : null;
    }

    if (hash === '') {
        return { suffix: `:${name}`, description: `file ${full}` };
    }

    const lines = gitHubLinesPattern.exec(hash);

    if (!lines) {
        return null;
    }

    const [, first, last = first] = lines;

    return last === first
        ? {
              suffix: `:${name}:${first}`,
              description: `file ${full}, line ${first}`,
          }
        : {
              suffix: `:${name}:${first}-${last}`,
              description: `file ${full}, lines ${first} to ${last}`,
          };
}

function gitHubItem(path: string[], hash: string): ForgeLinkItem | null {
    const [first, ...rest] = path;
    const kind = first.toLowerCase();

    // Own keys only: a path segment like "constructor" names no thread.
    if (Object.hasOwn(gitHubThreads, kind)) {
        return gitHubThread(kind, rest, hash);
    }

    // After the branch or commit, the path, every segment named.
    if (
        (kind === 'blob' || kind === 'tree') &&
        rest.length >= 2 &&
        rest.every((segment) => segment !== '')
    ) {
        return gitHubFile(kind, rest.slice(1), hash);
    }

    if (hash !== '') {
        return null;
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
 * A link to a GitHub profile or organization, to a repository, or to an issue, a pull request, a discussion, a commit,
 * a comparison, a release, a file or a folder in it, nothing after them but a comment's or some lines' anchor, so it
 * can show as GitHub writes it.
 */
function gitHubLinkTo(href: string): ForgeLinkTarget | null {
    let url: URL;

    try {
        url = new URL(href);
    } catch {
        return null;
    }

    const segments = url.pathname.slice(1).split('/');

    if (
        !gitHubHostPattern.test(url.hostname) ||
        url.username !== '' ||
        url.password !== '' ||
        url.port !== '' ||
        url.search !== ''
    ) {
        return null;
    }

    // An organization's own page names it like its profile does.
    const [owner, repository, ...path] =
        segments.length === 2 && segments[0].toLowerCase() === 'orgs'
            ? segments.slice(1)
            : segments;

    if (
        !gitHubOwnerPattern.test(owner) ||
        gitHubReservedPaths.has(owner.toLowerCase())
    ) {
        return null;
    }

    if (repository === undefined) {
        return url.hash === ''
            ? { forge: gitHub, owner, repository: null, item: null }
            : null;
    }

    if (!gitHubRepositoryPattern.test(repository)) {
        return null;
    }

    if (path.length === 0) {
        return url.hash === ''
            ? { forge: gitHub, owner, repository, item: null }
            : null;
    }

    const item = gitHubItem(path, url.hash);

    return item ? { forge: gitHub, owner, repository, item } : null;
}

function forgeLinkTo(href: string): ForgeLinkTarget | null {
    const gitHubLink = gitHubLinkTo(href);

    if (gitHubLink) {
        return gitHubLink;
    }

    for (const forge of forges) {
        const match = forge.pattern.exec(href);

        if (match && !forge.reservedPaths.has(match[1].toLowerCase())) {
            const item = match[3] ? forge.items[match[3].toLowerCase()] : null;
            const reference = item ? `${item.mark}${match[4]}` : '';

            return {
                forge,
                owner: match[1],
                repository: match[2],
                item: item
                    ? {
                          suffix: reference,
                          description: `${item.kind} ${reference}`,
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
    const name = repository === null ? owner : `${owner}/${repository}`;
    const label = (
        <>
            <span className="text-[0px]">{url}</span>
            <span
                aria-hidden="true"
                className="font-mono text-[0.92em] wrap-anywhere select-none"
            >
                {/* The mark never wraps onto a line of its own. */}
                <span
                    className={cn('whitespace-nowrap', {
                        'text-muted-foreground': repository !== null,
                    })}
                >
                    <forge.Icon className="mr-[0.2em] inline-block size-[1.15em] align-[-0.25em] text-foreground" />
                    {owner.charAt(0)}
                </span>
                {repository === null ? (
                    owner.slice(1)
                ) : (
                    <>
                        <span className="text-muted-foreground">
                            {owner.slice(1)}/
                        </span>
                        {repository}
                        {item?.suffix}
                    </>
                )}
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
            aria-label={`${name}${item ? ` ${item.description}` : ''} on ${forge.name}`}
            className="text-primary hover:underline"
        >
            {label}
        </a>
    );
}

function withPasteis(text: string, pasteisMode: boolean): ReactNode {
    if (!pasteisMode) {
        return <CertificateText text={text} />;
    }

    return text
        .split(
            /(?<![\p{L}\p{M}\p{N}_])(pastel|pasteis|pastéis)(?![\p{L}\p{M}\p{N}_])/giu,
        )
        .map((part, index) =>
            index % 2 === 0 ? (
                <CertificateText key={index} text={part} />
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

function withWordmarks(
    text: string,
    offset: number,
    pasteisMode: boolean,
): ReactNode[] {
    return text
        .split(
            /(?<![\p{L}\p{M}\p{N}_./-])(\/dev\/null)(?![\p{L}\p{M}\p{N}_/-])/u,
        )
        .map((part, index) =>
            index % 2 === 0 ? (
                <Fragment key={`text-${offset}-${index}`}>
                    {withPasteis(part, pasteisMode)}
                </Fragment>
            ) : (
                <span
                    key={`wordmark-${offset}-${index}`}
                    title={part}
                    className="font-semibold tracking-tight whitespace-nowrap"
                >
                    <AppWordmark />
                </span>
            ),
        );
}

function Hashtag({ hashtag }: { hashtag: string }) {
    const { auth } = usePage().props;

    if (!auth.user) {
        return hashtag;
    }

    return (
        <Link
            prefetch
            href={SearchController.index({ query: { q: hashtag } })}
            className="font-medium text-primary hover:underline"
            data-test="hashtag"
        >
            {hashtag}
        </Link>
    );
}

function withHashtags(
    text: string,
    offset: number,
    hashtags: RegExpExecArray[],
    pasteisMode: boolean,
): ReactNode[] {
    const parts: ReactNode[] = [];
    let position = 0;

    for (const hashtag of hashtags) {
        const index = hashtag.index - offset;

        if (index < position || index + hashtag[0].length > text.length) {
            continue;
        }

        parts.push(
            ...withWordmarks(
                text.slice(position, index),
                offset + position,
                pasteisMode,
            ),
            <Hashtag key={`hashtag-${hashtag.index}`} hashtag={hashtag[0]} />,
        );

        position = index + hashtag[0].length;
    }

    parts.push(
        ...withWordmarks(text.slice(position), offset + position, pasteisMode),
    );

    return parts;
}

/**
 * The hex colors found in the whole text that fall in this slice of it, as their value with a swatch of the color.
 */
function withColors(
    text: string,
    offset: number,
    colors: Color[],
    hashtags: RegExpExecArray[],
    pasteisMode: boolean,
): ReactNode[] {
    const parts: ReactNode[] = [];
    let position = 0;

    for (const color of colors) {
        const index = color.index - offset;

        if (index < position || index + color.length > text.length) {
            continue;
        }

        parts.push(
            ...withHashtags(
                text.slice(position, index),
                offset + position,
                hashtags,
                pasteisMode,
            ),
            <span
                key={`color-${color.index}`}
                className="whitespace-nowrap"
                data-test="color"
            >
                <span
                    aria-hidden="true"
                    className="mr-0.5 inline-block size-[0.75em] rounded-sm border border-border align-[-0.05em]"
                    style={{ backgroundColor: `#${color.value}` }}
                />
                {text.slice(index, index + color.length)}
            </span>,
        );

        position = index + color.length;
    }

    parts.push(
        ...withHashtags(
            text.slice(position),
            offset + position,
            hashtags,
            pasteisMode,
        ),
    );

    return parts;
}

function withMentions(
    text: string,
    offset: number,
    mentions: Commit['mentions'],
    organizationMentions: Commit['organization_mentions'],
    colors: Color[],
    hashtags: RegExpExecArray[],
    pasteisMode: boolean,
    isStatic: boolean,
    linksEveryMention: boolean,
): ReactNode[] {
    const parts: ReactNode[] = [];
    let position = 0;

    for (const match of text.matchAll(mentionPattern)) {
        const handle = match[1].toLowerCase();
        const userId =
            mentions && Object.hasOwn(mentions, handle)
                ? mentions[handle]
                : null;
        const organizationId =
            organizationMentions && Object.hasOwn(organizationMentions, handle)
                ? organizationMentions[handle]
                : null;

        parts.push(
            ...withColors(
                text.slice(position, match.index),
                offset + position,
                colors,
                hashtags,
                pasteisMode,
            ),
            isStatic ? (
                match[0]
            ) : userId ? (
                <UserPreview
                    key={`mention-${offset + match.index}`}
                    user={{ handle: match[1] }}
                    userId={userId}
                >
                    <Link
                        prefetch
                        href={show(userId)}
                        className="font-medium text-primary hover:underline"
                        data-test="mention"
                    >
                        {match[0]}
                    </Link>
                </UserPreview>
            ) : organizationId ? (
                <Link
                    prefetch
                    key={`mention-${offset + match.index}`}
                    href={showOrganizationMention(organizationId)}
                    className="font-medium text-primary hover:underline"
                    data-test="organization-mention"
                >
                    {match[0]}
                </Link>
            ) : !linksEveryMention ? (
                match[0]
            ) : (
                // Anyone else may be on GitHub: their page looks them up for members. Guests and crawlers get a
                // missing page, so it isn't followed, nor prefetched, which would ask for it on every hover of a feed...
                <Link
                    key={`mention-${offset + match.index}`}
                    href={showProfile(match[1])}
                    rel="nofollow"
                    className="font-medium text-primary hover:underline"
                    data-test="handle-mention"
                >
                    {match[0]}
                </Link>
            ),
        );

        position = match.index + match[0].length;
    }

    parts.push(
        ...withColors(
            text.slice(position),
            offset + position,
            colors,
            hashtags,
            pasteisMode,
        ),
    );

    return parts;
}

type InlineOptions = {
    mentions: Commit['mentions'];
    organizationMentions: Commit['organization_mentions'];
    colors: Color[];
    hashtags: RegExpExecArray[];
    pasteisMode: boolean;
    isStatic: boolean;
    linksEveryMention: boolean;
};

// Discord-style inline markdown, matched before links and mentions so markers never leak into them. Styled
// pieces recurse through the same pass, so mentions and links still resolve inside bold or spoilers. Plain
// `code` passes through untouched.
const richInlinePattern =
    /(?<code>`[^`\n]*`)|(?<masked>\[(?<maskedLabel>[^\]\n]+)\]\((?<maskedUrl>https?:\/\/[^)\s]+)\))|(?<timestamp><t:(?<timestampAt>\d+)(?::(?<timestampFormat>[tTdDfFR]))?>)|(?<spoiler>\|\|(?<spoilerContent>[\s\S]+?)\|\|)|(?<bold>\*\*(?<boldContent>[^*\n]+?)\*\*)|(?<underline>__(?<underlineContent>[^_\n]+?)__)|(?<strike>~~(?<strikeContent>[^~\n]+?)~~)|(?<italicStar>\*(?<italicStarContent>[^*\n]+?)\*)|(?<italicUnder>_(?<italicUnderContent>[^_\n]+?)_)/g;

function isWordCharacter(character: string | undefined): boolean {
    return character !== undefined && /[\p{L}\p{M}\p{N}_]/u.test(character);
}

function SpoilerText({
    children,
    isStatic,
}: {
    children: ReactNode;
    isStatic: boolean;
}) {
    const [revealed, setRevealed] = useState(false);

    if (isStatic) {
        return (
            <span className="spoiler" data-revealed="true">
                {children}
            </span>
        );
    }

    return (
        <button
            type="button"
            onClick={() => setRevealed((previous) => !previous)}
            data-revealed={revealed}
            data-test="spoiler"
            className="spoiler cursor-pointer"
        >
            {children}
        </button>
    );
}

const timestampFormats: Record<string, Intl.DateTimeFormatOptions> = {
    t: { hour: 'numeric', minute: '2-digit' },
    T: { hour: 'numeric', minute: '2-digit', second: '2-digit' },
    d: { day: '2-digit', month: '2-digit', year: 'numeric' },
    D: { day: 'numeric', month: 'long', year: 'numeric' },
    f: {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    },
    F: {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    },
};

function TimestampText({ at, format }: { at: string; format: string }) {
    const date = new Date(Number(at) * 1000);

    if (Number.isNaN(date.getTime())) {
        return `<t:${at}${format === 'f' ? '' : `:${format}`}>`;
    }

    if (format === 'R') {
        return (
            <span
                title={new Intl.DateTimeFormat(undefined, {
                    dateStyle: 'full',
                    timeStyle: 'long',
                }).format(date)}
                data-test="timestamp"
            >
                {formatRelativeTime(date.toISOString())}
            </span>
        );
    }

    return (
        <span data-test="timestamp">
            {new Intl.DateTimeFormat(
                undefined,
                timestampFormats[format],
            ).format(date)}
        </span>
    );
}

function withRichInline(
    text: string,
    offset: number,
    keyPrefix: string,
    options: InlineOptions,
): ReactNode[] {
    const parts: ReactNode[] = [];
    let position = 0;
    // Links resolve after this pass, so markers inside a URL are left alone for the link pass.
    const linkRanges = [...text.matchAll(urlPattern)].map((link) => [
        link.index,
        link.index + withoutTrailingPunctuation(link[0]).length,
    ]);

    for (const match of text.matchAll(richInlinePattern)) {
        const groups = match.groups ?? {};
        const index = match.index;

        if (
            linkRanges.some(
                ([start, end]) =>
                    index >= start && index + match[0].length <= end,
            )
        ) {
            continue;
        }

        // Underscore italics only stand alone, so snake_case words keep their underscores.
        if (
            groups.italicUnderContent !== undefined &&
            (isWordCharacter(text[index - 1]) ||
                isWordCharacter(text[index + match[0].length]))
        ) {
            continue;
        }

        parts.push(
            ...withLinksAndMentions(
                text.slice(position, index),
                offset + position,
                keyPrefix,
                options,
            ),
        );
        position = index + match[0].length;

        const key = `${keyPrefix}-${offset + index}`;
        const nested = (inner: string, shift: number): ReactNode[] =>
            withRichInline(
                inner,
                offset + index + shift,
                `${keyPrefix}-nested`,
                options,
            );

        if (groups.code !== undefined) {
            parts.push(match[0]);
        } else if (groups.maskedLabel !== undefined) {
            const href = hrefFor(groups.maskedUrl);

            if (href === null) {
                parts.push(match[0]);
            } else if (options.isStatic) {
                parts.push(
                    <Fragment key={key}>
                        {nested(groups.maskedLabel, 1)}
                    </Fragment>,
                );
            } else {
                parts.push(
                    <a
                        key={key}
                        href={href}
                        target="_blank"
                        rel="noopener noreferrer nofollow ugc"
                        title={href}
                        className="wrap-anywhere text-primary hover:underline"
                    >
                        {nested(groups.maskedLabel, 1)}
                    </a>,
                );
            }
        } else if (groups.timestampAt !== undefined) {
            parts.push(
                <TimestampText
                    key={key}
                    at={groups.timestampAt}
                    format={groups.timestampFormat ?? 'f'}
                />,
            );
        } else if (groups.spoilerContent !== undefined) {
            parts.push(
                <SpoilerText key={key} isStatic={options.isStatic}>
                    {nested(groups.spoilerContent, 2)}
                </SpoilerText>,
            );
        } else if (groups.boldContent !== undefined) {
            parts.push(
                <strong key={key}>{nested(groups.boldContent, 2)}</strong>,
            );
        } else if (groups.underlineContent !== undefined) {
            parts.push(<u key={key}>{nested(groups.underlineContent, 2)}</u>);
        } else if (groups.strikeContent !== undefined) {
            parts.push(<s key={key}>{nested(groups.strikeContent, 2)}</s>);
        } else if (groups.italicStarContent !== undefined) {
            parts.push(
                <em key={key}>{nested(groups.italicStarContent, 1)}</em>,
            );
        } else if (groups.italicUnderContent !== undefined) {
            parts.push(
                <em key={key}>{nested(groups.italicUnderContent, 1)}</em>,
            );
        }
    }

    parts.push(
        ...withLinksAndMentions(
            text.slice(position),
            offset + position,
            keyPrefix,
            options,
        ),
    );

    return parts;
}

export function LinkifiedText({
    text,
    mentions,
    organizationMentions = null,
    pasteisMode = false,
    isStatic = false,
    linksEveryMention = true,
    startsText = false,
}: {
    text: string;
    mentions: Commit['mentions'];
    organizationMentions?: Commit['organization_mentions'];
    pasteisMode?: boolean;
    isStatic?: boolean;
    linksEveryMention?: boolean;
    // Whether the text is a whole body, so its start is the start of a line.
    startsText?: boolean;
}) {
    // Colors are found in the prose only, for callers that pass a whole body, code included.
    const prose = withoutCode(text);
    const colors = colorsIn(prose, startsText);
    const hashtags = isStatic ? [] : [...prose.matchAll(hashtagPattern)];

    return (
        <>
            {withRichInline(text, 0, 'rich', {
                mentions,
                organizationMentions,
                colors,
                hashtags,
                pasteisMode,
                isStatic,
                linksEveryMention,
            })}
        </>
    );
}

function withLinksAndMentions(
    text: string,
    offset: number,
    keyPrefix: string,
    options: InlineOptions,
): ReactNode[] {
    const {
        mentions,
        organizationMentions,
        colors,
        hashtags,
        pasteisMode,
        isStatic,
        linksEveryMention,
    } = options;
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
                offset + position,
                mentions,
                organizationMentions,
                colors,
                hashtags,
                pasteisMode,
                isStatic,
                linksEveryMention,
            ),
            commitLink ? (
                <CommitLink
                    key={`${keyPrefix}-link-${match.index}`}
                    url={linkText}
                    handle={commitLink[1]}
                    commit={commitLink[2].toLowerCase()}
                    isStatic={isStatic}
                />
            ) : forgeLink ? (
                <ForgeLink
                    key={`${keyPrefix}-link-${match.index}`}
                    url={linkText}
                    href={href}
                    target={forgeLink}
                    isStatic={isStatic}
                />
            ) : isStatic ? (
                linkText
            ) : (
                <a
                    key={`${keyPrefix}-link-${match.index}`}
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
            offset + position,
            mentions,
            organizationMentions,
            colors,
            hashtags,
            pasteisMode,
            isStatic,
            linksEveryMention,
        ),
    );

    return parts;
}
