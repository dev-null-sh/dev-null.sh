import { Deferred, Link, router, usePage } from '@inertiajs/react';
import { Ellipsis, EyeOff, RefreshCw } from 'lucide-react';
import { useRef, useState } from 'react';
import { FollowButton, useHasBlockWith } from '@/components/follow-button';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Skeleton } from '@/components/ui/skeleton';
import { UserAvatar } from '@/components/user-avatar';
import { UserPreview } from '@/components/user-preview';
import { useWhoToFollow } from '@/hooks/use-who-to-follow';
import { cn, laneColor } from '@/lib/utils';
import { edit } from '@/routes/appearance';
import { show } from '@/routes/user';
import type { SuggestedUser } from '@/types';

function reasonFor(user: SuggestedUser): string | null {
    if (user.followed_by_following_count > 0) {
        return `Followed by ${user.followed_by_following_count} you follow`;
    }

    if (user.followers_count > 0) {
        return user.followers_count === 1
            ? '1 follower'
            : `${user.followers_count} followers`;
    }

    return null;
}

export function SuggestedUserRow({
    user,
    className,
    buttonClassName,
}: {
    user: SuggestedUser;
    className?: string;
    buttonClassName?: string;
}) {
    const reason = reasonFor(user);
    const avatarRef = useRef<HTMLSpanElement>(null);
    const hasBlock = useHasBlockWith(user);
    const [isGone, setIsGone] = useState(false);

    if (isGone) {
        return null;
    }

    return (
        <li
            inert={hasBlock}
            className={cn(
                'grid grid-cols-1 transition-[grid-template-rows,opacity,margin] duration-300 ease-out',
                hasBlock
                    ? 'm-0 grid-rows-[0fr] border-0 opacity-0'
                    : 'grid-rows-[1fr]',
            )}
            onTransitionEnd={(event) =>
                hasBlock &&
                event.target === event.currentTarget &&
                event.propertyName === 'grid-template-rows' &&
                setIsGone(true)
            }
        >
            <div className={cn('min-h-0', { 'overflow-hidden': hasBlock })}>
                <div className={cn('flex items-center gap-3', className)}>
                    <UserPreview
                        user={user}
                        anchor={avatarRef}
                        className="flex min-w-0 flex-1"
                    >
                        <Link
                            href={show(user)}
                            className="group flex min-w-0 flex-1 items-center gap-3"
                        >
                            <span
                                ref={avatarRef}
                                className="inline-flex shrink-0"
                            >
                                <UserAvatar user={user} className="size-9" />
                            </span>
                            <span className="min-w-0">
                                <span className="block truncate text-sm font-semibold group-hover:underline">
                                    {user.name}
                                </span>
                                <span className="flex min-w-0 items-center gap-1.5 font-mono text-xs text-muted-foreground">
                                    <span
                                        aria-hidden
                                        className="size-1.5 shrink-0 rounded-full"
                                        style={{
                                            background: laneColor(user.lane),
                                        }}
                                    />
                                    <span className="truncate">
                                        @{user.username}
                                    </span>
                                </span>
                                {reason && (
                                    <span className="line-clamp-2 block text-xs text-muted-foreground">
                                        {reason}
                                    </span>
                                )}
                            </span>
                        </Link>
                    </UserPreview>
                    <FollowButton
                        user={user}
                        isFollowing={false}
                        size="sm"
                        className={cn('min-w-24 shrink-0', buttonClassName)}
                        only={['auth']}
                        preserveState
                        onSuccess={() => router.flushAll()}
                        data-test="suggested-follow-button"
                    />
                </div>
            </div>
        </li>
    );
}

function SuggestedUsersSkeleton({
    rowClassName,
    buttonClassName,
    hasWrappingReasons = false,
}: {
    rowClassName?: string;
    buttonClassName?: string;
    hasWrappingReasons?: boolean;
}) {
    return (
        <>
            {[0, 1, 2].map((index) => (
                <li
                    key={index}
                    aria-hidden
                    className={cn('flex items-center gap-3', rowClassName)}
                >
                    <Skeleton className="size-9 shrink-0 rounded-full" />
                    <span className="flex-1">
                        <Skeleton className="my-[3px] h-3.5 w-28" />
                        <Skeleton className="my-0.5 h-3 w-20" />
                        <Skeleton className="my-0.5 h-3 w-32" />
                        {hasWrappingReasons && (
                            <Skeleton className="my-0.5 h-3 w-16" />
                        )}
                    </span>
                    <Skeleton
                        className={cn('h-8 w-24 shrink-0', buttonClassName)}
                    />
                </li>
            ))}
        </>
    );
}

function WhoToFollowRefreshButton() {
    const { isRefreshing, refreshSuggestedUsers } = useWhoToFollow();

    return (
        <Button
            variant="ghost"
            size="icon"
            className="size-7 text-muted-foreground"
            onClick={refreshSuggestedUsers}
            aria-disabled={isRefreshing}
            aria-label="Suggest other people"
            title="Suggest other people"
            data-test="who-to-follow-refresh"
        >
            <RefreshCw
                className={cn(
                    isRefreshing && 'animate-spin motion-reduce:animate-none',
                )}
            />
        </Button>
    );
}

