import { Deferred, Link, router, usePage } from '@inertiajs/react';
import UserFollowController from '@/actions/App/Http/Controllers/UserFollowController';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { UserAvatar } from '@/components/user-avatar';
import { laneFor } from '@/lib/utils';
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

function SuggestedUserRow({ user }: { user: SuggestedUser }) {
    const reason = reasonFor(user);

    return (
        <li className="flex items-center gap-3">
            <Link
                href={show(user)}
                className="group flex min-w-0 flex-1 items-center gap-3"
            >
                <UserAvatar user={user} className="size-9" />
                <span className="min-w-0">
                    <span className="block truncate text-sm font-semibold group-hover:underline">
                        {user.name}
                    </span>
                    <span className="flex items-center gap-1.5 font-mono text-xs text-muted-foreground">
                        <span
                            aria-hidden
                            className="size-1.5 shrink-0 rounded-full"
                            style={{ background: laneFor(user.id) }}
                        />
                        <span className="truncate">@{user.username}</span>
                    </span>
                    {reason && (
                        <span className="block truncate text-xs text-muted-foreground">
                            {reason}
                        </span>
                    )}
                </span>
            </Link>
            <Button size="sm" className="shrink-0" asChild>
                <Link
                    href={UserFollowController.store(user)}
                    as="button"
                    preserveScroll
                    onSuccess={() => router.flushAll()}
                    aria-label={`Follow @${user.username}`}
                    data-test="suggested-follow-button"
                >
                    Follow
                </Link>
            </Button>
        </li>
    );
}

function SuggestedUsersSkeleton() {
    return (
        <ul className="space-y-4" aria-hidden>
            {[0, 1, 2].map((index) => (
                <li key={index} className="flex items-center gap-3">
                    <Skeleton className="size-9 rounded-full" />
                    <span className="flex-1 space-y-1.5">
                        <Skeleton className="h-3.5 w-28" />
                        <Skeleton className="h-3 w-20" />
                    </span>
                </li>
            ))}
        </ul>
    );
}

export function WhoToFollow() {
    const { suggestedUsers } = usePage().props;

    if (suggestedUsers?.length === 0) {
        return null;
    }

    return (
        <section
            aria-labelledby="aside-who-to-follow"
            className="border-t pt-6"
        >
            <h2 id="aside-who-to-follow" className="mb-4 text-sm font-semibold">
                Who to follow
            </h2>
            <Deferred
                data="suggestedUsers"
                fallback={<SuggestedUsersSkeleton />}
            >
                <ul className="space-y-4">
                    {suggestedUsers?.map((user) => (
                        <SuggestedUserRow key={user.id} user={user} />
                    ))}
                </ul>
            </Deferred>
        </section>
    );
}
