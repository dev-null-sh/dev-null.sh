import { Link, router } from '@inertiajs/react';
import { Link2 } from 'lucide-react';
import type { MouseEvent } from 'react';
import { useState } from 'react';
import { GraphCanvas } from '@/components/graph-canvas';
import { GraphRail, RailSegment } from '@/components/graph-rail';
import { LinkTopicCommits } from '@/components/link-topic-commits';
import { clickedCommit } from '@/lib/commit-click';
import { formatCount } from '@/lib/numbers';
import { hostname, readableUrl } from '@/lib/urls';
import { cn, laneColor } from '@/lib/utils';
import type { Commit } from '@/types';

function commitCount(count: number): string {
    return `${formatCount(count)} commit${count === 1 ? '' : 's'}`;
}

/** A stand-in until the app reads icons itself, when it fetches a link's page. */
export function siteFavicon(url: string): string | null {
    return URL.canParse(url) ? `https://favicon.show/${hostname(url)}` : null;
}

/**
 * Links have no colour of their own yet, so each one gets a lane of the palette, the same one every time.
 */
export function linkLane(url: string): string {
    const hash = Array.from(url).reduce(
        (sum, character) => (sum * 31 + character.charCodeAt(0)) % 1_000_003,
        7,
    );

    return laneColor(hash % 6);
}

/** The whole link, readable, without the protocol and the www. most links start with. */
export function displayedLink(url: string): string {
    return readableUrl(url).replace(/^https?:\/\/(?:www\.)?/u, '');
}

export function TopicIcon({
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

/**
 * A link drawn like a commit with replies: the link stands where the author would, its line in the link's colour, and
 * its commits nest below, each in its own author's colour.
 */
function LinkTopicBlock({
    url,
    pageUrl,
    title,
    count,
    commits,
}: {
    url: string;
    pageUrl: string;
    title: string | null;
    count: number;
    commits: Commit[];
}) {
    const lane = linkLane(url);
    const nodeId = `topic:${url}`;
    const endId = `${nodeId}:end`;
    const shownLink = displayedLink(url);
    const shownTitle = title ?? shownLink;

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
        <div
            className="grid grid-cols-[4rem_minmax(0,1fr)] sm:grid-cols-[4.5rem_minmax(0,1fr)]"
            data-test="topic"
        >
            <GraphRail
                lane={lane}
                endsWithDot
                endsOnActions
                hasReplyTail={commits.length > 0}
                graph={{ id: nodeId, endId }}
                node={
                    <Link
                        prefetch
                        href={pageUrl}
                        tabIndex={-1}
                        className="flex rounded-lg"
                    >
                        <span className="relative flex rounded-lg bg-card">
                            <span className="relative flex rounded-lg border-2 border-(--lane) p-0.5">
                                <TopicIcon
                                    iconUrl={siteFavicon(url)}
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
                    href={pageUrl}
                    title={shownTitle}
                    className="block truncate text-[15px] font-semibold hover:underline"
                >
                    {shownTitle}
                </Link>
                {shownTitle !== shownLink && (
                    <a
                        href={url}
                        target="_blank"
                        rel="noopener noreferrer nofollow ugc"
                        title={url}
                        className="mt-0.5 block truncate font-mono text-[13px] text-muted-foreground hover:text-foreground hover:underline"
                    >
                        {shownLink}
                    </a>
                )}
                <p className="mt-1 flex min-h-7 items-center text-[13px] text-muted-foreground tabular-nums md:min-h-6">
                    {commitCount(count)}
                </p>
            </div>
            <RailSegment />

            <div
                className="min-w-0 pr-4 pb-3"
                onClick={openClickedCommit}
                onAuxClick={openClickedCommit}
            >
                <LinkTopicCommits
                    commits={commits}
                    lane={lane}
                    graphParent={endId}
                    more={
                        count > commits.length
                            ? { count, href: pageUrl }
                            : undefined
                    }
                />
            </div>
        </div>
    );
}

/** The commits about a commit's link from outside its thread, which its page shows under the thread. */
export type LinkTopicAround = {
    url: string;
    page_url: string;
    title: string | null;
    count: number;
    commits: Commit[];
};

/**
 * What others say about the link of the commit shown, before the commits related to it: the link drawn as a topic,
 * with its most starred commits from outside the thread.
 */
export function LinkTopicSection({ topic }: { topic: LinkTopicAround }) {
    return (
        <section
            aria-labelledby="link-topic"
            className="border-t"
            data-test="link-topic-section"
        >
            <div className="flex h-11 items-center border-b px-4 sm:px-6">
                <h2 id="link-topic" className="min-w-0 truncate font-semibold">
                    More on {hostname(topic.url)}
                </h2>
            </div>

            <GraphCanvas>
                <LinkTopicBlock
                    url={topic.url}
                    pageUrl={topic.page_url}
                    title={topic.title}
                    count={topic.count}
                    commits={topic.commits}
                />
            </GraphCanvas>
        </section>
    );
}
