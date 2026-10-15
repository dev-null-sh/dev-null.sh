import { Deferred, Link, router, usePage } from '@inertiajs/react';
import UserFollowController from '@/actions/App/Http/Controllers/UserFollowController';
import { RailSegment } from '@/components/graph-rail';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { UserAvatar } from '@/components/user-avatar';
import { cn, laneFor } from '@/lib/utils';
import { show } from '@/routes/user';
import type { SuggestedUser } from '@/types';

const asideLimit = 3;

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

function FollowButton({
    user,
    className,
}: {
    user: SuggestedUser;
    className?: string;
}) {
    return (
        <Button size="sm" className={cn('shrink-0', className)} asChild>
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
    );
}

function Username({ user }: { user: SuggestedUser }) {
    return (
        <span className="flex min-w-0 items-center gap-1.5 font-mono text-xs text-muted-foreground">
            <span
                aria-hidden
                className="size-1.5 shrink-0 rounded-full"
                style={{ background: laneFor(user.id) }}
            />
            <span className="truncate">@{user.username}</span>
        </span>
    );
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
                    <Username user={user} />
                    {reason && (
                        <span className="block truncate text-xs text-muted-foreground">
                            {reason}
                        </span>
                    )}
                </span>
            </Link>
            <FollowButton user={user} />
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
                    {suggestedUsers?.slice(0, asideLimit).map((user) => (
                        <SuggestedUserRow key={user.id} user={user} />
                    ))}
                </ul>
            </Deferred>
        </section>
    );
}

function SuggestedUserCard({ user }: { user: SuggestedUser }) {
    const reason = reasonFor(user);

    return (
        <li className="flex w-40 shrink-0 snap-start flex-col items-center rounded-lg border bg-card p-3 text-center">
            <Link
                href={show(user)}
                className="group flex w-full min-w-0 flex-col items-center"
            >
                <UserAvatar user={user} className="size-14 text-base" />
                <span className="mt-2 block w-full truncate text-sm font-semibold group-hover:underline">
                    {user.name}
                </span>
                <span className="flex max-w-full justify-center">
                    <Username user={user} />
                </span>
                <span className="mt-1 line-clamp-2 min-h-8 text-xs text-muted-foreground">
                    {reason}
                </span>
            </Link>
            <FollowButton user={user} className="mt-3 w-full" />
        </li>
    );
}

function SuggestedUserCardsSkeleton() {
    return (
        <ul className="flex gap-3 overflow-hidden" aria-hidden>
            {[0, 1, 2].map((index) => (
                <li
                    key={index}
                    className="flex w-40 shrink-0 flex-col items-center gap-2 rounded-lg border p-3"
                >
                    <Skeleton className="size-14 rounded-full" />
                    <Skeleton className="h-3.5 w-24" />
                    <Skeleton className="h-3 w-16" />
                    <Skeleton className="mt-6 h-8 w-full" />
                </li>
            ))}
        </ul>
    );
}

/**
 * The aside is hidden below the xl breakpoint, so smaller screens get the suggestions as a swipeable row inside the log.
 */
export function WhoToFollowCarousel({
    lane,
    isDimmed = false,
}: {
    lane: string;
    isDimmed?: boolean;
}) {
    const { suggestedUsers } = usePage().props;

    if (suggestedUsers?.length === 0) {
        return null;
    }

    return (
        <section aria-labelledby="log-who-to-follow" className="flex xl:hidden">
            <RailSegment lane={lane} isDimmed={isDimmed} />
            <div className="min-w-0 flex-1 border-b py-3">
                <h2
                    id="log-who-to-follow"
                    className="mb-3 pr-4 text-sm font-semibold"
                >
                    Who to follow
                </h2>
                <Deferred
                    data="suggestedUsers"
                    fallback={<SuggestedUserCardsSkeleton />}
                >
                    <ul className="flex snap-x snap-mandatory [scrollbar-width:none] gap-3 overflow-x-auto pr-4 pb-1">
                        {suggestedUsers?.map((user) => (
                            <SuggestedUserCard key={user.id} user={user} />
                        ))}
                    </ul>
                </Deferred>
            </div>
        </section>
    );
}
