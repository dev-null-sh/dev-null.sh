import { Link } from '@inertiajs/react';
import { ArrowUpRight } from 'lucide-react';
import type { ReactNode, Ref } from 'react';
import { UserPreview } from '@/components/user-preview';
import type { ChangelogAuthor, ChangelogPullRequest } from '@/lib/changelog';
import { authorLane } from '@/lib/changelog';
import {
    cn,
    formatFullDateTime,
    formatRelativeTime,
    laneColor,
} from '@/lib/utils';
import { show } from '@/routes/user';

export function changelogLaneColor(author: ChangelogAuthor): string {
    return laneColor(authorLane(author));
}

function avatarUrl(avatar: string, size: number): string {
    const url = new URL(avatar);
    url.searchParams.set('s', String(size * 2));

    return url.toString();
}

export function ChangelogAvatar({
    avatar,
    size,
    title,
    className,
}: {
    avatar: string;
    size: number;
    title?: string;
    className?: string;
}) {
    return (
        <img
            src={avatarUrl(avatar, size)}
            alt=""
            title={title}
            width={size}
            height={size}
            loading="lazy"
            className={cn('shrink-0 rounded-full bg-muted', className)}
        />
    );
}

/**
 * An author's /dev/null profile, with their profile card on hover, or just the author when they aren't on /dev/null.
 */
export function ChangelogAuthorLink({
    author,
    className,
    onClick,
    children,
}: {
    author: ChangelogAuthor;
    className?: string;
    onClick?: () => void;
    children: ReactNode;
}) {
    if (author.username === null) {
        return (
            <span title={`@${author.login}`} className={className}>
                {children}
            </span>
        );
    }

    return (
        <UserPreview
            user={{ username: author.username }}
            className="flex min-w-0"
        >
            <Link
                href={show({ username: author.username })}
                onClick={onClick}
                aria-label={
                    author.login === author.username
                        ? `${author.mention} on /dev/null`
                        : `@${author.login}, ${author.mention} on /dev/null`
                }
                className={cn(
                    className,
                    'transition-colors hover:text-foreground',
                )}
            >
                {children}
            </Link>
        </UserPreview>
    );
}

export function BreakingBadge() {
    return (
        <span className="shrink-0 rounded border border-destructive/40 px-1.5 text-[11px] leading-4 font-medium text-destructive">
            breaking
        </span>
    );
}

function PullRequestLink({
    pullRequest,
    ref,
}: {
    pullRequest: ChangelogPullRequest;
    ref?: Ref<HTMLAnchorElement>;
}) {
    return (
        <a
            ref={ref}
            href={pullRequest.url}
            target="_blank"
            rel="noopener noreferrer"
            aria-label={`Pull request #${pullRequest.number} on GitHub (opens in a new tab)`}
            className="inline-flex shrink-0 items-center gap-0.5 rounded-sm font-mono text-xs text-muted-foreground tabular-nums transition-colors hover:text-primary"
        >
            #{pullRequest.number}
            <ArrowUpRight aria-hidden className="size-3" />
        </a>
    );
}

/**
 * The merges on one lane, each dot and segment in the color of whoever merged it. A lane can carry on from the merge
 * before it (previousAuthor) and into what comes after it (continues), so a split list still reads as one line.
 */
export function ChangelogLane({
    pullRequests,
    isCompact = false,
    previousAuthor,
    continues = false,
    firstLinkRef,
    timeZone,
    onNavigate,
}: {
    pullRequests: ChangelogPullRequest[];
    isCompact?: boolean;
    previousAuthor?: ChangelogAuthor;
    continues?: boolean;
    firstLinkRef?: Ref<HTMLAnchorElement>;
    timeZone?: string;
    onNavigate?: () => void;
}) {
    return (
        <ol>
            {pullRequests.map((pullRequest, index) => {
                const color = changelogLaneColor(pullRequest.author);
                const before =
                    index === 0
                        ? previousAuthor
                        : pullRequests[index - 1].author;
                const hasTail = continues || index < pullRequests.length - 1;

                return (
                    <li
                        key={pullRequest.number}
                        className={cn('flex', isCompact ? 'gap-2' : 'gap-2.5')}
                    >
                        <span
                            aria-hidden
                            className={cn(
                                'relative shrink-0',
                                isCompact ? 'w-4' : 'w-6',
                            )}
                        >
                            {before !== undefined && (
                                <span
                                    className="absolute top-0 left-1/2 h-2.5 w-0.5 -translate-x-1/2"
                                    style={{
                                        background: changelogLaneColor(before),
                                    }}
                                />
                            )}
                            {hasTail && (
                                <span
                                    className="absolute top-2.5 bottom-0 left-1/2 w-0.5 -translate-x-1/2"
                                    style={{ background: color }}
                                />
                            )}
                            <span
                                className={cn(
                                    'absolute top-2.5 left-1/2 -translate-1/2 rounded-full border-2 bg-popover',
                                    isCompact ? 'size-2' : 'size-2.5',
                                )}
                                style={{ borderColor: color }}
                            />
                        </span>
                        <div
                            className={cn(
                                'min-w-0 flex-1',
                                isCompact ? 'pb-2.5' : 'pb-3',
                            )}
                        >
                            <div className="flex items-center gap-2.5 text-sm leading-5">
                                <span
                                    title={pullRequest.title}
                                    className={cn(
                                        'min-w-0 flex-1 truncate',
                                        !isCompact && 'font-medium',
                                    )}
                                >
                                    {pullRequest.title}
                                </span>
                                {isCompact && pullRequest.breaking && (
                                    <BreakingBadge />
                                )}
                                <PullRequestLink
                                    pullRequest={pullRequest}
                                    ref={index === 0 ? firstLinkRef : undefined}
                                />
                            </div>
                            {!isCompact && (
                                <div className="mt-0.5 flex min-w-0 items-center gap-1.5 text-xs text-muted-foreground tabular-nums">
                                    <ChangelogAuthorLink
                                        author={pullRequest.author}
                                        onClick={onNavigate}
                                        className="flex min-w-0 items-center gap-1.5 rounded-sm"
                                    >
                                        <ChangelogAvatar
                                            avatar={pullRequest.author.avatar}
                                            size={16}
                                        />
                                        <span className="max-w-36 truncate">
                                            @{pullRequest.author.login}
                                        </span>
                                    </ChangelogAuthorLink>
                                    <span aria-hidden>·</span>
                                    <time
                                        dateTime={pullRequest.merged_at}
                                        title={formatFullDateTime(
                                            pullRequest.merged_at,
                                            timeZone,
                                        )}
                                        className="shrink-0"
                                    >
                                        {formatRelativeTime(
                                            pullRequest.merged_at,
                                        )}
                                    </time>
                                    <span aria-hidden>·</span>
                                    <a
                                        href={pullRequest.commit_url}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        aria-label={`Commit ${pullRequest.sha.slice(0, 7)} on GitHub (opens in a new tab)`}
                                        className="shrink-0 rounded-sm font-mono transition-colors hover:text-foreground"
                                    >
                                        {pullRequest.sha.slice(0, 7)}
                                    </a>
                                    {pullRequest.type !== null && (
                                        <>
                                            <span aria-hidden>·</span>
                                            <span className="shrink-0 font-mono">
                                                {pullRequest.type}
                                            </span>
                                        </>
                                    )}
                                    {pullRequest.breaking && <BreakingBadge />}
                                </div>
                            )}
                        </div>
                    </li>
                );
            })}
        </ol>
    );
}
