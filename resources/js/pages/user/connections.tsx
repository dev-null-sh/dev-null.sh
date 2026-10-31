import { Head, InfiniteScroll, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import type { CSSProperties } from 'react';
import UserFollowersController from '@/actions/App/Http/Controllers/UserFollowersController';
import UserFollowersYouKnowController from '@/actions/App/Http/Controllers/UserFollowersYouKnowController';
import UserFollowingController from '@/actions/App/Http/Controllers/UserFollowingController';
import UserMutualsController from '@/actions/App/Http/Controllers/UserMutualsController';
import { EmptyFeed } from '@/components/commit-feed';
import { FollowButton } from '@/components/follow-button';
import { OrganizationAvatar } from '@/components/organization-avatar';
import { OrganizationFollowButton } from '@/components/organization-follow-button';
import { PageTabs } from '@/components/page-tabs';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { UserAvatar } from '@/components/user-avatar';
import AppLayout from '@/layouts/app-layout';
import { laneColor } from '@/lib/utils';
import { show } from '@/routes/user';
import type { CursorPaginated, Organization, User } from '@/types';

type Connection = User & { is_followed: boolean };

type FollowedOrganization = Organization & {
    id: string;
    is_followed: boolean;
};

type ConnectionType =
    | 'followers'
    | 'followers_you_know'
    | 'following'
    | 'mutuals';

const titles: Record<ConnectionType, string> = {
    followers: 'Followers',
    followers_you_know: 'Followers you know',
    following: 'Following',
    mutuals: 'Mutuals',
};

function ConnectionFollowButton({ person }: { person: Connection }) {
    return (
        <FollowButton
            user={person}
            isFollowing={person.is_followed}
            size="sm"
            className="mt-4 min-w-24 shrink-0"
            only={['auth']}
            preserveState
            onSuccess={() => router.flushAll()}
        />
    );
}

export default function Connections({
    user,
    type,
    users,
    organizations,
}: {
    user: User;
    type: ConnectionType;
    users: CursorPaginated<Connection>;
    /**
     * Only on the Following list, above the people.
     */
    organizations?: CursorPaginated<FollowedOrganization>;
}) {
    const { auth } = usePage().props;
    const title = titles[type];
    const knowsFollowers = auth.user !== null && auth.user.id !== user.id;
    const emptyMessages: Record<ConnectionType, string> = {
        followers: `Nobody is following @${user.handle} yet.`,
        followers_you_know: `Nobody you follow is following @${user.handle} yet.`,
        following: `@${user.handle} isn't following anyone yet.`,
        mutuals: `@${user.handle} has no mutuals yet.`,
    };

    return (
        <>
            <Head title={`${title} · @${user.handle}`} />

            <div className="sticky top-0 z-10 bg-card">
                <div className="flex h-14 items-center gap-3 border-b px-4 sm:px-6">
                    <Link
                        href={show(user)}
                        aria-label={`Back to @${user.handle}`}
                        className="text-muted-foreground hover:text-foreground"
                    >
                        <ArrowLeft className="size-4" />
                    </Link>
                    <h1 className="min-w-0 truncate font-semibold">
                        {user.name}
                    </h1>
                    <p className="shrink-0 font-mono text-[13px] text-muted-foreground">
                        @{user.handle}
                    </p>
                </div>
                <PageTabs
                    label="Connections"
                    tabs={[
                        {
                            title: 'Followers',
                            href: UserFollowersController.index(user),
                            isActive: type === 'followers',
                        },
                        ...(knowsFollowers
                            ? [
                                  {
                                      title: 'Followers you know',
                                      shortTitle: 'You know',
                                      href: UserFollowersYouKnowController.index(
                                          user,
                                      ),
                                      isActive: type === 'followers_you_know',
                                  },
                              ]
                            : []),
                        {
                            title: 'Following',
                            href: UserFollowingController.index(user),
                            isActive: type === 'following',
                        },
                        {
                            title: 'Mutuals',
                            href: UserMutualsController.index(user),
                            isActive: type === 'mutuals',
                        },
                    ]}
                />
            </div>

            {organizations !== undefined && organizations.data.length > 0 && (
                <section
                    aria-labelledby="followed-organizations"
                    className="border-b"
                >
                    <h2
                        id="followed-organizations"
                        className="px-4 pt-4 pb-1 text-sm font-medium text-muted-foreground sm:px-6"
                    >
                        Organizations
                    </h2>
                    <InfiniteScroll
                        data="organizations"
                        manual
                        onlyNext
                        preserveUrl
                        next={({ hasMore, loading, fetch }) =>
                            hasMore && (
                                <div className="px-4 pb-3 sm:px-6">
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        className="-ml-3"
                                        onClick={fetch}
                                        disabled={loading}
                                    >
                                        {loading && <Spinner />}
                                        Show more organizations
                                    </Button>
                                </div>
                            )
                        }
                    >
                        <ul className="divide-y">
                            {organizations.data.map((organization) => (
                                <li
                                    key={organization.id}
                                    className="flex items-start gap-3 pr-4 hover:bg-muted/50 sm:pr-6"
                                >
                                    <Link
                                        href={show(organization.handle)}
                                        className="flex min-w-0 flex-1 items-start gap-3 py-3 pl-4 sm:pl-6"
                                    >
                                        <OrganizationAvatar
                                            organization={organization}
                                            className="size-10"
                                        />
                                        <div className="min-w-0">
                                            <p className="truncate font-medium">
                                                {organization.name}
                                            </p>
                                            <p className="truncate font-mono text-sm text-muted-foreground">
                                                @{organization.handle}
                                            </p>
                                            {organization.bio && (
                                                <p className="mt-1 line-clamp-2 text-sm">
                                                    {organization.bio}
                                                </p>
                                            )}
                                        </div>
                                    </Link>
                                    <OrganizationFollowButton
                                        organization={organization}
                                        isFollowing={organization.is_followed}
                                        size="sm"
                                        className="mt-4 min-w-24 shrink-0"
                                    />
                                </li>
                            ))}
                        </ul>
                    </InfiniteScroll>
                </section>
            )}

            {users.data.length === 0 && !organizations?.data.length ? (
                <div className="px-6 py-16 text-center sm:px-12">
                    <EmptyFeed
                        command={
                            type === 'followers_you_know'
                                ? 'fatal: no followers you know'
                                : `fatal: no ${type} yet`
                        }
                    >
                        {emptyMessages[type]}
                    </EmptyFeed>
                </div>
            ) : users.data.length === 0 ? null : (
                <InfiniteScroll
                    data="users"
                    onlyNext
                    preserveUrl
                    loading={
                        <div className="flex justify-center py-6">
                            <Spinner className="text-muted-foreground" />
                        </div>
                    }
                >
                    {Boolean(organizations?.data.length) && (
                        <h2 className="px-4 pt-4 pb-1 text-sm font-medium text-muted-foreground sm:px-6">
                            People
                        </h2>
                    )}
                    <ul className="divide-y">
                        {users.data.map((person) => (
                            <li
                                key={person.id}
                                className="flex items-start gap-3 pr-4 hover:bg-muted/50 sm:pr-6"
                            >
                                <Link
                                    href={show(person)}
                                    className="flex min-w-0 flex-1 items-start gap-3 py-3 pl-4 sm:pl-6"
                                >
                                    <UserAvatar
                                        user={person}
                                        className="size-10 ring-2 ring-(--lane) ring-offset-2 ring-offset-card"
                                        style={
                                            {
                                                '--lane': laneColor(
                                                    person.lane,
                                                ),
                                            } as CSSProperties
                                        }
                                    />
                                    <div className="min-w-0">
                                        <p className="truncate font-medium">
                                            {person.name}
                                        </p>
                                        <p className="truncate font-mono text-sm text-muted-foreground">
                                            @{person.handle}
                                        </p>
                                        {person.bio && (
                                            <p className="mt-1 line-clamp-2 text-sm">
                                                {person.bio}
                                            </p>
                                        )}
                                    </div>
                                </Link>
                                {person.id !== auth.user?.id && (
                                    <ConnectionFollowButton person={person} />
                                )}
                            </li>
                        ))}
                    </ul>
                </InfiniteScroll>
            )}
        </>
    );
}

Connections.layout = AppLayout;
