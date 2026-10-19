import { Head, InfiniteScroll, Link } from '@inertiajs/react';
import { EmptyFeed } from '@/components/commit-feed';
import {
    LiveAvatarMarker,
    LiveStreamCard,
} from '@/components/live-stream-card';
import { Spinner } from '@/components/ui/spinner';
import { UserAvatar } from '@/components/user-avatar';
import AppLayout from '@/layouts/app-layout';
import { show } from '@/routes/user';
import type { CursorPaginated, User } from '@/types';

export default function Index({ users }: { users: CursorPaginated<User> }) {
    return (
        <>
            <Head title="Live" />

            <div className="sticky top-0 z-10 bg-card">
                <div className="flex h-14 items-center gap-3 border-b px-4 sm:px-6">
                    <h1 className="font-semibold">Live</h1>
                </div>
            </div>

            {users.data.length === 0 ? (
                <div className="px-6 py-16 text-center sm:px-12">
                    <EmptyFeed command="fatal: nobody is live">
                        Nobody is streaming right now.
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
                    <ul className="grid gap-6 p-4 sm:grid-cols-2 sm:p-6">
                        {users.data.map(
                            (person) =>
                                person.live && (
                                    <li key={person.id} data-test="live-user">
                                        <Link
                                            href={show(person)}
                                            className="mb-3 flex min-w-0 items-center gap-3"
                                        >
                                            <span className="relative shrink-0">
                                                <UserAvatar
                                                    user={person}
                                                    className="size-9 ring-2 ring-red-600 ring-offset-2 ring-offset-card"
                                                />
                                                <LiveAvatarMarker className="px-1 text-[9px] leading-3.5" />
                                            </span>
                                            <span className="min-w-0">
                                                <span className="block truncate font-medium">
                                                    {person.name}
                                                </span>
                                                <span className="block truncate font-mono text-sm text-muted-foreground">
                                                    @{person.username}
                                                </span>
                                            </span>
                                        </Link>
                                        <LiveStreamCard
                                            user={person}
                                            stream={person.live}
                                        />
                                    </li>
                                ),
                        )}
                    </ul>
                </InfiniteScroll>
            )}
        </>
    );
}

Index.layout = AppLayout;
