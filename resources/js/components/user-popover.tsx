import { Link, usePage } from '@inertiajs/react';
import type { CSSProperties } from 'react';
import { FollowButton } from '@/components/follow-button';
import { Button } from '@/components/ui/button';
import { UserAvatar } from '@/components/user-avatar';
import { laneFor } from '@/lib/utils';
import { show } from '@/routes/user';
import type { CommitAuthor } from '@/types';

function pluralize(count: number, singular: string, plural: string) {
    return count === 1 ? singular : plural;
}

export function UserPopover({ user }: { user: CommitAuthor }) {
    const { auth } = usePage().props;
    const isCurrentUser = auth.user.id === user.id;
    const lane = laneFor(user.id);

    return (
        <div data-test="user-popover">
            <div className="flex items-start justify-between gap-3">
                <UserAvatar
                    user={user}
                    className="size-12 text-lg ring-2 ring-(--lane) ring-offset-2 ring-offset-popover"
                    style={{ '--lane': lane } as CSSProperties}
                />

                {isCurrentUser ? (
                    <Button variant="outline" size="sm" asChild>
                        <Link href={show(user)} data-test="view-profile-button">
                            View profile
                        </Link>
                    </Button>
                ) : (
                    <FollowButton
                        user={user}
                        isFollowing={user.is_following}
                        size="sm"
                        className="shrink-0"
                    />
                )}
            </div>

            <Link
                href={show(user)}
                className="mt-3 block truncate font-semibold hover:underline"
            >
                {user.name}
            </Link>
            <p className="mt-0.5 flex items-center gap-1.5 font-mono text-[13px] text-muted-foreground">
                <span
                    aria-hidden
                    className="size-2 shrink-0 rounded-full"
                    style={{ background: lane }}
                />
                <span className="truncate">@{user.username}</span>
            </p>

            {user.bio && (
                <p className="mt-3 line-clamp-3 text-[15px] leading-relaxed break-words whitespace-pre-wrap">
                    {user.bio}
                </p>
            )}

            <dl className="mt-3 flex gap-4 text-sm">
                {[
                    {
                        count: user.followers_count,
                        label: pluralize(
                            user.followers_count,
                            'follower',
                            'followers',
                        ),
                    },
                    { count: user.following_count, label: 'following' },
                ].map((stat) => (
                    <div
                        key={stat.label}
                        className="flex flex-row-reverse gap-1.5"
                    >
                        <dt className="text-muted-foreground">{stat.label}</dt>
                        <dd className="font-mono font-medium">{stat.count}</dd>
                    </div>
                ))}
            </dl>
        </div>
    );
}
