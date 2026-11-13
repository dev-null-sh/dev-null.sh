import { Link } from '@inertiajs/react';
import type { ComponentProps, ReactNode } from 'react';
import { ThreadPositionBadge } from '@/components/thread-position-badge';
import type { ThreadPosition } from '@/components/thread-position-badge';
import { cn, shortHash } from '@/lib/utils';
import type { Commit } from '@/types';

// Every anchor prop goes through, a tooltip trigger's ref and handlers included.
type HeaderLinkProps = ComponentProps<'a'> & {
    isEmbedded: boolean;
    href: string;
    'data-test'?: string;
};

function HeaderLink({ isEmbedded, children, ...props }: HeaderLinkProps) {
    return isEmbedded ? (
        <a {...props}>{children}</a>
    ) : (
        // Inertia types its click handler on any element, an anchor's being narrower.
        <Link prefetch {...(props as ComponentProps<typeof Link>)}>
            {children}
        </Link>
    );
}

/**
 * The header of a commit or a reply: its author's name and handle, the date linking to the commit, its thread badge,
 * then whatever the caller adds (status badge, delete button) and, for a commit, its hash on the right.
 *
 * In a commit embed, on another site, links are plain ones to /dev/null, the hash always shows, and the handle hides
 * on narrow cards rather than on narrow screens; the app wraps the author and the date in their previews instead.
 */
export function CommitHeader({
    commit,
    href,
    profileHref,
    date,
    size = 'commit',
    threadPosition,
    isQuote = false,
    hasHash = false,
    isFocused = false,
    isEmbedded = false,
    wrapAuthor = (author, className) => (
        <span className={className}>{author}</span>
    ),
    wrapDate = (link) => link,
    children,
}: {
    commit: Pick<Commit, 'id' | 'created_at'> & {
        author: Pick<Commit['author'], 'name' | 'handle'>;
    };
    href: string;
    profileHref: string;
    date: string;
    size?: 'commit' | 'reply';
    threadPosition?: ThreadPosition;
    isQuote?: boolean;
    hasHash?: boolean;
    isFocused?: boolean;
    isEmbedded?: boolean;
    wrapAuthor?: (author: ReactNode, className: string) => ReactNode;
    wrapDate?: (link: ReactNode) => ReactNode;
    children?: ReactNode;
}) {
    const isReply = size === 'reply';

    return (
        <div
            className={cn(
                'flex items-baseline gap-1.5',
                isReply ? 'text-sm' : 'text-[15px]',
            )}
        >
            {wrapAuthor(
                <>
                    <HeaderLink
                        isEmbedded={isEmbedded}
                        href={profileHref}
                        className="truncate font-semibold hover:underline"
                    >
                        {commit.author.name}
                    </HeaderLink>
                    <HeaderLink
                        isEmbedded={isEmbedded}
                        href={profileHref}
                        tabIndex={-1}
                        className={cn(
                            'truncate font-mono text-muted-foreground',
                            isReply ? 'text-xs' : 'shrink-4 text-[13px]',
                            isReply &&
                                (isEmbedded
                                    ? 'embed-handle shrink-4'
                                    : 'hidden sm:inline'),
                        )}
                    >
                        @{commit.author.handle}
                    </HeaderLink>
                </>,
                'flex min-w-0 items-baseline gap-1.5',
            )}
            {isQuote && (
                <HeaderLink
                    isEmbedded={isEmbedded}
                    href={href}
                    className="shrink-0 font-mono text-xs text-primary hover:underline"
                    data-test="quote-reply-label"
                >
                    quoted
                </HeaderLink>
            )}
            <span aria-hidden className="text-muted-foreground">
                ·
            </span>
            {wrapDate(
                <HeaderLink
                    isEmbedded={isEmbedded}
                    href={href}
                    className={cn(
                        'shrink-0 font-mono text-muted-foreground hover:underline',
                        isReply ? 'text-xs' : 'text-[13px]',
                    )}
                    data-test={isReply ? undefined : 'commit-permalink'}
                >
                    <time dateTime={commit.created_at}>{date}</time>
                </HeaderLink>,
            )}
            <ThreadPositionBadge threadPosition={threadPosition} />
            {children}
            {hasHash && (
                <span
                    data-commit-hash
                    className="relative ml-auto flex shrink-0 items-center self-center"
                >
                    <HeaderLink
                        isEmbedded={isEmbedded}
                        href={href}
                        tabIndex={-1}
                        className={cn(
                            'font-mono text-[13px] text-muted-foreground transition-opacity hover:text-foreground',
                            !isEmbedded && 'hidden sm:inline',
                            !isEmbedded && isFocused && 'sm:hidden',
                        )}
                        title={`Commit ${commit.id}`}
                    >
                        {shortHash(commit.id)}
                    </HeaderLink>
                </span>
            )}
        </div>
    );
}
