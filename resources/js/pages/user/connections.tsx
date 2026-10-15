import { Head, InfiniteScroll, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import type { CSSProperties } from 'react';
import { EmptyFeed } from '@/components/commit-feed';
import { Spinner } from '@/components/ui/spinner';
import { UserAvatar } from '@/components/user-avatar';
import AppLayout from '@/layouts/app-layout';
import { laneFor } from '@/lib/utils';
import { show } from '@/routes/user';
import type { CursorPaginated, User } from '@/types';

export default function Connections({
    user,
    type,
    users,
}: {
    user: User;
    type: 'following' | 'followers';
    users: CursorPaginated<User>;
}) {
    const title = type === 'following' ? 'Following' : 'Followers';

    return (
        <>
            <Head title={`${title} · @${user.username}`} />

            <div className="sticky top-0 z-10 flex h-14 items-center gap-3 border-b bg-card px-4">
                <Link
                    href={show(user)}
                    aria-label={`Back to @${user.username}`}
                    className="text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft className="size-4" />
                </Link>
                <p className="min-w-0 truncate font-semibold">{user.name}</p>
                <p className="shrink-0 font-mono text-[13px] text-muted-foreground">
                    {title.toLowerCase()}
                </p>
            </div>

            {users.data.length === 0 ? (
                <div className="px-6 py-16 text-center sm:px-12">
                    <EmptyFeed command={`fatal: no ${type} yet`}>
                        {type === 'following'
                            ? `@${user.username} isn't following anyone yet.`
                            : `Nobody is following @${user.username} yet.`}
                    </EmptyFeed>
                </div>
            ) : (
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
                    <ul className="divide-y">
                        {users.data.map((person) => (
                            <li key={person.id}>
                                <Link
                                    href={show(person)}
                                    className="flex items-start gap-3 px-4 py-3 hover:bg-muted/50 sm:px-6"
                                >
                                    <UserAvatar
                                        user={person}
                                        className="size-10 ring-2 ring-(--lane) ring-offset-2 ring-offset-card"
                                        style={
                                            {
                                                '--lane': laneFor(person.id),
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
                            </li>
                        ))}
                    </ul>
                </InfiniteScroll>
            )}
        </>
    );
}

Connections.layout = AppLayout;
