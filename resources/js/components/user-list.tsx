import { InfiniteScroll, Link, router, usePage } from '@inertiajs/react';
import type { CSSProperties } from 'react';
import { useState } from 'react';
import { FollowButton } from '@/components/follow-button';
import { Spinner } from '@/components/ui/spinner';
import { UserAvatar } from '@/components/user-avatar';
import { laneColor } from '@/lib/utils';
import { show } from '@/routes/user';
import type { CursorPaginated, User } from '@/types';

export type ListedUser = User & { is_followed: boolean };

function ListedUserFollowButton({ person }: { person: ListedUser }) {
    const [isFollowing, setIsFollowing] = useState(person.is_followed);

    return (
        <FollowButton
            user={person}
            isFollowing={isFollowing}
            size="sm"
            className="mt-4 min-w-24 shrink-0"
            only={['auth']}
            preserveState
            onSuccess={() => {
                setIsFollowing(!isFollowing);
                router.flushAll();
            }}
        />
    );
}

export function UserList({
    data,
    users,
}: {
    data: string;
    users: CursorPaginated<ListedUser>;
}) {
    const { auth } = usePage().props;

    return (
        <InfiniteScroll
            data={data}
            onlyNext
            preserveUrl
            loading={
                <div className="flex justify-center py-6">
                    <Spinner className="text-muted-foreground" />
                </div>
            }
        >
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
                                        '--lane': laneColor(person.lane),
                                    } as CSSProperties
                                }
                            />
                            <div className="min-w-0">
                                <p className="truncate font-medium">
                                    {person.name}
                                </p>
                                <p className="truncate font-mono text-sm text-muted-foreground">
                                    @{person.username}
                                </p>
                                {person.bio && (
                                    <p className="mt-1 line-clamp-2 text-sm">
                                        {person.bio}
                                    </p>
                                )}
                            </div>
                        </Link>
                        {person.id !== auth.user?.id && (
                            <ListedUserFollowButton person={person} />
                        )}
                    </li>
                ))}
            </ul>
        </InfiniteScroll>
    );
}
