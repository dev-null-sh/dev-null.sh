import { Link } from '@inertiajs/react';
import { ArrowUpRight } from 'lucide-react';
import type { ReactNode, Ref } from 'react';
import { UserPreview } from '@/components/user-preview';
import type { ChangelogAuthor, ChangelogCommit } from '@/lib/changelog';
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
    if (author.handle === null) {
        return (
            <span title={`@${author.login}`} className={className}>
                {children}
            </span>
        );
    }

    return (
        <UserPreview user={{ handle: author.handle }} className="flex min-w-0">
            <Link
                prefetch
                href={show({ handle: author.handle })}
                onClick={onClick}
                aria-label={
                    author.login === author.handle
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

/**
 * The pull request a commit came from, or the commit itself when it was pushed straight to production.
 */
function CommitLink({
    commit,
    ref,
}: {
    commit: ChangelogCommit;
    ref?: Ref<HTMLAnchorElement>;
}) {
    return (
        <a
            ref={ref}
            data-commit-link
            href={commit.pull_request?.url ?? commit.url}
            target="_blank"
            rel="noopener noreferrer"
            aria-label={
                commit.pull_request === null
                    ? `Commit ${commit.sha.slice(0, 7)} on GitHub (opens in a new tab)`
                    : `Pull request #${commit.pull_request.number} on GitHub (opens in a new tab)`
            }
            className="inline-flex shrink-0 items-center gap-0.5 rounded-sm font-mono text-xs text-muted-foreground tabular-nums transition-colors hover:text-primary"
        >
            {commit.pull_request === null
                ? commit.sha.slice(0, 7)
                : `#${commit.pull_request.number}`}
            <ArrowUpRight aria-hidden className="size-3" />
        </a>
    );
}

/**
 * The commits on one lane, each dot and segment in the color of whoever made it. A lane can carry on from the commit
 * before it (previousAuthor) and into what comes after it (continues), so a split list still reads as one line.
 */
export function ChangelogLane({
    commits,
    isCompact = false,
    previousAuthor,
    continues = false,
    firstLinkRef,
    timeZone,
    onNavigate,
}: {
    commits: ChangelogCommit[];
    isCompact?: boolean;
    previousAuthor?: ChangelogAuthor;
    continues?: boolean;
    firstLinkRef?: Ref<HTMLAnchorElement>;
    timeZone?: string;
    onNavigate?: () => void;
}) {
    return (
        <ol>
            {commits.map((commit, index) => {
                const color = changelogLaneColor(commit.author);
                const before =
                    index === 0 ? previousAuthor : commits[index - 1].author;
                const hasTail = continues || index < commits.length - 1;

                return (
                    <li
                        key={commit.sha}
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
                                    title={commit.title}
                                    className={cn(
                                        'min-w-0 flex-1 truncate',
                                        !isCompact && 'font-medium',
                                    )}
                                >
                                    {commit.title}
                                </span>
                                {isCompact && commit.breaking && (
                                    <BreakingBadge />
                                )}
                                <CommitLink
                                    commit={commit}
                                    ref={index === 0 ? firstLinkRef : undefined}
                                />
                            </div>
                            {!isCompact && (
                                <div className="mt-0.5 flex min-w-0 items-center gap-1.5 text-xs text-muted-foreground tabular-nums">
                                    <ChangelogAuthorLink
                                        author={commit.author}
                                        onClick={onNavigate}
                                        className="flex min-w-0 items-center gap-1.5 rounded-sm"
                                    >
                                        <ChangelogAvatar
                                            avatar={commit.author.avatar}
                                            size={16}
                                        />
                                        <span className="max-w-36 truncate">
                                            @{commit.author.login}
                                        </span>
                                    </ChangelogAuthorLink>
                                    <span aria-hidden>·</span>
                                    <time
                                        dateTime={commit.committed_at}
                                        title={formatFullDateTime(
                                            commit.committed_at,
                                            timeZone,
                                        )}
                                        className="shrink-0"
                                    >
                                        {formatRelativeTime(
                                            commit.committed_at,
                                        )}
                                    </time>
                                    {commit.pull_request !== null && (
                                        <>
                                            <span aria-hidden>·</span>
                                            <a
                                                href={commit.url}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                aria-label={`Commit ${commit.sha.slice(0, 7)} on GitHub (opens in a new tab)`}
                                                className="shrink-0 rounded-sm font-mono transition-colors hover:text-foreground"
                                            >
                                                {commit.sha.slice(0, 7)}
                                            </a>
                                        </>
                                    )}
                                    {commit.type !== null && (
                                        <>
                                            <span aria-hidden>·</span>
                                            <span className="shrink-0 font-mono">
                                                {commit.type}
                                            </span>
                                        </>
                                    )}
                                    {commit.breaking && <BreakingBadge />}
                                </div>
                            )}
                        </div>
                    </li>
                );
            })}
        </ol>
    );
}
