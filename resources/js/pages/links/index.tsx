import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, ExternalLink, Link2, Star } from 'lucide-react';
import type { CSSProperties, FormEvent, MouseEvent } from 'react';
import { useState } from 'react';
import CommitLinkPreviewController from '@/actions/App/Http/Controllers/CommitLinkPreviewController';
import LinkController from '@/actions/App/Http/Controllers/LinkController';
import { CommitFeed, EmptyFeed } from '@/components/commit-feed';
import { GraphCanvas } from '@/components/graph-canvas';
import { GraphRail, RailSegment } from '@/components/graph-rail';
import { HiddenLinkPreviewContext } from '@/components/hidden-link-preview-context';
import { LinkPreview } from '@/components/link-preview';
import { LinkTopicCommits } from '@/components/link-topic-commits';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { clickedCommit } from '@/lib/commit-click';
import { exactCountTitle, formatCount } from '@/lib/numbers';
import { hostname } from '@/lib/urls';
import { cn, formatRelativeTime, isDeleted, laneColor } from '@/lib/utils';
import type {
    Commit,
    CursorPaginated,
    LinkPreview as LinkPreviewData,
} from '@/types';

type LinkDetails = Pick<LinkPreviewData, 'title' | 'description'>;

type TopLink = LinkDetails & {
    url: string;
    people: number;
    commits: number;
    last_mentioned_at: string;
    stars: number;
    stars_last_6h: number;
    top_commits: Commit[];
};

type LinkStats = { commits: number; people: number };

type Props =
    | { url: null; topLinks: TopLink[] }
    | {
          url: string;
          link: LinkPreviewData | null;
          stats: LinkStats;
          commits: CursorPaginated<Commit> | null;
      };

function commitCount(count: number) {
    return `${count} commit${count === 1 ? '' : 's'}`;
}

/**
 * What stands for a link as a topic. Most links are pages of any site, which go by their title and the site's icon. A
 * GitHub project has a better name and picture than its page's: its own, and its owner's avatar.
 */
