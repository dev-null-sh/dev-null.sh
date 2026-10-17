import { useLang } from '@erag/lang-sync-inertia/react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    ArrowUpRight,
    Building2,
    CalendarDays,
    Heart,
    Link2,
    MapPin,
} from 'lucide-react';
import type { CSSProperties } from 'react';
import { useEffect, useState } from 'react';
import { show } from '@/actions/App/Http/Controllers/UserController';
import UserFollowersController from '@/actions/App/Http/Controllers/UserFollowersController';
import UserFollowingController from '@/actions/App/Http/Controllers/UserFollowingController';
import { AchievementsDialog } from '@/components/achievements-dialog';
import { AchievementsShelf } from '@/components/achievements-shelf';
import { ActivityGraph } from '@/components/activity-graph';
import { ActivityStreaks } from '@/components/activity-streaks';
import { CommitFeed, EmptyFeed } from '@/components/commit-feed';
import { FollowButton } from '@/components/follow-button';
import { GitHubIcon } from '@/components/github-icon';
import { PageTabs } from '@/components/page-tabs';
import { PinCommitButton } from '@/components/pin-commit-button';
import { Button } from '@/components/ui/button';
import { UserAvatar } from '@/components/user-avatar';
import { UserSafetyMenu } from '@/components/user-safety-menu';
import { refreshSharedState, useSharedState } from '@/hooks/use-shared-state';
import AppLayout from '@/layouts/app-layout';
import { commitStateKey } from '@/lib/commit-state';
import { laneColor } from '@/lib/utils';
import type {
    Achievements,
    Activity,
    Commit,
    CursorPaginated,
    Profile,
    Streaks,
} from '@/types';