function WhoToFollowActions({
    hasSuggestions,
    onHide,
}: {
    hasSuggestions: boolean;
    onHide: () => void;
}) {
    return (
        <div className="flex items-center gap-1">
            {hasSuggestions && <WhoToFollowRefreshButton />}
            <WhoToFollowMenu onHide={onHide} />
        </div>
    );
}

function WhoToFollowMenu({ onHide }: { onHide: () => void }) {
    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="size-7 text-muted-foreground"
                    aria-label="Who to follow options"
                    data-test="who-to-follow-menu"
                >
                    <Ellipsis />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-52">
                <DropdownMenuItem onSelect={onHide}>
                    <EyeOff />
                    <span>
                        Don't show again
                        <span className="block font-mono text-xs text-muted-foreground">
                            &gt; /dev/null
                        </span>
                    </span>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

/**
 * Hiding only writes the cookie, so the block stays put with an undo until the next visit leaves it out.
 */
function useHideWhoToFollow() {
    const { showsWhoToFollow, suggestedUsers } = usePage().props;
    const { rememberShowsWhoToFollow } = useWhoToFollow();
    const [isHidden, setIsHidden] = useState(false);

    const toggle = (hides: boolean) => {
        rememberShowsWhoToFollow(!hides);
        setIsHidden(hides);
    };

    return {
        isVisible: showsWhoToFollow && suggestedUsers?.length !== 0,
        isHidden,
        hide: () => toggle(true),
        undo: () => toggle(false),
        suggestedUsers,
    };
}

function WhoToFollowHidden({ onUndo }: { onUndo: () => void }) {
    return (
        <div>
            <p className="font-mono text-[13px] text-muted-foreground">
                $ who --should-i-follow{' '}
                <span className="whitespace-nowrap text-foreground">
                    &gt; /dev/null
                </span>
            </p>
            <div className="mt-1 flex items-center justify-between gap-3">
                <p className="min-w-0 text-xs text-muted-foreground">
                    Bring it back in{' '}
                    <Link href={edit()} className="underline">
                        Settings
                    </Link>
                    .
                </p>
                <Button
                    variant="outline"
                    size="sm"
                    className="shrink-0"
                    onClick={onUndo}
                    data-test="who-to-follow-undo"
                >
                    Undo
                </Button>
            </div>
        </div>
    );
}

export function WhoToFollow() {
    const { isVisible, isHidden, hide, undo, suggestedUsers } =
        useHideWhoToFollow();

    if (!isVisible) {
        return null;
    }

    return (
        <section
            aria-labelledby="aside-who-to-follow"
            className="border-t pt-6"
        >
            <div className="mb-3 flex items-center justify-between gap-2">
                <h2 id="aside-who-to-follow" className="text-sm font-semibold">
                    Who to follow
                </h2>
                {!isHidden && (
                    <WhoToFollowActions
                        hasSuggestions={suggestedUsers !== undefined}
                        onHide={hide}
                    />
                )}
            </div>
            {isHidden ? (
                <WhoToFollowHidden onUndo={undo} />
            ) : (
                <ul className="space-y-4">
                    <Deferred
                        data="suggestedUsers"
                        fallback={<SuggestedUsersSkeleton hasWrappingReasons />}
                    >
                        {suggestedUsers?.map((user) => (
                            <SuggestedUserRow key={user.id} user={user} />
                        ))}
                    </Deferred>
                </ul>
            )}
        </section>
    );
}

/**
 * The aside is hidden below the xl breakpoint, so smaller screens get the same suggestions inside the log.
 */
export function WhoToFollowInLog() {
    const { isVisible, isHidden, hide, undo, suggestedUsers } =
        useHideWhoToFollow();

    if (!isVisible) {
        return null;
    }

    return (
        <section aria-labelledby="log-who-to-follow" className="flex xl:hidden">
            <div aria-hidden className="w-8 shrink-0 sm:w-9" />
            <div className="min-w-0 flex-1 border-b py-4 pr-4">
                <div
                    className={cn(
                        'mb-2 flex items-center justify-between gap-2',
                        { 'sr-only': isHidden },
                    )}
                >
                    <h2
                        id="log-who-to-follow"
                        className="font-mono text-[13px] text-muted-foreground"
                    >
                        $ who --should-i-follow
                    </h2>
                    {!isHidden && (
                        <WhoToFollowActions
                            hasSuggestions={suggestedUsers !== undefined}
                            onHide={hide}
                        />
                    )}
                </div>
                {isHidden ? (
                    <WhoToFollowHidden onUndo={undo} />
                ) : (
                    <ul className="divide-y rounded-lg border">
                        <Deferred
                            data="suggestedUsers"
                            fallback={
                                <SuggestedUsersSkeleton
                                    rowClassName="p-3"
                                    buttonClassName="w-[90px]"
                                />
                            }
                        >
                            {suggestedUsers?.map((user) => (
                                <SuggestedUserRow
                                    key={user.id}
                                    user={user}
                                    className="p-3"
                                    buttonClassName="min-w-[90px] px-2"
                                />
                            ))}
                        </Deferred>
                    </ul>
                )}
            </div>
        </section>
    );
}
