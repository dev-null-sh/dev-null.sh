import type { CSSProperties, MouseEvent, ReactNode } from 'react';
import { useEffect, useRef } from 'react';
import CommitController from '@/actions/App/Http/Controllers/CommitController';
import CommitLinkPreviewController from '@/actions/App/Http/Controllers/CommitLinkPreviewController';
import AppLogoIcon from '@/components/app-logo-icon';
import { AuthorAvatar } from '@/components/author-avatar';
import { CommitBody } from '@/components/commit-body';
import { CommitHeader } from '@/components/commit-header';
import { CommitImages } from '@/components/commit-images';
import { CommitPoll } from '@/components/commit-poll';
import { CommitStarLink } from '@/components/commit-star-button';
import {
    maxIndentDepth,
    MoreRepliesLink,
    RepliesLink,
    ReplyBranch,
    replyRing,
    ReplySpine,
    ThreadColumn,
    ThreadElbow,
    threadLayouts,
    ThreadLink,
} from '@/components/commit-thread';
import type { ReplyGraph } from '@/components/commit-thread';
import { EmbeddedContext } from '@/components/embedded-context';
import {
    GraphCanvas,
    useGraphNode,
    useGraphNodeId,
    WithoutGraph,
} from '@/components/graph-canvas';
import {
    GraphRail,
    RailAvatar,
    RailDot,
    RailSegment,
} from '@/components/graph-rail';
import { LinkPreview } from '@/components/link-preview';
import { QuotedCommit } from '@/components/quoted-commit';
import type { ThreadPosition } from '@/components/thread-position-badge';
import { TombstoneLine } from '@/components/tombstone-line';
import { authorLaneColor, cn, isDeleted } from '@/lib/utils';
import { show as showProfile } from '@/routes/user';
import type { Commit } from '@/types';

type Tombstone = 'deleted' | 'unavailable';

export type EmbedNode = {
    id: string;
    tombstone: Tombstone | null;
    commit: Commit | null;
    replies: EmbedNode[];
    more: number;
};

type Options = {
    thread: boolean;
    replies: number;
    depth: number;
    theme: 'auto' | 'light' | 'dark';
    maxHeight: number;
};

const tombstoneLabels: Record<Tombstone, string> = {
    deleted: '[deleted]',
    unavailable: '[unavailable]',
};

const tombstoneSentences: Record<Tombstone, string> = {
    deleted: 'This commit was deleted.',
    unavailable: "This commit isn't available.",
};

// The card renders in the reader's browser, so dates read in their own time, like on /dev/null.
const dateFormat = new Intl.DateTimeFormat('en-US', {
    month: 'short',
    day: 'numeric',
});
const fullDateFormat = new Intl.DateTimeFormat('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
});
const timeFormat = new Intl.DateTimeFormat('en-GB', {
    hour: '2-digit',
    minute: '2-digit',
});

function laneOfNode(node: EmbedNode): string {
    return node.commit ? authorLaneColor(node.commit.author) : 'var(--rail)';
}

// The actions row of /dev/null, read-only: the star and the replies link open the commit there.
// Like collapsed replies on /dev/null, replies the card leaves out entirely are counted here; open ones that it cuts
// end with "Show the last N" instead, so they're counted once.
function Actions({
    commit,
    hiddenReplies,
    url,
}: {
    commit: Commit;
    hiddenReplies: number;
    url: string;
}) {
    return (
        <div className="mt-1 -ml-2 flex h-6 items-center gap-0.5 text-[13px]">
            <CommitStarLink
                count={commit.stars_count}
                href={url}
                className="px-2 py-0.5"
            />
            {hiddenReplies > 0 && (
                <RepliesLink count={hiddenReplies} href={url} />
            )}
        </div>
    );
}

// A commit the card can't show, like a deleted reply on /dev/null: its label and sentence, then the link to its
// hidden replies, without the star.
function TombstoneContent({
    node,
    url,
    small = false,
    endsWithDot = false,
}: {
    node: EmbedNode;
    url: string;
    small?: boolean;
    endsWithDot?: boolean;
}) {
    const tombstone = node.tombstone ?? 'unavailable';
    const replies = hiddenReplies(node);

    return (
        <>
            <TombstoneLine
                label={tombstoneLabels[tombstone]}
                sentence={tombstoneSentences[tombstone]}
                size={small ? 'reply' : 'commit'}
                className={
                    small
                        ? 'min-h-(--av) items-center'
                        : 'min-h-10 items-center'
                }
            />
            {replies > 0 ? (
                <div className="mt-1 -ml-2 flex h-6 items-center text-[13px]">
                    <RepliesLink count={replies} href={url} />
                </div>
            ) : (
                endsWithDot && <div className="h-6" />
            )}
        </>
    );
}

