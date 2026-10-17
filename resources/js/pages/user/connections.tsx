import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import UserFollowersController from '@/actions/App/Http/Controllers/UserFollowersController';
import UserFollowingController from '@/actions/App/Http/Controllers/UserFollowingController';
import { EmptyFeed } from '@/components/commit-feed';
import { PageTabs } from '@/components/page-tabs';
import type { ListedUser } from '@/components/user-list';
import { UserList } from '@/components/user-list';
import AppLayout from '@/layouts/app-layout';
import { show } from '@/routes/user';
import type { CursorPaginated, User } from '@/types';

export default function Connections({
    user,
    type,
    users,
}: {
    user: User;
    type: 'following' | 'followers';
    users: CursorPaginated<ListedUser>;
}) {
    const title = type === 'following' ? 'Following' : 'Followers';

    return (
        <>
            <Head title={`${title} · @${user.username}`} />

            <div className="sticky top-0 z-10 bg-card">
                <div className="flex h-14 items-center gap-3 border-b px-4 sm:px-6">
                    <Link
                        href={show(user)}
                        aria-label={`Back to @${user.username}`}
                        className="text-muted-foreground hover:text-foreground"
                    >
                        <ArrowLeft className="size-4" />
                    </Link>
                    <h1 className="min-w-0 truncate font-semibold">
                        {user.name}
                    </h1>
                    <p className="shrink-0 font-mono text-[13px] text-muted-foreground">
                        @{user.username}
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
                        {
                            title: 'Following',
                            href: UserFollowingController.index(user),
                            isActive: type === 'following',
                        },
                    ]}
                />
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
                <UserList data="users" users={users} />
            )}
        </>
    );
}

Connections.layout = AppLayout;
