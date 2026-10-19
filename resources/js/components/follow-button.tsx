import { Link, router, usePage } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';
import type { ComponentProps } from 'react';
import { useState } from 'react';
import { toast } from 'sonner';
import UserFollowController from '@/actions/App/Http/Controllers/UserFollowController';
import { Button } from '@/components/ui/button';
import {
    peekSharedState,
    refreshSharedState,
    useSharedState,
} from '@/hooks/use-shared-state';
import { signIn } from '@/lib/auth';
import { toastWhenThrottled } from '@/lib/throttle';
import { cn } from '@/lib/utils';
import { show as userPreview } from '@/routes/user-preview';
import type { User } from '@/types';

/**
 * The pointer is still on the button right after a follow, so it keeps saying "Following" until the pointer leaves instead of flipping to a red "Unfollow".
 */
export function useFollowHover() {
    const [isJustToggled, setIsJustToggled] = useState(false);

    return {
        isJustToggled,
        markToggled: () => setIsJustToggled(true),
        onMouseLeave: () => setIsJustToggled(false),
    };
}

const isFollowingKey = (user: Pick<User, 'id'>): string =>
    `is_following:${user.id}`;

/**
 * Every follow control shares whether you follow someone, so a follow from a profile or a preview also shows in "Who to follow".
 */
export function useIsFollowing(
    user: Pick<User, 'id'>,
    isFollowing: boolean,
): [boolean, (isFollowing: boolean) => void] {
    return useSharedState(isFollowingKey(user), isFollowing);
}

const hasBlockKey = (user: Pick<User, 'id'>): string => `has_block:${user.id}`;

/**
 * Whether this tab has seen a block between you, either way: one you made, or a follow the server refused.
 */
export function useHasBlockWith(user: Pick<User, 'id'>): boolean {
    const [hasBlock] = useSharedState(hasBlockKey(user), false);

    return hasBlock;
}

/**
 * A block works both ways and ends the follow.
 */
export function rememberBlock(user: Pick<User, 'id'>): void {
    refreshSharedState(
        new Map([
            [isFollowingKey(user), false],
            [hasBlockKey(user), true],
        ]),
    );
}

/**
 * The server only refuses a follow across a block, which a suggestion can't know about until it tries, so it toasts instead of swapping in the 403 page.
 */
export function onFollowHttpException(
    user: Pick<User, 'id' | 'username'>,
    response: { status: number },
): boolean {
    if (response.status !== 403) {
        return !toastWhenThrottled(response);
    }

    rememberBlock(user);
    toast.error(`You can't follow @${user.username}.`);

    return false;
}

/**
 * Uses a follow control that already ran in this tab when it can, otherwise asks the preview endpoint once.
 */
export async function resolveIsFollowing(
    user: Pick<User, 'id' | 'username'>,
): Promise<boolean | null> {
    const known = peekSharedState<boolean>(isFollowingKey(user));

    if (known !== undefined) {
        return known;
    }

    try {
        const response = await fetch(userPreview(user).url, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        // Another follow control may have confirmed state while this preview was in flight.
        const writtenWhilePending = peekSharedState<boolean>(
            isFollowingKey(user),
        );

        if (writtenWhilePending !== undefined) {
            return writtenWhilePending;
        }

        if (!response.ok) {
            toastWhenThrottled(response);

            return null;
        }

        const data = (await response.json()) as {
            isFollowing: boolean;
            isUnavailable: boolean;
        };

        if (data.isUnavailable) {
            rememberBlock(user);

            return null;
        }

        refreshSharedState(new Map([[isFollowingKey(user), data.isFollowing]]));

        return data.isFollowing;
    } catch {
        toast.error(`Could not check @${user.username}. Try again.`);

        return null;
    }
}

/**
 * Follows or unfollows after resolving the current state, keeping every follow control in sync.
 */
export function toggleFollow(
    user: Pick<User, 'id' | 'username'>,
    isFollowing: boolean,
    setIsFollowing: (isFollowing: boolean) => void,
    onFinish?: () => void,
): void {
    const next = !isFollowing;

    router.visit(
        next
            ? UserFollowController.store(user)
            : UserFollowController.destroy(user),
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                setIsFollowing(next);
                toast(
                    <span className="font-mono text-[13px]">
                        {next
                            ? `Following @${user.username}`
                            : `Unfollowed @${user.username}`}
                    </span>,
                );
            },
            onHttpException: (response) =>
                onFollowHttpException(user, response),
            onFinish,
        },
    );
}

export function FollowLabel({
    isFollowing,
    isJustToggled,
}: {
    isFollowing: boolean;
    isJustToggled: boolean;
}) {
    if (!isFollowing) {
        return 'Follow';
    }

    if (isJustToggled) {
        return 'Following';
    }

    return (
        <>
            <span className="group-hover/follow:hidden">Following</span>
            <span className="hidden text-destructive group-hover/follow:inline">
                Unfollow
            </span>
        </>
    );
}

export function FollowButton({
    user,
    isFollowing: initialIsFollowing,
    size,
    className,
    onClick,
    onSuccess,
    ...props
}: {
    user: Pick<User, 'id' | 'username'>;
    isFollowing: boolean;
    size?: ComponentProps<typeof Button>['size'];
    className?: string;
} & Omit<InertiaLinkProps, 'href' | 'as' | 'method' | 'size' | 'className'>) {
    const { auth } = usePage().props;
    const [isFollowing, setIsFollowing] = useIsFollowing(
        user,
        initialIsFollowing,
    );
    const { isJustToggled, markToggled, onMouseLeave } = useFollowHover();

    if (auth.user === null) {
        return (
            <Button
                size={size}
                className={className}
                onClick={signIn}
                aria-label={`Follow @${user.username}`}
                data-test="follow-button"
            >
                Follow
            </Button>
        );
    }

    return (
        <Button
            variant={isFollowing ? 'outline' : 'default'}
            size={size}
            className={cn('group/follow', className)}
            asChild
        >
            <Link
                href={
                    isFollowing
                        ? UserFollowController.destroy(user)
                        : UserFollowController.store(user)
                }
                as="button"
                preserveScroll
                onHttpException={(response) =>
                    onFollowHttpException(user, response)
                }
                aria-label={
                    isFollowing
                        ? `Unfollow @${user.username}`
                        : `Follow @${user.username}`
                }
                data-test="follow-button"
                onClick={(event) => {
                    markToggled();
                    onClick?.(event);
                }}
                onSuccess={(page) => {
                    setIsFollowing(!isFollowing);
                    onSuccess?.(page);
                }}
                onMouseLeave={onMouseLeave}
                {...props}
            >
                <FollowLabel
                    isFollowing={isFollowing}
                    isJustToggled={isJustToggled}
                />
            </Link>
        </Button>
    );
}