// Replies the card leaves out entirely, which only the actions row counts.
const hiddenReplies = (node: EmbedNode): number =>
    node.replies.length === 0 ? node.more : 0;

function Content({
    commit,
    url,
    isFocal = false,
}: {
    commit: Commit;
    url: string;
    isFocal?: boolean;
}) {
    return (
        <>
            <CommitBody
                segments={commit.body_segments}
                mentions={commit.mentions}
                organizationMentions={commit.organization_mentions}
                className={isFocal ? 'text-[17px]' : 'text-[15px]'}
            />
            <CommitImages commit={commit} />
            {commit.poll && (
                <CommitPoll
                    commit={commit}
                    poll={commit.poll}
                    isStatic
                    voteUrl={url}
                />
            )}
            {/* Replies don't carry their quote, as on /dev/null, where they only read "quoted". */}
            {commit.quoted_commit_id !== null &&
                commit.quoted_commit !== undefined && (
                    <QuotedCommit
                        commit={commit.quoted_commit}
                        embedded={
                            commit.quoted_commit === null ||
                            isDeleted(commit.quoted_commit)
                                ? undefined
                                : {
                                      href: CommitController.show.url({
                                          profile: commit.quoted_commit.author,
                                          commit: commit.quoted_commit,
                                      }),
                                      date: dateFormat.format(
                                          new Date(
                                              commit.quoted_commit.created_at,
                                          ),
                                      ),
                                  }
                        }
                    />
                )}
            {/* Without a source, the preview reads nothing again from the page. */}
            {commit.link_preview && (
                <LinkPreview
                    refreshUrl={CommitLinkPreviewController.show.url(commit.id)}
                    preview={commit.link_preview}
                />
            )}
        </>
    );
}

// /dev/null's commit header, its links leading there, with a short absolute date.
function Head({
    commit,
    threadPosition,
    isQuote = false,
    small = false,
}: {
    commit: Commit;
    threadPosition?: ThreadPosition;
    isQuote?: boolean;
    small?: boolean;
}) {
    return (
        <CommitHeader
            commit={commit}
            href={CommitController.show.url({
                profile: commit.author,
                commit,
            })}
            profileHref={showProfile.url(commit.author)}
            date={dateFormat.format(new Date(commit.created_at))}
            size={small ? 'reply' : 'commit'}
            threadPosition={threadPosition}
            isQuote={isQuote}
            hasHash={!small}
            isEmbedded
        />
    );
}

// A commit of the card, laid out like on its /dev/null page: the shared rail on the left, the commit beside it, then its
// replies under it. The rail draws its own lines in CSS until the graph canvas, once the page runs, draws them instead.
function EmbedCommit({
    node,
    previous,
    isFocal = false,
    isLast = false,
    threadPosition,
    showsThreadLink = false,
    url,
}: {
    node: EmbedNode;
    previous?: EmbedNode;
    isFocal?: boolean;
    isLast?: boolean;
    threadPosition?: ThreadPosition;
    showsThreadLink?: boolean;
    url: string;
}) {
    const { commit } = node;
    const lane = laneOfNode(node);
    const repliesShown = node.replies.length > 0;
    const endId = `${node.id}:end`;
    const isGroupedWithPrevious =
        commit !== null && previous?.commit?.author.id === commit.author.id;
    // Commits are separated like on /dev/null; the last one sits on the card's footer, which has its own border.
    const separator = isLast ? 'pb-3' : 'border-b pb-3';

    return (
        <article>
            <div className="grid grid-cols-[4rem_minmax(0,1fr)] sm:grid-cols-[4.5rem_minmax(0,1fr)]">
                <GraphRail
                    lane={lane}
                    previousLane={previous ? laneOfNode(previous) : undefined}
                    endsWithDot={isLast}
                    endsOnActions
                    hasReplyTail={repliesShown && isLast}
                    graph={{ id: node.id, after: previous?.id, endId }}
                    node={
                        commit === null ? (
                            <span className="size-12 rounded-full border-2 border-dashed border-rail bg-card" />
                        ) : isGroupedWithPrevious ? (
                            <RailDot />
                        ) : (
                            <RailAvatar
                                user={
                                    commit.author as Parameters<
                                        typeof RailAvatar
                                    >[0]['user']
                                }
                            />
                        )
                    }
                />
                <div
                    className={cn(
                        'min-w-0 pt-3 pr-4',
                        repliesShown ? 'pb-3' : separator,
                    )}
                >
                    {commit ? (
                        <>
                            <Head
                                commit={commit}
                                threadPosition={threadPosition}
                            />
                            <div className="mt-0.5">
                                <Content
                                    commit={commit}
                                    url={url}
                                    isFocal={isFocal}
                                />
                            </div>
                            {isFocal && (
                                <p className="mt-2 font-mono text-[13px] text-muted-foreground">
                                    {timeFormat.format(
                                        new Date(commit.created_at),
                                    )}{' '}
                                    ·{' '}
                                    {fullDateFormat.format(
                                        new Date(commit.created_at),
                                    )}
                                </p>
                            )}
                            {showsThreadLink && commit.continuation_id && (
                                <ThreadLink
                                    href={url}
                                    handle={commit.author.handle}
                                    isEmbedded
                                />
                            )}
                            <Actions
                                commit={commit}
                                hiddenReplies={hiddenReplies(node)}
                                url={url}
                            />
                        </>
                    ) : (
                        <TombstoneContent
                            node={node}
                            url={url}
                            endsWithDot={isLast}
                        />
                    )}
                </div>
                {repliesShown && (
                    <>
                        <RailSegment lane={isLast ? undefined : lane} />
                        <div className={cn('min-w-0 pr-4', separator)}>
                            <EmbedReplies
                                parent={node}
                                depth={0}
                                graphParent={isLast ? endId : node.id}
                                isOnTrunk={!isLast}
                                url={url}
                            />
                        </div>
                    </>
                )}
            </div>
        </article>
    );
}

