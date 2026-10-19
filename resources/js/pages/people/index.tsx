import { Head, InfiniteScroll } from '@inertiajs/react';
import { EmptyFeed } from '@/components/commit-feed';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { SuggestedUserRow } from '@/components/who-to-follow';
import AppLayout from '@/layouts/app-layout';
import type { CursorPaginated, SuggestedUser } from '@/types';

export default function People({
    users,
}: {
    users: CursorPaginated<SuggestedUser>;
}) {
    return (
        <>
            <Head title="Who to follow" />

            <header className="border-b px-4 py-5 sm:px-6">
                <h1 className="text-xl font-semibold">Who to follow</h1>
                <p className="mt-2 text-sm text-pretty text-muted-foreground">
                    Developers with public commits, newest members first. Follow
                    anyone who catches your eye to see their commits in your
                    log.
                </p>
            </header>

            {users.data.length === 0 ? (
                <div className="px-6 py-16 text-center sm:px-12">
                    <EmptyFeed command="who: no new developers to suggest">
                        You're all caught up. Check back as more developers join
                        and start committing.
                    </EmptyFeed>
                </div>
            ) : (
                <InfiniteScroll
                    data="users"
                    onlyNext
                    preserveUrl
                    manual
                    next={({ loading, fetch, hasMore }) =>
                        hasMore ? (
                            <div className="flex justify-center border-t py-6">
                                <Button
                                    variant="outline"
                                    onClick={fetch}
                                    disabled={loading}
                                    data-test="people-load-more"
                                >
                                    {loading && <Spinner />}
                                    {loading
                                        ? 'Loading…'
                                        : 'Show more developers'}
                                </Button>
                            </div>
                        ) : (
                            <p className="border-t px-4 py-6 text-center text-sm text-muted-foreground">
                                You've reached the end. Check back for more
                                developers.
                            </p>
                        )
                    }
                >
                    <ul className="divide-y" data-test="people-list">
                        {users.data.map((person) => (
                            <SuggestedUserRow
                                key={person.id}
                                user={person}
                                className="px-4 py-4 sm:px-6"
                                showsBio
                            />
                        ))}
                    </ul>
                </InfiniteScroll>
            )}
        </>
    );
}

People.layout = AppLayout;
