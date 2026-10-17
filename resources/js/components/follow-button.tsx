import { useLang } from '@erag/lang-sync-inertia/react';
import { Link, usePage } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';
import type { ComponentProps } from 'react';
import { useState } from 'react';
import UserFollowController from '@/actions/App/Http/Controllers/UserFollowController';
import { Button } from '@/components/ui/button';
import { signIn } from '@/lib/auth';
import { toastWhenThrottled } from '@/lib/throttle';
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
    const { __ } = useLang();

    if (!isFollowing) {
        return __('users.follow.follow');
    }

    if (isJustToggled) {
        return __('users.follow.following');
    }

    return (
        <>
            <span className="group-hover/follow:hidden">
                {__('users.follow.following')}
            </span>
            <span className="hidden text-destructive group-hover/follow:inline">
                {__('users.follow.unfollow')}
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
    const { __ } = useLang();
    const { isJustToggled, markToggled, onMouseLeave } = useFollowHover();

    if (auth.user === null) {
        return (
            <Button
                size={size}
                className={className}
                onClick={signIn}
                aria-label={__('users.follow.follow_label', {
                    username: user.username,
                })}
                data-test="follow-button"
            >
                {__('users.follow.follow')}
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
                onHttpException={(response) => !toastWhenThrottled(response)}
                aria-label={__(
                    isFollowing
                        ? 'users.follow.unfollow_label'
                        : 'users.follow.follow_label',
                    { username: user.username },
                )}
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