function linkTopic(url: string, link: LinkDetails) {
    const [, owner, project] =
        /^https?:\/\/(?:www\.)?github\.com\/([^/?#]+)(?:\/([^/?#]+))?/.exec(
            url,
        ) ?? [];

    return owner
        ? {
              title: project ? `${owner}/${project}` : owner,
              iconUrl: `https://github.com/${owner}.png?size=160`,
          }
        : {
              title: link.title ?? displayedLink(url),
              iconUrl: siteFavicon(url),
          };
}

/** A stand-in until the app reads icons itself, when it fetches a link's page. */
function siteFavicon(url: string): string {
    return `https://favicon.show/${hostname(url)}`;
}

/**
 * Links have no colour of their own yet, so each one gets a lane of the palette, the same one every time.
 */
function linkLane(url: string): string {
    const hash = Array.from(url).reduce(
        (sum, character) => (sum * 31 + character.charCodeAt(0)) % 1_000_003,
        7,
    );

    return laneColor(hash % 6);
}

/** The whole link, without the protocol and the www. most links start with. */
function displayedLink(url: string): string {
    return url.replace(/^https?:\/\/(?:www\.)?/u, '');
}

function linkPage(url: string) {
    return LinkController.index({ query: { url } });
}

export default function Index(props: Props) {
    return props.url === null ? (
        <TopLinks links={props.topLinks} />
    ) : (
        <LinkPage
            url={props.url}
            link={props.link}
            stats={props.stats}
            commits={props.commits}
        />
    );
}

function TopicIcon({
    iconUrl,
    className,
}: {
    iconUrl: string | null;
    className?: string;
}) {
    const [isBroken, setIsBroken] = useState(false);
    const hasIcon = iconUrl !== null && !isBroken;

    return (
        <span
            className={cn(
                // White in both themes, as most icons are drawn for a light tab bar.
                'flex items-center justify-center overflow-hidden rounded-md border text-muted-foreground',
                hasIcon ? 'bg-white p-[12%]' : 'bg-muted',
                className,
            )}
        >
            {hasIcon ? (
                <img
                    src={iconUrl}
                    alt=""
                    referrerPolicy="no-referrer"
                    onError={() => setIsBroken(true)}
                    className="size-full object-contain"
                />
            ) : (
                <Link2 aria-hidden className="size-1/2" />
            )}
        </span>
    );
}

/** The image a page gives for sharing it, as large as sites draw it, leading to the page. */
function SocialCard({ url, imageUrl }: { url: string; imageUrl: string }) {
    const [isBroken, setIsBroken] = useState(false);

    return isBroken ? null : (
        <a
            href={url}
            target="_blank"
            rel="noopener noreferrer nofollow ugc"
            tabIndex={-1}
            className="mt-4 block overflow-hidden rounded-lg border transition-opacity hover:opacity-90"
        >
            <img
                src={imageUrl}
                alt=""
                referrerPolicy="no-referrer"
                onError={() => setIsBroken(true)}
                className="aspect-[1.91/1] w-full bg-muted object-cover"
            />
        </a>
    );
}

function LinkCount({ count, label }: { count: number; label: string }) {
    return (
        <li className="flex gap-1.5">
            <span
                className="font-mono font-medium tabular-nums"
                title={exactCountTitle(count)}
            >
                {formatCount(count)}
            </span>
            <span className="text-muted-foreground">{label}</span>
        </li>
    );
}

function TopLinks({ links }: { links: TopLink[] }) {
    const [draft, setDraft] = useState('');
    const active = links.filter((link) => link.stars_last_6h > 0);
    const quiet = links.filter((link) => link.stars_last_6h === 0);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const url = draft.trim();
        router.get(LinkController.index({ query: url === '' ? {} : { url } }));
    };

    return (
        <>
            <Head title="Links" />

            <header className="sticky top-0 z-10 border-b bg-card px-4 py-3 sm:px-6">
                <h1 className="sr-only">Links</h1>
                <form onSubmit={submit} className="relative">
                    <Link2
                        aria-hidden
                        className="pointer-events-none absolute top-1/2 left-4 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <input
                        type="url"
                        inputMode="url"
                        autoFocus
                        value={draft}
                        onChange={(event) => setDraft(event.target.value)}
                        placeholder="Paste a link to see every commit about it"
                        aria-label="Link"
                        autoComplete="off"
                        className="h-11 w-full rounded-lg border bg-background pr-4 pl-10 text-base outline-none placeholder:text-muted-foreground focus-visible:border-ring md:text-[15px]"
                    />
                </form>
            </header>

            {links.length === 0 ? (
                <div className="px-4 py-12 text-center sm:px-6">
                    <EmptyFeed command="fatal: no links shared yet">
                        Commits with a link card will show up here.
                    </EmptyFeed>
                </div>
            ) : (
                <GraphCanvas>
                    {active.map((link) => (
                        <LinkTopic key={link.url} link={link} />
                    ))}
                    {quiet.length > 0 && (
                        <section aria-label="Quiet links">
                            <h2 className="flex items-baseline gap-2 border-b px-4 pt-5 pb-2 font-mono text-xs sm:px-6">
                                <span>quiet</span>
                                <span className="text-muted-foreground">
                                    no stars in 6h
                                </span>
                            </h2>
                            <ul>
                                {quiet.map((link) => (
                                    <QuietLinkTopic
                                        key={link.url}
                                        link={link}
                                    />
                                ))}
                            </ul>
                        </section>
                    )}
                </GraphCanvas>
            )}
        </>
    );
}

/**
 * A link in the list of topics, drawn like a commit with replies: the topic stands where the author would, its line in
 * the topic's colour, and its most starred commits nest below, each in its own author's colour.
 */
