import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import CommitController from '@/actions/App/Http/Controllers/CommitController';
import { EmptyFeed } from '@/components/commit-feed';
import type { ListedUser } from '@/components/user-list';
import { UserList } from '@/components/user-list';
import AppLayout from '@/layouts/app-layout';
import { shortHash } from '@/lib/utils';
import type { CursorPaginated, User } from '@/types';

export default function Stargazers({
    commit,
    users,
}: {
    commit: { id: string; body: string; user: User; stars_count: number };
    users: CursorPaginated<ListedUser>;
}) {
    const hash = shortHash(commit.id);

    return (
        <>
            <Head title={`Stargazers · ${hash}`} />

            <div className="sticky top-0 z-10 bg-card">
                <div className="flex h-14 items-center gap-3 border-b px-4 sm:px-6">
                    <Link
                        href={CommitController.show({
                            user: commit.user,
                            commit,
                        })}
                        aria-label={`Back to commit ${hash}`}
                        className="text-muted-foreground hover:text-foreground"
                    >
                        <ArrowLeft className="size-4" />
                    </Link>
                    <h1 className="min-w-0 truncate font-semibold">
                        Stargazers
                    </h1>
                    <p className="ml-auto shrink-0 font-mono text-[13px] text-muted-foreground">
                        {commit.stars_count}{' '}
                        {commit.stars_count === 1 ? 'star' : 'stars'} · {hash}
                    </p>
                </div>
                <p className="truncate border-b px-4 py-2.5 text-sm text-muted-foreground sm:px-6">
                    <span className="font-medium text-foreground">
                        {commit.user.name}
                    </span>
                    : {commit.body}
                </p>
            </div>

            {users.data.length === 0 ? (
                <div className="px-6 py-16 text-center sm:px-12">
                    <EmptyFeed command="fatal: no stargazers yet">
                        Nobody has starred this commit yet.
                    </EmptyFeed>
                </div>
            ) : (
                <UserList data="users" users={users} />
            )}
        </>
    );
}

Stargazers.layout = AppLayout;
