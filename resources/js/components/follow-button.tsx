import { Link, router } from '@inertiajs/react';
import UserFollowController from '@/actions/App/Http/Controllers/UserFollowController';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

export function FollowButton({
    user,
    isFollowing,
    size = 'default',
    className,
}: {
    user: { username: string };
    isFollowing: boolean;
    size?: 'default' | 'sm';
    className?: string;
}) {
    return (
        <Button
            variant={isFollowing ? 'outline' : 'default'}
            size={size}
            className={cn('group/follow min-w-28', className)}
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
                onSuccess={() => router.flushAll()}
                aria-label={
                    isFollowing
                        ? `Unfollow @${user.username}`
                        : `Follow @${user.username}`
                }
                data-test="follow-button"
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
