import { Link } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';
import { CornerDownRight } from 'lucide-react';
import type { CSSProperties } from 'react';
import { useCallback, useRef } from 'react';
import CommitController from '@/actions/App/Http/Controllers/CommitController';
import { AuthorAvatar } from '@/components/author-avatar';
import { FoldedCommitBody } from '@/components/commit-body';
import { CommitImages } from '@/components/commit-images';
import { CommitPastelButton } from '@/components/commit-pastel-button';
import { CommitPoll } from '@/components/commit-poll';
import { CommitQuoteButton } from '@/components/commit-quote-button';
import { CommitStarButton } from '@/components/commit-star-button';
import { CommitStatusBadge } from '@/components/commit-status-badge';
import {
    ShowThreadLink,
    ThreadActions,
    ThreadReplies,
    ThreadSpine,
    useThread,
} from '@/components/commit-thread';
import { useCommitTranslation } from '@/components/commit-translation';
import {
    GraphScope,
    useGraphNode,
    useGraphNodeId,
} from '@/components/graph-canvas';
import { RailDot, railDotRing } from '@/components/graph-rail';
import { QuotedCommit } from '@/components/quoted-commit';
import { TimestampTooltip } from '@/components/timestamp-tooltip';
import { UserPreview } from '@/components/user-preview';
import { useDateTime } from '@/hooks/use-date-time';
import { useIsCommitDeleted } from '@/hooks/use-deleted-resources';
import { useNow } from '@/hooks/use-now';
import { formatCount } from '@/lib/numbers';
import { cn, isDeleted, laneOf, shortHash } from '@/lib/utils';
import { show } from '@/routes/user';
import type { Commit } from '@/types';

/** The avatar's ring reaches this far beyond its picture, like a reply's. */
const avatarRing = 4;

/**
 * The most starred commits of a link, nested under it like replies: the link stands where a commit's author would, and
 * its lane branches off to each of them. They read like commits of the log, with a smaller avatar. The commit card
 * would bring its large one, and a reply belongs to the commit above it, so this page draws its own.
 */
export function LinkTopicCommits({
    commits,
    lane,
    graphParent,
    more,
}: {
    commits: Commit[];
    lane: string;
    /** The node the commits branch off. */
    graphParent: string;
    /** The link's other commits, when it has more than the ones listed. */
    more?: { count: number; href: NonNullable<InertiaLinkProps['href']> };
}) {
    return (
        <div
            className="relative [--indent:0px] [--spine:-32px] sm:[--spine:-36px]"
            style={{ '--lane': lane } as CSSProperties}
            data-test="topic-commits"
        >
            {commits.map((commit) => (
                // A commit can also be listed as the reply of another one, whose node then has an id of its own.
                <GraphScope key={commit.id} scope={commit.id}>
                    <LinkTopicCommit
                        commit={commit}
                        graphParent={graphParent}
                    />
                </GraphScope>
            ))}
            {more && (
                <div className="relative ml-[calc(var(--spine)-12px)] sm:ml-[calc(var(--spine)-14px)]">
                    <MoreCommitsLink {...more} graphParent={graphParent} />
                </div>
            )}
        </div>
    );
}

