import { Head, Link, usePage } from '@inertiajs/react';
import {
    Building2,
    CalendarDays,
    Heart,
    Link2,
    MapPin,
    MessagesSquare,
} from 'lucide-react';
import type { CSSProperties } from 'react';
import { useEffect, useLayoutEffect, useRef, useState } from 'react';
import ConversationController from '@/actions/App/Http/Controllers/ConversationController';
import { show } from '@/actions/App/Http/Controllers/UserController';
import UserFollowersController from '@/actions/App/Http/Controllers/UserFollowersController';
import UserFollowingController from '@/actions/App/Http/Controllers/UserFollowingController';
import { AchievementsDialog } from '@/components/achievements-dialog';
import { AchievementsShelf } from '@/components/achievements-shelf';
import { ActivityGraph } from '@/components/activity-graph';
import { ActivityStreaks } from '@/components/activity-streaks';
import { BioText } from '@/components/bio-text';
import { CommitFeed, EmptyFeed } from '@/components/commit-feed';
import { EditProfileMenu } from '@/components/edit-profile-menu';
import { FollowButton, useIsFollowing } from '@/components/follow-button';
import { FollowersYouKnow } from '@/components/followers-you-know';
import type { FollowersYouKnowPreview } from '@/components/followers-you-know';
import { GitHubIcon } from '@/components/github-icon';
import {
    LiveAvatarMarker,
    LiveStreamTrigger,
} from '@/components/live-stream-card';
import { LocalTime } from '@/components/local-time';
import { MembersLink } from '@/components/members-link';
import { PageTabs } from '@/components/page-tabs';
import { PinCommitButton } from '@/components/pin-commit-button';
import { ProfileLiveButton } from '@/components/profile-live-button';
import { ProfileReadme } from '@/components/profile-readme';
import { ProfileShareButton } from '@/components/profile-share-button';
import { Button } from '@/components/ui/button';
import { UserAvatar } from '@/components/user-avatar';
import type { UserModeration } from '@/components/user-moderation-menu';
import {
    UserModerationMenu,
    UserModerationStatus,
} from '@/components/user-moderation-menu';
import { useIsMuting, UserSafetyMenu } from '@/components/user-safety-menu';
import { refreshSharedState, useSharedState } from '@/hooks/use-shared-state';
import AppLayout from '@/layouts/app-layout';
import { commitStateKey } from '@/lib/commit-state';
import { exactCountTitle, formatCount } from '@/lib/numbers';
import { profileAvatarPosition } from '@/lib/profile-avatar';
import { formatClockMonth, laneColor, laneTint } from '@/lib/utils';
import type {
    Achievements,
    Activity,
    Commit,
    CursorPaginated,
    Profile,
    Streaks,
} from '@/types';

function pluralize(count: number, singular: string, plural: string) {
    return count === 1 ? singular : plural;
}