function LinkTopic({ link }: { link: TopLink }) {
    const topic = linkTopic(link.url, link);
    const lane = linkLane(link.url);
    const href = linkPage(link.url);
    const nodeId = `topic:${link.url}`;
    const endId = `${nodeId}:end`;
    const hasMore = link.commits > link.top_commits.length;
    const shownLink = displayedLink(link.url);

    const openClickedCommit = (event: MouseEvent<HTMLElement>) => {
        const click = clickedCommit(
            event,
            window.getSelection()?.toString() ?? '',
            window.location.pathname,
        );

        if (click?.newTab) {
            window.open(click.url, '_blank', 'noopener');
        } else if (click) {
            router.visit(click.url);
        }
    };

    return (
        <section
            aria-label={topic.title}
            className="grid grid-cols-[4rem_minmax(0,1fr)] sm:grid-cols-[4.5rem_minmax(0,1fr)]"
            data-test="topic"
        >
            <GraphRail
                lane={lane}
                endsWithDot
                endsOnActions
                hasReplyTail={link.top_commits.length > 0}
                graph={{ id: nodeId, endId }}
                node={
                    <Link
                        prefetch
                        href={href}
                        tabIndex={-1}
                        className="flex rounded-lg"
                    >
                        <span className="relative flex rounded-lg bg-card">
                            <span className="relative flex rounded-lg border-2 border-(--lane) p-0.5">
                                <TopicIcon
                                    iconUrl={topic.iconUrl}
                                    className="size-10"
                                />
                            </span>
                        </span>
                    </Link>
                }
            />

            <div className="min-w-0 pt-3 pr-4">
                <Link
                    prefetch
                    href={href}
                    title={topic.title}
                    className="block truncate text-[15px] font-semibold hover:underline"
                >
                    {topic.title}
                </Link>
                {topic.title !== shownLink && (
                    <a
                        href={link.url}
                        target="_blank"
                        rel="noopener noreferrer nofollow ugc"
                        title={link.url}
                        className="mt-0.5 block truncate font-mono text-[13px] text-muted-foreground hover:text-foreground hover:underline"
                    >
                        {shownLink}
                    </a>
                )}
                <p className="mt-1 flex min-h-7 items-center gap-3 text-[13px] text-muted-foreground tabular-nums md:min-h-6">
                    <span
                        className="inline-flex items-center gap-1.5"
                        aria-label={`${link.stars} ${link.stars === 1 ? 'star' : 'stars'}`}
                        title={exactCountTitle(link.stars)}
                    >
                        <Star aria-hidden className="size-4" />
                        {formatCount(link.stars)}
                    </span>
                    <span>{commitCount(link.commits)}</span>
                </p>
            </div>
            <RailSegment />

            <div
                className="min-w-0 border-b pr-4 pb-3"
                onClick={openClickedCommit}
                onAuxClick={openClickedCommit}
            >
                <LinkTopicCommits
                    commits={link.top_commits}
                    lane={lane}
                    graphParent={endId}
                    more={hasMore ? { count: link.commits, href } : undefined}
                />
            </div>
        </section>
    );
}

/**
 * A link no commit got stars on lately folds to a line, with what was last said about it.
 */
function QuietLinkTopic({ link }: { link: TopLink }) {
    const topic = linkTopic(link.url, link);
    const last = link.top_commits[0];

    return (
        <li
            className="border-b"
            style={{ '--lane': linkLane(link.url) } as CSSProperties}
        >
            <Link
                prefetch
                href={linkPage(link.url)}
                className="grid grid-cols-[4rem_minmax(0,1fr)] items-center py-2.5 pr-4 transition-colors hover:bg-accent focus-visible:bg-accent sm:grid-cols-[4.5rem_minmax(0,1fr)]"
            >
                <span className="flex justify-center">
                    <span className="flex rounded-lg border-2 border-(--lane) p-0.5">
                        <TopicIcon iconUrl={topic.iconUrl} className="size-7" />
                    </span>
                </span>
                <span className="min-w-0">
                    <span className="flex items-baseline gap-x-1.5 text-sm">
                        <span
                            className="min-w-0 truncate font-semibold"
                            title={topic.title}
                        >
                            {topic.title}
                        </span>
                        <span className="ml-auto shrink-0 font-mono text-xs text-muted-foreground">
                            <span className="max-sm:hidden">
                                {commitCount(link.commits)} ·{' '}
                            </span>
                            last {formatRelativeTime(link.last_mentioned_at)}
                        </span>
                    </span>
                    {topic.title !== displayedLink(link.url) && (
                        <span
                            className="block truncate font-mono text-xs text-muted-foreground"
                            title={link.url}
                        >
                            {displayedLink(link.url)}
                        </span>
                    )}
                    {last && !isDeleted(last) && (
                        <span className="block truncate text-[13px] text-muted-foreground">
                            <span className="font-medium">
                                {last.author.name}
                            </span>{' '}
                            {last.body}
                        </span>
                    )}
                </span>
            </Link>
        </li>
    );
}