function LinkTopicCommit({
    commit,
    graphParent,
}: {
    commit: Commit;
    graphParent: string;
}) {
    const { formatRelativeTime } = useDateTime();
    const now = useNow();
    const thread = useThread(commit);
    const { bodyRef, ...translation } = useCommitTranslation(commit);
    const wasDeleted = useIsCommitDeleted(commit.id);
    const nodeRef = useGraphNode({
        id: useGraphNodeId()(commit.id),
        lane: laneOf(commit),
        parent: graphParent,
        dashed: true,
        ring: avatarRing,
    });
    const avatarRef = useRef<HTMLAnchorElement>(null);
    // A stable ref, so the node stays registered across renders.
    const avatarNodeRef = useCallback(
        (element: HTMLAnchorElement | null) => {
            avatarRef.current = element;
            nodeRef(element);
        },
        [nodeRef],
    );
    const permalink = CommitController.show({
        profile: commit.author,
        commit,
    });

    return (
        <article
            className={cn(
                // With the next commit's top padding, as far apart as commits of the log.
                'relative pt-2.5 pb-3.5 pl-(--indent) last:pb-0',
                wasDeleted ? 'cursor-default' : 'cursor-pointer',
            )}
            data-reply
            data-permalink={wasDeleted ? undefined : permalink.url}
            data-test="reply"
        >
            <div
                className={cn(
                    'relative isolate flex gap-2 before:absolute before:-inset-x-2 before:-top-1.5 before:-bottom-1.5 before:-z-10 before:rounded-lg before:transition-colors sm:gap-2.5',
                    { 'reply-hover:before:bg-accent': !wasDeleted },
                )}
            >
                <div className="relative shrink-0">
                    <UserPreview user={commit.author} anchor={avatarRef}>
                        <Link
                            prefetch
                            ref={avatarNodeRef}
                            href={show(commit.author)}
                            className="block rounded-full"
                        >
                            <AuthorAvatar
                                author={commit.author}
                                className="size-6 ring-2 ring-(--lane) ring-offset-2 ring-offset-card sm:size-7"
                                style={
                                    {
                                        '--lane': laneOf(commit),
                                    } as CSSProperties
                                }
                            />
                        </Link>
                    </UserPreview>
                    <ThreadSpine thread={thread} className="top-7 sm:top-8" />
                </div>

                <div
                    inert={wasDeleted}
                    className={cn('min-w-0 flex-1', {
                        'opacity-50': wasDeleted,
                    })}
                >
                    <div className="flex items-baseline gap-1.5 text-[15px]">
                        <UserPreview
                            user={commit.author}
                            anchor={avatarRef}
                            className="flex min-w-0 items-baseline gap-1.5"
                        >
                            <Link
                                prefetch
                                href={show(commit.author)}
                                className="truncate font-semibold hover:underline"
                            >
                                {commit.author.name}
                            </Link>
                            <Link
                                prefetch
                                href={show(commit.author)}
                                tabIndex={-1}
                                className="shrink-4 truncate font-mono text-[13px] text-muted-foreground"
                            >
                                @{commit.author.handle}
                            </Link>
                        </UserPreview>
                        <span aria-hidden className="text-muted-foreground">
                            ·
                        </span>
                        <TimestampTooltip
                            date={commit.created_at}
                            author={
                                commit.author.type === 'user'
                                    ? commit.author
                                    : undefined
                            }
                        >
                            <Link
                                prefetch
                                href={permalink}
                                className="shrink-0 font-mono text-[13px] text-muted-foreground hover:underline"
                            >
                                <time dateTime={commit.created_at}>
                                    {formatRelativeTime(commit.created_at, now)}
                                </time>
                            </Link>
                        </TimestampTooltip>
                        <CommitStatusBadge commit={commit} />
                        <Link
                            prefetch
                            href={permalink}
                            tabIndex={-1}
                            className="ml-auto hidden shrink-0 self-center font-mono text-[13px] text-muted-foreground transition-opacity hover:text-foreground sm:inline"
                            title={`Commit ${commit.id}`}
                        >
                            {shortHash(commit.id)}
                        </Link>
                    </div>

                    {commit.parent && (
                        <p className="flex min-w-0 items-center gap-1 font-mono text-xs text-muted-foreground">
                            <CornerDownRight className="size-3 shrink-0" />
                            <span className="truncate">
                                replying to{' '}
                                {isDeleted(commit.parent)
                                    ? '[deleted]'
                                    : `@${commit.parent.author.handle}`}
                            </span>
                        </p>
                    )}

                    <div ref={bodyRef} style={translation.bodyStyle}>
                        <FoldedCommitBody
                            segments={translation.segments}
                            mentions={commit.mentions}
                            organizationMentions={commit.organization_mentions}
                            previewSegments={translation.previewSegments}
                            className="mt-0.5 text-[15px]"
                        />
                    </div>
                    {translation.notice}
                    <CommitImages commit={commit} />
                    {commit.poll && (
                        <CommitPoll
                            key={`${commit.poll.id}-${commit.poll.has_voted}-${commit.poll.total_votes}`}
                            commit={commit}
                            poll={commit.poll}
                        />
                    )}
                    {commit.quoted_commit_id !== null && (
                        <QuotedCommit commit={commit.quoted_commit ?? null} />
                    )}
                    <ShowThreadLink thread={thread} />

                    <ThreadActions
                        thread={thread}
                        isReply
                        actions={
                            <>
                                <CommitStarButton
                                    commit={commit}
                                    keyShortcut="s"
                                    className="px-2 py-0.5"
                                />
                                <CommitPastelButton
                                    commit={commit}
                                    className="px-2 py-0.5"
                                />
                                <CommitQuoteButton commit={commit} />
                                {translation.button}
                            </>
                        }
                    />
                </div>
            </div>

            <ThreadReplies thread={thread} depth={1} />
        </article>
    );
}

function MoreCommitsLink({
    count,
    href,
    graphParent,
}: {
    count: number;
    href: NonNullable<InertiaLinkProps['href']>;
    graphParent: string;
}) {
    const dotRef = useGraphNode({
        id: `${graphParent}:more`,
        lane: 'var(--muted-foreground)',
        parent: graphParent,
        dashed: true,
        ring: railDotRing,
    });

    return (
        <Link
            prefetch
            href={href}
            className="-ml-2 flex w-fit max-w-full min-w-0 items-center gap-2 rounded-md px-2 py-0.5 text-[13px] text-muted-foreground transition-colors hover:bg-accent hover:text-foreground sm:gap-2.5"
        >
            <span
                aria-hidden
                className="relative flex size-6 shrink-0 items-center justify-center [--lane:var(--muted-foreground)] sm:size-7"
            >
                <span ref={dotRef} className="flex">
                    <RailDot />
                </span>
            </span>
            <span className="truncate tabular-nums">
                Show all {formatCount(count)}
            </span>
        </Link>
    );
}