// The replies the card shows under a commit, like ThreadReplies: each one branches off its parent's line, and the ones it
// cuts end with "Show the last N".
function EmbedReplies({
    parent,
    depth,
    graphParent,
    isOnTrunk = false,
    url,
}: {
    parent: EmbedNode;
    depth: number;
    graphParent: string;
    isOnTrunk?: boolean;
    url: string;
}) {
    const isFlattened = depth >= maxIndentDepth;
    const layout =
        depth === 0
            ? threadLayouts.root
            : isFlattened
              ? threadLayouts.flattened
              : threadLayouts.nested;
    const graph = isFlattened ? {} : { parent: graphParent };
    const hasMore = parent.more > 0;

    return (
        <div
            className={cn('relative', layout)}
            style={{ '--lane': laneOfNode(parent) } as CSSProperties}
        >
            {parent.replies.map((reply, index, replies) => (
                <EmbedReply
                    key={reply.id}
                    reply={reply}
                    parent={parent}
                    depth={depth + 1}
                    graph={graph}
                    links={
                        isFlattened ? null : (
                            <WithoutGraph>
                                <ReplyBranch
                                    isOnTrunk={isOnTrunk}
                                    hasNext={
                                        index < replies.length - 1 || hasMore
                                    }
                                />
                            </WithoutGraph>
                        )
                    }
                    url={url}
                />
            ))}
            {hasMore && (
                <div
                    className={cn(
                        'relative pt-1.5',
                        isFlattened || isOnTrunk
                            ? 'pl-(--indent)'
                            : // Centres the dot on the parent's column.
                              'ml-[calc(var(--spine)-12px)] sm:ml-[calc(var(--spine)-14px)]',
                    )}
                >
                    {!isFlattened && (
                        <WithoutGraph>
                            {isOnTrunk ? (
                                <ThreadElbow
                                    isOnTrunk
                                    className="h-5 w-[calc(var(--indent)-var(--spine)+4px)] sm:h-5.5"
                                />
                            ) : (
                                <ThreadColumn className="top-0 left-3 h-5 sm:left-3.5 sm:h-5.5" />
                            )}
                        </WithoutGraph>
                    )}
                    <MoreRepliesLink
                        remaining={parent.more}
                        href={url}
                        parentId={parent.id}
                        graph={graph}
                    />
                </div>
            )}
        </div>
    );
}

