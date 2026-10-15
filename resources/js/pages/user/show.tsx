import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    ArrowUpRight,
    Building2,
    CalendarDays,
    Link2,
    MapPin,
} from 'lucide-react';
import type { CSSProperties } from 'react';
import { useState } from 'react';
import UserFollowersController from '@/actions/App/Http/Controllers/UserFollowersController';
import UserFollowingController from '@/actions/App/Http/Controllers/UserFollowingController';
import { ActivityGraph } from '@/components/activity-graph';
import { CommitFeed, EmptyFeed } from '@/components/commit-feed';
import { FollowButton } from '@/components/follow-button';
import { GitHubIcon } from '@/components/github-icon';
import { Button } from '@/components/ui/button';
import { UserAvatar } from '@/components/user-avatar';
import AppLayout from '@/layouts/app-layout';
import { laneColor } from '@/lib/utils';
import type { ActivityDay, Commit, CursorPaginated, Profile } from '@/types';

function pluralize(count: number, singular: string, plural: string) {
    return count === 1 ? singular : plural;
}

export default function Show({
    user,
    isFollowing,
    activity,
    commits,
}: {
    user: Profile;
    isFollowing: boolean;
    activity: ActivityDay[];
    commits: CursorPaginated<Commit>;
}) {
    const { auth } = usePage().props;
    const isCurrentUser = auth.user.id === user.id;
    const lane = laneColor(user.lane);
    const [highlightedDate, setHighlightedDate] = useState<string | null>(null);

    return (
        <>
            <Head title={`${user.name} (@${user.username})`} />

            <div className="sticky top-0 z-10 flex h-14 items-center gap-3 border-b bg-card px-4">
                <p className="min-w-0 truncate font-semibold">{user.name}</p>
                <p className="shrink-0 font-mono text-[13px] text-muted-foreground">
                    {user.commits_count}{' '}
                    {pluralize(user.commits_count, 'commit', 'commits')}
                </p>
            </div>

            <header className="border-b px-4 pt-6 pb-5 sm:px-6">
                <div className="flex items-start justify-between gap-4">
                    <UserAvatar
                        user={user}
                        className="size-20 text-2xl ring-2 ring-(--lane) ring-offset-4 ring-offset-card"
                        style={{ '--lane': lane } as CSSProperties}
                    />

                    {isCurrentUser ? (
                        <Button variant="outline" asChild>
                            <a
                                href="https://github.com/settings/profile"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                Edit on GitHub
                                <ArrowUpRight />
                            </a>
                        </Button>
                    ) : (
                        <FollowButton
                            user={user}
                            isFollowing={isFollowing}
                            className="min-w-28"
                            onSuccess={() => router.flushAll()}
                        />
                    )}
                </div>

                <h1 className="mt-4 text-2xl font-semibold tracking-tight text-balance">
                    {user.name}
                </h1>
                <p className="flex items-center gap-1.5 font-mono text-sm text-muted-foreground">
                    <span
                        aria-hidden
                        className="size-2 rounded-full"
                        style={{ background: lane }}
                    />
                    @{user.username}
                </p>

                {user.bio && (
                    <p className="mt-3 max-w-prose text-[15px] leading-relaxed break-words whitespace-pre-wrap">
                        {user.bio}
                    </p>
                )}

                <ul className="mt-3 flex flex-wrap gap-x-4 gap-y-1.5 text-sm text-muted-foreground">
                    {user.company && (
                        <li className="flex items-center gap-1.5">
                            <Building2 className="size-4" />
                            {user.company}
                        </li>
                    )}
                    {user.location && (
                        <li className="flex items-center gap-1.5">
                            <MapPin className="size-4" />
                            {user.location}
                        </li>
                    )}
                    {user.website && (
                        <li className="flex min-w-0 items-center gap-1.5">
                            <Link2 className="size-4 shrink-0" />
                            <a
                                href={user.website}
                                target="_blank"
                                rel="noopener noreferrer nofollow"
                                className="truncate text-primary hover:underline"
                            >
                                {user.website.replace(/^https?:\/\//u, '')}
                            </a>
                        </li>
                    )}
                    <li className="flex items-center gap-1.5">
                        <GitHubIcon className="size-4" />
                        <a
                            href={`https://github.com/${user.username}`}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="text-primary hover:underline"
                        >
                            github.com/{user.username}
                        </a>
                    </li>
                    <li className="flex items-center gap-1.5">
                        <CalendarDays className="size-4" />
                        Joined{' '}
                        {new Date(user.created_at).toLocaleDateString(
                            undefined,
                            { month: 'long', year: 'numeric' },
                        )}
                    </li>
                </ul>

                <dl className="mt-4 flex gap-5 text-sm">
                    {[
                        {
                            count: user.commits_count,
                            label: pluralize(
                                user.commits_count,
                                'commit',
                                'commits',
                            ),
                        },
                        {
                            count: user.following_count,
                            label: 'following',
                            href: UserFollowingController.index(user),
                        },
                        {
                            count: user.followers_count,
                            label: pluralize(
                                user.followers_count,
                                'follower',
                                'followers',
                            ),
                            href: UserFollowersController.index(user),
                        },
                    ].map((stat) => (
                        <div
                            key={stat.label}
                            className="flex flex-row-reverse gap-1.5"
                        >
                            <dt className="text-muted-foreground">
                                {stat.href ? (
                                    <Link
                                        href={stat.href}
                                        className="hover:underline"
                                    >
                                        {stat.label}
                                    </Link>
                                ) : (
                                    stat.label
                                )}
                            </dt>
                            <dd className="font-mono font-medium">
                                {stat.count}
                            </dd>
                        </div>
                    ))}
                </dl>
            </header>

            <ActivityGraph
                activity={activity}
                lane={lane}
                onDayHover={setHighlightedDate}
            />

            <CommitFeed
                commits={commits}
                highlightedDate={highlightedDate}
                end={
                    isCurrentUser
                        ? 'That was your first commit. Everyone starts somewhere.'
                        : `That's @${user.username}'s root commit.`
                }
                empty={
                    isCurrentUser ? (
                        <EmptyFeed command="fatal: you do not have any commits yet">
                            Your commits show up here. Make your first one from
                            your log.
                        </EmptyFeed>
                    ) : (
                        <EmptyFeed command="fatal: no commits yet">
                            @{user.username} hasn't committed anything yet.
                        </EmptyFeed>
                    )
                }
            />
        </>
    );
}

Show.layout = AppLayout;