/**
 * A link's own page, like an organization's: who it is at the top, then its commits, each standing on its own in its
 * author's lane, like in a log.
 */
function LinkPage({
    url,
    link,
    stats,
    commits,
}: {
    url: string;
    link: LinkPreviewData | null;
    stats: LinkStats;
    commits: CursorPaginated<Commit> | null;
}) {
    const topic = link ? linkTopic(url, link) : null;
    const title = topic?.title ?? displayedLink(url);
    const lane = linkLane(url);
    const firstCommit = commits?.data[0];

    return (
        <>
            <Head title={title} />

            <div className="sticky top-0 z-10 flex h-14 items-center gap-3 border-b bg-card px-4 sm:px-6">
                <Link
                    prefetch
                    href={LinkController.index()}
                    aria-label="All links"
                    className="-ml-1.5 shrink-0 rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                >
                    <ArrowLeft aria-hidden className="size-4" />
                </Link>
                <p className="min-w-0 truncate font-semibold">{title}</p>
                <p className="shrink-0 font-mono text-[13px] text-muted-foreground">
                    link
                </p>
            </div>

            <header className="border-b px-4 pt-6 pb-5 sm:px-6">
                <div className="flex items-start justify-between gap-4">
                    <span
                        className="flex rounded-lg ring-2 ring-(--lane) ring-offset-4 ring-offset-card"
                        style={{ '--lane': lane } as CSSProperties}
                    >
                        <TopicIcon
                            iconUrl={topic?.iconUrl ?? siteFavicon(url)}
                            className="size-16 rounded-lg"
                        />
                    </span>

                    <Button variant="outline" asChild>
                        <a
                            href={url}
                            target="_blank"
                            rel="noopener noreferrer nofollow ugc"
                        >
                            <ExternalLink />
                            Open link
                        </a>
                    </Button>
                </div>

                <h1 className="mt-4 text-2xl font-semibold tracking-tight text-balance wrap-break-word">
                    {title}
                </h1>
                <p className="mt-0.5 flex items-center gap-1.5 font-mono text-sm text-muted-foreground">
                    <span
                        aria-hidden
                        className="size-2 shrink-0 rounded-full"
                        style={{ background: lane }}
                    />
                    <a
                        href={url}
                        target="_blank"
                        rel="noopener noreferrer nofollow ugc"
                        title={url}
                        className="min-w-0 truncate hover:text-foreground hover:underline"
                    >
                        {displayedLink(url)}
                    </a>
                </p>

                {link?.description && (
                    <p className="mt-3 max-w-prose text-[15px] leading-relaxed wrap-break-word">
                        {link.description}
                    </p>
                )}

                {link?.embed && firstCommit ? (
                    <LinkPreview
                        refreshUrl={CommitLinkPreviewController.show.url(
                            firstCommit.id,
                        )}
                        preview={link}
                        source={{ commitId: firstCommit.id }}
                    />
                ) : (
                    link?.image_url && (
                        <SocialCard url={url} imageUrl={link.image_url} />
                    )
                )}

                <ul className="mt-4 flex flex-wrap gap-x-4 gap-y-1 text-sm">
                    <LinkCount
                        count={stats.commits}
                        label={stats.commits === 1 ? 'commit' : 'commits'}
                    />
                    <LinkCount
                        count={stats.people}
                        label={stats.people === 1 ? 'person' : 'people'}
                    />
                </ul>
            </header>

            <div className="min-h-dvh">
                {commits === null ? (
                    <div className="px-4 py-12 text-center sm:px-6">
                        <EmptyFeed command="fatal: no commits about this link">
                            No commit uses this exact link as its card yet.
                        </EmptyFeed>
                    </div>
                ) : (
                    <HiddenLinkPreviewContext value={link?.url ?? null}>
                        <CommitFeed
                            commits={commits}
                            end="That's the first commit about this link."
                            empty={
                                <EmptyFeed command="fatal: no commits about this link">
                                    No commit you can see uses this link as its
                                    card.
                                </EmptyFeed>
                            }
                        />
                    </HiddenLinkPreviewContext>
                )}
            </div>
        </>
    );
}

Index.layout = AppLayout;