// A reply of the card, like ReplyItem: its avatar a node of the graph, its own replies under it.
function EmbedReply({
    reply,
    parent,
    depth,
    graph,
    links,
    url,
}: {
    reply: EmbedNode;
    parent: EmbedNode;
    depth: number;
    graph: ReplyGraph;
    links: ReactNode;
    url: string;
}) {
    const { commit } = reply;
    const lane = laneOfNode(reply);
    const hasReplies = reply.replies.length > 0;
    const nodeId = useGraphNodeId()(reply.id);
    const nodeRef = useGraphNode({
        id: nodeId,
        lane,
        dashed: true,
        dimmed: false,
        ring: commit === null ? 0 : replyRing,
        ...graph,
    });

    return (
        <article className="relative pt-2.5 pl-(--indent)">
            {links}
            <div className="flex gap-2 sm:gap-2.5">
                <div className="relative shrink-0">
                    {commit ? (
                        <a
                            ref={nodeRef}
                            href={showProfile.url(commit.author)}
                            className="block rounded-full"
                        >
                            <AuthorAvatar
                                author={commit.author}
                                className="size-6 ring-2 ring-(--lane) ring-offset-2 ring-offset-card sm:size-7"
                                style={{ '--lane': lane } as CSSProperties}
                            />
                        </a>
                    ) : (
                        <span
                            ref={nodeRef}
                            aria-hidden
                            className="block size-6 rounded-full border-2 border-dashed border-rail sm:size-7"
                        />
                    )}
                    {hasReplies && depth < maxIndentDepth && (
                        <ReplySpine lane={lane} className="top-7 sm:top-8" />
                    )}
                </div>
                <div className="min-w-0 flex-1">
                    {commit ? (
                        <>
                            <Head
                                commit={commit}
                                isQuote={commit.quoted_commit_id === parent.id}
                                small
                            />
                            <div className="mt-0.5">
                                <Content commit={commit} url={url} />
                            </div>
                            <Actions
                                commit={commit}
                                hiddenReplies={hiddenReplies(reply)}
                                url={url}
                            />
                        </>
                    ) : (
                        <TombstoneContent node={reply} url={url} small />
                    )}
                </div>
            </div>
            {hasReplies && (
                <EmbedReplies
                    parent={reply}
                    depth={depth}
                    graphParent={nodeId}
                    url={url}
                />
            )}
        </article>
    );
}

export default function Show({
    ancestors,
    focal,
    thread,
    options,
    commitUrl,
}: {
    ancestors: EmbedNode[];
    focal: EmbedNode;
    thread: EmbedNode[];
    options: Options;
    commitUrl: string;
}) {
    const card = useRef<HTMLDivElement>(null);

    // The page that frames the card sizes it from these messages, through embed.js.
    useEffect(() => {
        const element = card.current;

        if (!element || window.parent === window) {
            return;
        }

        const id =
            new URLSearchParams(window.location.search).get('signature') ?? '';
        const observer = new ResizeObserver(() =>
            window.parent.postMessage(
                {
                    type: 'devnull:embed:resize',
                    id,
                    height: element.offsetHeight,
                },
                '*',
            ),
        );

        observer.observe(element);

        return () => observer.disconnect();
    }, []);

    // Links open on /dev/null or their own site, never inside the frame.
    const openOutside = (event: MouseEvent) => {
        const link = (event.target as HTMLElement).closest('a');

        if (!link || !link.href || link.href.startsWith('javascript:')) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        window.open(link.href, '_blank', 'noopener');
    };

    // The thread reads top-down like the commit page: ancestors, the embedded commit, then the rest of its thread.
    const commits = [...ancestors, focal, ...thread];
    const steps = thread.length + 1;

    return (
        <div
            ref={card}
            onClickCapture={openOutside}
            className="embed-card overflow-hidden rounded-lg border bg-card text-foreground"
        >
            <style>{`
                .embed-card { container-type: inline-size; font-feature-settings: 'ss01', 'cv11'; }
                @container (max-width: 459px) { .embed-handle { display: none; } }
            `}</style>
            <div
                className="embed-flow overflow-y-auto overscroll-contain"
                style={{
                    maxHeight: options.maxHeight
                        ? `${options.maxHeight}px`
                        : undefined,
                }}
            >
                <EmbeddedContext value>
                    <GraphCanvas>
                        {commits.map((node, index) => {
                            const position = index - ancestors.length;

                            return (
                                <EmbedCommit
                                    key={node.id}
                                    node={node}
                                    previous={commits[index - 1]}
                                    isFocal={node === focal}
                                    isLast={index === commits.length - 1}
                                    threadPosition={
                                        thread.length > 0 && position >= 0
                                            ? {
                                                  position: position + 1,
                                                  size: steps,
                                                  isCapped: false,
                                              }
                                            : undefined
                                    }
                                    showsThreadLink={
                                        node === focal && !options.thread
                                    }
                                    url={commitUrl}
                                />
                            );
                        })}
                    </GraphCanvas>
                </EmbeddedContext>
            </div>
            <div className="flex flex-wrap items-center justify-between gap-3 border-t bg-background px-4 py-2.5">
                <a
                    href="https://dev-null.sh"
                    className="inline-flex items-baseline gap-1.5"
                >
                    <span className="inline-flex items-center gap-2 text-sm font-semibold tracking-tight">
                        <AppLogoIcon className="size-[18px] self-center fill-current" />
                        <span>
                            /dev
                            <span className="text-transparent [-webkit-text-stroke:max(1px,0.035em)_var(--lane-0)]">
                                /
                            </span>
                            null
                        </span>
                    </span>
                </a>
                <a
                    href={commitUrl}
                    className="text-[13px] font-medium text-primary hover:underline"
                >
                    View on /dev/null →
                </a>
            </div>
        </div>
    );
}
