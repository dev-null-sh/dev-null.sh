import { Link, usePage } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';
import type { ComponentProps } from 'react';
import { useState } from 'react';
import UserFollowController from '@/actions/App/Http/Controllers/UserFollowController';
import { Button } from '@/components/ui/button';
import { signIn } from '@/lib/auth';
import { cn } from '@/lib/utils';
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
    isFollowing,
    size,
    className,
    onClick,
    ...props
}: {
    user: Pick<User, 'username'>;
    isFollowing: boolean;
    size?: ComponentProps<typeof Button>['size'];
    className?: string;
} & Omit<InertiaLinkProps, 'href' | 'as' | 'method' | 'size' | 'className'>) {
    const { auth } = usePage().props;
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