export default function Show({
    user,
    bioProfiles,
    pasteisReceived,
    isFollowing,
    followsYou,
    isMuting,
    followersYouKnow,
    canPush,
    moderation,
    activity,
    streaks,
    achievements,
    commits,
    tab,
    pinnedCommit,
    readme,
}: {
    user: Profile;
    bioProfiles: Record<string, string>;
    pasteisReceived: number | null;
    isFollowing: boolean;
    followsYou: boolean;
    isMuting: boolean;
    followersYouKnow: FollowersYouKnowPreview | null;
    canPush: boolean;
    moderation: UserModeration | null;
    activity: Activity;
    streaks: Streaks;
    achievements: Achievements;
    commits: CursorPaginated<Commit>;
    tab: 'commits' | 'replies' | 'readme';
    pinnedCommit: Commit | null;
    readme: string | false | null;
}) {
    const { auth } = usePage().props;
    const isCurrentUser = auth.user?.id === user.id;
    const lane = laneColor(user.lane);
    const [highlightedPeriod, setHighlightedPeriod] = useState<string | null>(
        null,
    );
    const [isAchievementsOpen, setIsAchievementsOpen] = useState(false);
    const avatarRef = useRef<HTMLDivElement>(null);
    const headerRef = useRef<HTMLDivElement>(null);
    const avatarTargetRef = useRef<HTMLDivElement>(null);
    const [isHeaderVisible, setIsHeaderVisible] = useState(false);
    const [isFollowingNow] = useIsFollowing(user, isFollowing);
    const [isMutingNow] = useIsMuting(user, isMuting);
    const followersCount =
        user.followers_count + Number(isFollowingNow) - Number(isFollowing);
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

    useLayoutEffect(() => {
        const avatar = avatarRef.current;
        const header = headerRef.current;
        const target = avatarTargetRef.current;

        if (!avatar || !header || !target) {
            return;
        }

        const reducedMotion = window.matchMedia(
            '(prefers-reduced-motion: reduce)',
        );
        const update = () => {
            const bounds = header.getBoundingClientRect();
            const source = avatar.getBoundingClientRect();
            const position = profileAvatarPosition(
                source,
                target.getBoundingClientRect(),
                bounds.bottom,
                reducedMotion.matches,
            );

            header.style.setProperty(
                '--profile-progress',
                String(position.progress),
            );
            header.style.setProperty(
                '--profile-header-progress',
                String(Math.max(0, (position.progress - 0.75) * 4)),
            );
            header.style.setProperty(
                '--profile-avatar-left',
                `${position.left - bounds.left}px`,
            );
            header.style.setProperty(
                '--profile-avatar-top',
                `${position.top - bounds.top}px`,
            );
            header.style.setProperty(
                '--profile-avatar-scale',
                String(position.size / source.width),
            );
            setIsHeaderVisible(position.progress > 0.75);
        };
        const observer = new ResizeObserver(update);

        observer.observe(header);
        observer.observe(avatar);
        window.addEventListener('scroll', update, { passive: true });
        reducedMotion.addEventListener('change', update);
        update();

        return () => {
            observer.disconnect();
            window.removeEventListener('scroll', update);
            reducedMotion.removeEventListener('change', update);
        };
    }, []);

    return (
        <>
            <Head title={`${user.name} (@${user.handle})`} />

            <div
                ref={headerRef}
                className="pointer-events-none sticky top-0 z-10 -mb-16 grid h-16 grid-cols-[4rem_minmax(0,1fr)] items-center border-b border-transparent pr-4 [view-transition-class:tab-header] [view-transition-name:profile-header] sm:grid-cols-[4.5rem_minmax(0,1fr)]"
                data-test="profile-sticky-header"
            >
                <div
                    aria-hidden
                    className="absolute inset-0 border-b bg-card"
                    style={{
                        opacity: 'var(--profile-header-progress, 0)',
                        pointerEvents: isHeaderVisible ? 'auto' : 'none',
                    }}
                />
                <div
                    ref={avatarTargetRef}
                    aria-hidden
                    className="size-10 justify-self-center"
                />
                <div
                    className="pointer-events-auto absolute top-0 left-0 flex origin-top-left [--profile-avatar-gutter:1rem] sm:[--profile-avatar-gutter:1.5rem]"
                    style={
                        {
                            translate:
                                'var(--profile-avatar-left, var(--profile-avatar-gutter)) var(--profile-avatar-top, 1.5rem)',
                            scale: 'var(--profile-avatar-scale, 1)',
                            '--lane': lane,
                        } as CSSProperties
                    }
                    data-test="profile-avatar"
                >
                    {user.live ? (
                        <LiveStreamTrigger
                            user={user}
                            showProfile={false}
                            className="flex"
                        >
                            <a
                                href={user.live.url}
                                target="_blank"
                                rel="noopener noreferrer"
                                aria-label={`Watch ${user.name} live on ${user.live.url.includes('twitch.tv') ? 'Twitch' : 'YouTube'}`}
                                className="relative flex rounded-full"
                                data-test="profile-live-avatar"
                            >
                                <UserAvatar
                                    user={user}
                                    className="size-20 text-2xl ring-[calc(2px/var(--profile-avatar-scale,1))] ring-red-600 ring-offset-4 ring-offset-card"
                                    pixels={160}
                                />
                                <LiveAvatarMarker className="bottom-[calc(-10px/var(--profile-avatar-scale,1))] origin-bottom [scale:calc(1/var(--profile-avatar-scale,1))] px-1 text-[9px] leading-3.5" />
                            </a>
                        </LiveStreamTrigger>
                    ) : (
                        <UserAvatar
                            user={user}
                            className="size-20 text-2xl ring-[calc(2px/var(--profile-avatar-scale,1))] ring-(--lane) ring-offset-4 ring-offset-card"
                            pixels={160}
                        />
                    )}
                </div>
                <div
                    aria-hidden={!isHeaderVisible}
                    className="relative min-w-0"
                    style={{
                        opacity: 'var(--profile-header-progress, 0)',
                        translate:
                            '0 calc(8px * (1 - var(--profile-header-progress, 0)))',
                    }}
                >
                    <p className="truncate leading-snug font-semibold">
                        {user.name}
                    </p>
                    <p className="truncate font-mono text-xs text-muted-foreground">
                        @{user.handle}
                    </p>
                </div>
            </div>

            <header className="border-b px-4 pt-6 pb-5 sm:px-6">
                <div className="flex items-start justify-between gap-4">
                    <div
                        ref={avatarRef}
                        aria-hidden
                        className="size-20 shrink-0"
                    />

                    <div className="flex shrink-0 gap-1.5 sm:gap-2">
                        <ProfileShareButton
                            handle={user.handle}
                            href={show(user).url}
                        />
                        {isCurrentUser ? (
                            <>
                                <ProfileLiveButton user={user} />
                                <EditProfileMenu />
                            </>
                        ) : (
                            <>
                                {moderation && (
                                    <UserModerationMenu
                                        user={user}
                                        moderation={moderation}
                                    />
                                )}
                                {auth.user && (
                                    <UserSafetyMenu
                                        user={user}
                                        isMuting={isMuting}
                                        isBlocking={false}
                                    />
                                )}
                                {canPush && (
                                    <Button
                                        variant="outline"
                                        className="sm:min-w-24"
                                        asChild
                                    >
                                        <Link
                                            href={ConversationController.show(
                                                user,
                                            )}
                                            aria-label={`Remote @${user.handle}`}
                                            data-test="profile-remote"
                                        >
                                            <MessagesSquare />
                                            <span className="hidden sm:inline">
                                                Remote
                                            </span>
                                        </Link>
                                    </Button>
                                )}
                                <FollowButton
                                    user={user}
                                    isFollowing={isFollowing}
                                    followsYou={followsYou}
                                    className="min-w-28"
                                    only={['auth', 'user', 'isFollowing']}
                                />
                            </>
                        )}
                    </div>
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
                        @{user.handle}
                        {followsYou && (
                            <span className="ml-1 shrink-0 rounded bg-muted px-1.5 py-0.5 font-sans text-xs">
                                Follows you
                            </span>
                        )}
                        {isMutingNow && (
                            <span className="ml-1 rounded bg-muted px-1.5 py-0.5 font-sans text-xs">
                                Muted
                            </span>
                        )}
                    </p>
                    <button
                        type="button"
                        onClick={() => setIsAchievementsOpen(true)}
                        className="inline-flex cursor-pointer items-center gap-1 rounded-md border px-2 py-0.5 font-mono text-[11px] font-medium transition hover:brightness-110"
                        style={{
                            borderColor: laneTint(lane, 25),
                            backgroundColor: laneTint(lane, 8),
                            color: lane,
                        }}
                        title="View achievements and Unix rank"
                    >
                        <span>
                            Lv. {achievements.level} · {achievements.rank}
                        </span>
                    </button>
                </div>

                {moderation && <UserModerationStatus moderation={moderation} />}

                {user.bio && (
                    <p className="mt-3 max-w-prose text-[15px] leading-relaxed wrap-break-word whitespace-pre-wrap">
                        <BioText bio={user.bio} profiles={bioProfiles} />
                    </p>
                )}

                <ul className="mt-3 flex flex-wrap gap-x-4 gap-y-1.5 text-sm text-muted-foreground">
                    {user.company && (
                        <li className="flex items-center gap-1.5">
                            <Building2 className="size-4" />
                            <span>
                                <BioText
                                    bio={user.company}
                                    profiles={bioProfiles}
                                />
                            </span>
                        </li>
                    )}
                    {user.location && (
                        <li className="flex items-center gap-1.5">
                            <MapPin className="size-4" />
                            {user.location}
                        </li>
                    )}
                    {user.show_local_time && user.timezone && (
                        <LocalTime timeZone={user.timezone} />
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
                                href={`https://github.com/${user.handle}`}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="text-primary hover:underline"
                            >
                                github.com/{user.handle}
                            </a>
                        </li>
                    )}
                    {user.has_sponsors_listing && (
                        <li className="flex items-center gap-1.5">
                            <Heart className="size-4" />
                            <a
                                href={`https://github.com/sponsors/${user.handle}`}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="text-primary hover:underline"
                            >
                                Sponsor on GitHub
                            </a>
                        </li>
                    )}
                    <li className="flex items-center gap-1.5">
                        <CalendarDays className="size-4" />
                        Joined {formatClockMonth(user.created_at)}
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
                            href: show(user),
                        },
                        {
                            count: user.following_count,
                            label: 'following',
                            href: UserFollowingController.index(user),
                            isForMembers: true,
                        },
                        {
                            count: followersCount,
                            label: pluralize(
                                followersCount,
                                'follower',
                                'followers',
                            ),
                            href: UserFollowersController.index(user),
                            isForMembers: true,
                        },
                    ].map(({ isForMembers, ...stat }) => {
                        const StatLink = isForMembers ? MembersLink : Link;

                        return (
                            <div
                                key={stat.label}
                                className="group flex flex-row-reverse gap-1.5"
                            >
                                <dt className="text-muted-foreground">
                                    <StatLink
                                        href={stat.href}
                                        className="group-hover:underline"
                                    >
                                        {stat.label}
                                    </StatLink>
                                </dt>
                                <dd className="font-mono font-medium">
                                    <StatLink
                                        href={stat.href}
                                        className="group-hover:underline"
                                        title={exactCountTitle(stat.count)}
                                    >
                                        {formatCount(stat.count)}
                                    </StatLink>
                                </dd>
                            </div>
                        );
                    })}
                </dl>
                {followersYouKnow && (
                    <FollowersYouKnow user={user} preview={followersYouKnow} />
                )}
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
                        <span
                            className="font-mono font-medium text-foreground tabular-nums"
                            title={exactCountTitle(pasteisCount)}
                        >
                            {formatCount(pasteisCount)}
                        </span>
                        {pluralize(pasteisCount, 'pastel', 'pastéis')} received
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

            <div className="min-h-dvh">
                <PageTabs
                    label="Profile feed"
                    preserveScroll
                    // So the sticky bar keeps its scrolled state instead of animating in again.
                    preserveState
                    only={['tab', 'commits', 'pinnedCommit', 'readme']}
                    reset={['commits']}
                    tabs={[
                        {
                            title: 'Commits',
                            href: show(user),
                            isActive: tab === 'commits',
                        },
                        {
                            title: 'Replies',
                            href: show(user, { query: { tab: 'replies' } }),
                            isActive: tab === 'replies',
                        },
                        {
                            title: 'README',
                            href: show(user, { query: { tab: 'readme' } }),
                            isActive: tab === 'readme',
                        },
                    ]}
                />

                {tab === 'readme' ? (
                    <ProfileReadme
                        readme={readme}
                        username={user.handle}
                        isCurrentUser={isCurrentUser}
                    />
                ) : (
                    <CommitFeed
                        key={`${user.id}:${tab}`}
                        commits={commits}
                        pinnedCommit={tab === 'commits' ? pinnedCommit : null}
                        threadActions={
                            isCurrentUser && tab === 'commits'
                                ? (commit) => (
                                      <PinCommitButton
                                          commit={commit}
                                          isPinned={
                                              commit.id === pinnedCommit?.id
                                          }
                                      />
                                  )
                                : undefined
                        }
                        highlightedPeriod={highlightedPeriod}
                        end={
                            tab === 'replies'
                                ? isCurrentUser
                                    ? 'That was your first reply.'
                                    : `That's @${user.handle}'s first reply.`
                                : isCurrentUser
                                  ? 'That was your first commit. Everyone starts somewhere.'
                                  : `That's @${user.handle}'s root commit.`
                        }
                        empty={
                            tab === 'replies' ? (
                                <EmptyFeed command="fatal: no replies yet">
                                    {isCurrentUser
                                        ? 'Your replies show up here. Join a conversation from your log.'
                                        : `@${user.handle} hasn't replied to anything yet.`}
                                </EmptyFeed>
                            ) : isCurrentUser ? (
                                <EmptyFeed command="fatal: you do not have any commits yet">
                                    Your commits show up here. Make your first
                                    one from your log.
                                </EmptyFeed>
                            ) : (
                                <EmptyFeed command="fatal: no commits yet">
                                    @{user.handle} hasn't committed anything
                                    yet.
                                </EmptyFeed>
                            )
                        }
                    />
                )}
            </div>
        </>
    );
}

Show.layout = AppLayout;
