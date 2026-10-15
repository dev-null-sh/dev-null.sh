import { Link } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';
import type { ComponentProps } from 'react';
import UserFollowController from '@/actions/App/Http/Controllers/UserFollowController';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { User } from '@/types';

export function FollowButton({
    user,
    isFollowing,
    size,
    className,
    ...props
}: {
    user: Pick<User, 'username'>;
    isFollowing: boolean;
    size?: ComponentProps<typeof Button>['size'];
    className?: string;
} & Omit<InertiaLinkProps, 'href' | 'as' | 'method' | 'size' | 'className'>) {
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
                {...props}
            >
                {isFollowing ? (
                    <>
                        <span className="group-hover/follow:hidden">
                            Following
                        </span>
                        <span className="hidden text-destructive group-hover/follow:inline">
                            Unfollow
                        </span>
                    </>
                ) : (
                    'Follow'
                )}
            </Link>
        </Button>
    );
}
