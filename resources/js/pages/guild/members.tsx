import { Head, InfiniteScroll, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import type { CSSProperties } from 'react';
import { show } from '@/actions/App/Http/Controllers/GuildController';
import { EmptyFeed } from '@/components/commit-feed';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { UserAvatar } from '@/components/user-avatar';
import AppLayout from '@/layouts/app-layout';
import { laneColor } from '@/lib/utils';
import { show as profile } from '@/routes/user';
import type { User } from '@/types';
import type { Guild } from '@/types/guild';

export default function Members({
    guild,
    users,
}: {
    guild: Pick<Guild, 'handle' | 'name'>;
    users: {
        data: (Pick<
            User,
            'id' | 'handle' | 'name' | 'avatar_url' | 'bio' | 'lane'
        > & { is_moderator: boolean })[];
    };
}) {
    return (
        <>
            <Head title={`Members · ${guild.name}`} />
            <header className="sticky top-0 z-10 flex h-14 items-center gap-3 border-b bg-card px-4 sm:px-6">
                <Link
                    href={show(guild.handle)}
                    prefetch
                    aria-label={`Back to ${guild.name}`}
                    className="text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft className="size-4" />
                </Link>
                <h1
                    className="min-w-0 truncate font-semibold"
                    title={guild.name}
                >
                    {guild.name}
                </h1>
                <p className="shrink-0 text-sm text-muted-foreground">
                    Members
                </p>
            </header>
            {users.data.length === 0 ? (
                <div className="px-6 py-16 text-center sm:px-12">
                    <EmptyFeed command="fatal: no members yet">
                        Nobody has joined this guild yet.
                    </EmptyFeed>
                </div>
            ) : (
                <InfiniteScroll
                    data="users"
                    manual
                    onlyNext
                    preserveUrl
                    next={({ hasMore, loading, fetch }) =>
                        hasMore && (
                            <div className="flex justify-center py-4">
                                <Button
                                    variant="outline"
                                    onClick={fetch}
                                    disabled={loading}
                                >
                                    <Spinner
                                        className={loading ? '' : 'invisible'}
                                    />
                                    Load more
                                </Button>
                            </div>
                        )
                    }
                >
                    <ul className="divide-y">
                        {users.data.map((person) => (
                            <li key={person.id}>
                                <Link
                                    href={profile(person)}
                                    prefetch
                                    className="flex min-w-0 items-start gap-3 px-4 py-3 hover:bg-muted/50 sm:px-6"
                                >
                                    <UserAvatar
                                        user={person}
                                        className="size-10 shrink-0 ring-2 ring-(--lane) ring-offset-2 ring-offset-card"
                                        style={
                                            {
                                                '--lane': laneColor(
                                                    person.lane,
                                                ),
                                            } as CSSProperties
                                        }
                                    />
                                    <div className="min-w-0">
                                        <div className="flex min-w-0 items-baseline gap-1">
                                            <p
                                                className="truncate font-medium"
                                                title={person.name}
                                            >
                                                {person.name}
                                            </p>
                                            {person.is_moderator && (
                                                <span className="shrink-0 text-sm text-muted-foreground">
                                                    (moderator)
                                                </span>
                                            )}
                                        </div>
                                        <p
                                            className="truncate font-mono text-sm text-muted-foreground"
                                            title={`@${person.handle}`}
                                        >
                                            @{person.handle}
                                        </p>
                                        {person.bio && (
                                            <p className="mt-1 line-clamp-2 text-sm wrap-anywhere">
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

Members.layout = AppLayout;