export default function Show({
    user,
    pasteisReceived,
    isFollowing,
    followsYou,
    isMuting,
    activity,
    streaks,
    achievements,
    commits,
    tab,
    pinnedCommit,
}: {
    user: Profile;
    pasteisReceived: number | null;
    isFollowing: boolean;
    followsYou: boolean;
    isMuting: boolean;
    activity: Activity;
    streaks: Streaks;
    achievements: Achievements;
    commits: CursorPaginated<Commit>;
    tab: 'commits' | 'replies';
    pinnedCommit: Commit | null;
}) {
    const { auth } = usePage().props;
    const { __, transChoice } = useLang();
    const isCurrentUser = auth.user?.id === user.id;
    const lane = laneColor(user.lane);
    const [highlightedPeriod, setHighlightedPeriod] = useState<string | null>(
        null,
    );
    const [isAchievementsOpen, setIsAchievementsOpen] = useState(false);
    const [pasteisCount] = useSharedState(
        commitStateKey('pasteis_received', user.id),
        pasteisReceived ?? 0,
    );

    useEffect(() => {
        if (pasteisReceived !== null) {
            refreshSharedState(
                new Map([
                    [
                        commitStateKey('pasteis_received', user.id),
                        pasteisReceived,
                    ],
                ]),
            );
        }
    }, [user.id, pasteisReceived]);

    return (
        <>
            <Head title={`${user.name} (@${user.username})`} />

            <div className="sticky top-0 z-10 flex h-14 items-center gap-3 border-b bg-card px-4 sm:px-6">
                <p className="min-w-0 truncate font-semibold">{user.name}</p>
                <p className="shrink-0 font-mono text-[13px] text-muted-foreground">
                    {user.commits_count}{' '}
                    {transChoice('users.counts.commits', user.commits_count)}
                </p>
            </div>

            <header className="border-b px-4 pt-6 pb-5 sm:px-6">
                <div className="flex items-start justify-between gap-4">
                    <UserAvatar
                        user={user}
                        className="size-20 text-2xl ring-2 ring-(--lane) ring-offset-4 ring-offset-card"
                        pixels={160}
                        style={{ '--lane': lane } as CSSProperties}
                    />

                    {isCurrentUser ? (
                        <Button variant="outline" asChild>
                            <a
                                href="https://github.com/settings/profile"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                {__('users.profile.edit_on_github')}
                                <ArrowUpRight />
                            </a>
                        </Button>
                    ) : (
                        <div className="flex gap-2">
                            {auth.user && (
                                <UserSafetyMenu
                                    user={user}
                                    isMuting={isMuting}
                                    isBlocking={false}
                                />
                            )}
                            <FollowButton
                                user={user}
                                isFollowing={isFollowing}
                                className="min-w-28"
                                onSuccess={() => router.flushAll()}
                            />
                        </div>
                    )}
                </div>

                <h1 className="mt-4 text-2xl font-semibold tracking-tight text-balance">
                    {user.name}
                </h1>
                <div className="flex items-center gap-2">
                    <p className="flex items-center gap-1.5 font-mono text-sm text-muted-foreground">
                        <span
                            aria-hidden
                            className="size-2 rounded-full"
                            style={{ background: lane }}
                        />
                        @{user.username}
                        {followsYou && (
                            <span className="ml-1 shrink-0 rounded bg-muted px-1.5 py-0.5 font-sans text-xs">
                                {__('users.follows_you')}
                            </span>
                        )}
                        {isMuting && (
                            <span className="ml-1 rounded bg-muted px-1.5 py-0.5 font-sans text-xs">
                                {__('users.muted')}
                            </span>
                        )}
                    </p>
                    <button
                        type="button"
                        onClick={() => setIsAchievementsOpen(true)}
                        className="inline-flex cursor-pointer items-center gap-1 rounded-full border px-2 py-0.5 font-mono text-[11px] font-medium transition hover:brightness-110"
                        style={{
                            borderColor: `${lane}40`,
                            backgroundColor: `${lane}15`,
                            color: lane,
                        }}
                        title={__('users.profile.view_achievements')}
                    >
                        <span>
                            {__('users.achievements.level', {
                                level: achievements.level,
                                rank: achievements.rank,
                            })}
                        </span>
                    </button>
                </div>

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
                    {user.has_github_account && (
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
                    )}
                    {user.has_sponsors_listing && (
                        <li className="flex items-center gap-1.5">
                            <Heart className="size-4" />
                            <a
                                href={`https://github.com/sponsors/${user.username}`}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="text-primary hover:underline"
                            >
                                {__('users.profile.sponsor')}
                            </a>
                        </li>
                    )}
                    <li className="flex items-center gap-1.5">
                        <CalendarDays className="size-4" />
                        {__('users.profile.joined', {
                            date: new Date(user.created_at).toLocaleDateString(
                                undefined,
                                { month: 'long', year: 'numeric' },
                            ),
                        })}
                    </li>
                </ul>

                <dl className="mt-4 flex gap-5 text-sm">
                    {[
                        {
                            count: user.commits_count,
                            label: transChoice(
                                'users.counts.commits',
                                user.commits_count,
                            ),
                            href: show(user),
                        },
                        {
                            count: user.following_count,
                            label: __('users.counts.following'),
                            href: UserFollowingController.index(user),
                        },
                        {
                            count: user.followers_count,
                            label: transChoice(
                                'users.counts.followers',
                                user.followers_count,
                            ),
                            href: UserFollowersController.index(user),
                        },
                    ].map((stat) => (
                        <div
                            key={stat.label}
                            className="group flex flex-row-reverse gap-1.5"
                        >
                            <dt className="text-muted-foreground">
                                <Link
                                    href={stat.href}
                                    className="group-hover:underline"
                                >
                                    {stat.label}
                                </Link>
                            </dt>
                            <dd className="font-mono font-medium">
                                <Link
                                    href={stat.href}
                                    className="group-hover:underline"
                                >
                                    {stat.count}
                                </Link>
                            </dd>
                        </div>
                    ))}
                </dl>
                {auth.pasteis && pasteisReceived !== null && (
                    <p
                        className="mt-3 flex items-center gap-1.5 text-sm text-muted-foreground"
                        data-test="profile-pasteis-count"
                    >
                        <img
                            src="/pastel-de-nata.svg"
                            alt=""
                            width={20}
                            height={20}
                            className="size-5 shrink-0 object-contain"
                        />
                        <span className="font-mono font-medium text-foreground tabular-nums">
                            {pasteisCount}
                        </span>
                        {transChoice(
                            'users.profile.pasteis_received',
                            pasteisCount,
                        )}
                    </p>
                )}
            </header>

            <ActivityGraph
                activity={activity}
                lane={lane}
                onPeriodHover={setHighlightedPeriod}
            />

            <ActivityStreaks streaks={streaks} lane={lane} />

            <AchievementsShelf
                achievements={achievements}
                lane={lane}
                onInspect={() => setIsAchievementsOpen(true)}
            />

            <AchievementsDialog
                open={isAchievementsOpen}
                onOpenChange={setIsAchievementsOpen}
                achievements={achievements}
                user={user}
                lane={lane}
            />

            <PageTabs
                label={__('users.profile.feed_label')}
                name="Profile feed"
                preserveScroll
                only={['tab', 'commits', 'pinnedCommit']}
                reset={['commits']}
                tabs={[
                    {
                        title: __('users.profile.tabs.commits'),
                        href: show(user),
                        isActive: tab === 'commits',
                    },
                    {
                        title: __('users.profile.tabs.replies'),
                        href: show(user, { query: { tab: 'replies' } }),
                        isActive: tab === 'replies',
                    },
                ]}
            />

            <CommitFeed
                key={`${user.id}:${tab}`}
                commits={commits}
                pinnedCommit={tab === 'commits' ? pinnedCommit : null}
                threadActions={
                    isCurrentUser && tab === 'commits'
                        ? (commit) => (
                              <PinCommitButton
                                  commit={commit}
                                  isPinned={commit.id === pinnedCommit?.id}
                              />
                          )
                        : undefined
                }
                highlightedPeriod={highlightedPeriod}
                end={
                    tab === 'replies'
                        ? isCurrentUser
                            ? __('users.profile.end.own_first_reply')
                            : __('users.profile.end.first_reply', {
                                  username: user.username,
                              })
                        : isCurrentUser
                          ? __('users.profile.end.own_first_commit')
                          : __('users.profile.end.root_commit', {
                                username: user.username,
                            })
                }
                empty={
                    tab === 'replies' ? (
                        <EmptyFeed
                            command={__('users.profile.empty.replies_command')}
                        >
                            {isCurrentUser
                                ? __('users.profile.empty.own_replies')
                                : __('users.profile.empty.replies', {
                                      username: user.username,
                                  })}
                        </EmptyFeed>
                    ) : isCurrentUser ? (
                        <EmptyFeed
                            command={__(
                                'users.profile.empty.own_commits_command',
                            )}
                        >
                            {__('users.profile.empty.own_commits')}
                        </EmptyFeed>
                    ) : (
                        <EmptyFeed
                            command={__('users.profile.empty.commits_command')}
                        >
                            {__('users.profile.empty.commits', {
                                username: user.username,
                            })}
                        </EmptyFeed>
                    )
                }
            />
        </>
    );
}

Show.layout = AppLayout;
