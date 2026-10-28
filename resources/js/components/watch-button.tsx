import { Link } from '@inertiajs/react';
import { Bell, BellRing } from 'lucide-react';
import UserWatchController from '@/actions/App/Http/Controllers/UserWatchController';
import { useIsFollowing } from '@/components/follow-button';
import { Button } from '@/components/ui/button';
import type { User } from '@/types';

/**
 * Watching is part of a follow, so the bell only shows for someone you follow, and goes away with the follow.
 */
export function WatchButton({
    user,
    isFollowing: initialIsFollowing,
    isWatching,
}: {
    user: Pick<User, 'id' | 'handle'>;
    isFollowing: boolean;
    isWatching: boolean;
}) {
    const [isFollowing] = useIsFollowing(user, initialIsFollowing);

    if (!isFollowing) {
        return null;
    }

    const label = isWatching
        ? `Stop notifications for @${user.handle}'s commits`
        : `Get notified when @${user.handle} commits`;

    return (
        <Button
            variant="outline"
            size="icon"
            className={
                isWatching ? 'text-primary hover:text-primary' : undefined
            }
            asChild
        >
            <Link
                href={
                    isWatching
                        ? UserWatchController.destroy(user)
                        : UserWatchController.store(user)
                }
                as="button"
                preserveScroll
                only={['isWatching']}
                aria-label={label}
                aria-pressed={isWatching}
                title={label}
                data-test="watch-button"
            >
                {isWatching ? <BellRing /> : <Bell />}
            </Link>
        </Button>
    );
}
