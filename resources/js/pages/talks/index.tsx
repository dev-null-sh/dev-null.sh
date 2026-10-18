import { Head, InfiniteScroll } from '@inertiajs/react';
import { EmptyFeed } from '@/components/commit-feed';
import { TalkCard } from '@/components/talk-card';
import AppLayout from '@/layouts/app-layout';
import type { CursorPaginated, TalkSummary } from '@/types';

type ExplorerTalk = TalkSummary & {
    user: {
        id: string;
        username: string;
        name: string;
        avatar_url: string | null;
        lane: number | string;
    };
};

export default function Index({
    talks,
}: {
    talks: CursorPaginated<ExplorerTalk>;
}) {
    return (
        <>
            <Head title="Talks" />
            <div className="flex h-14 items-center border-b px-4">
                <h1 className="font-semibold">Talks</h1>
            </div>

            {talks.data.length === 0 ? (
                <div className="px-6 py-16 text-center sm:px-12">
                    <EmptyFeed command="fatal: no talks yet">
                        Explore public talks from developers. Manage yours from
                        the Talks tab on your profile.
                    </EmptyFeed>
                </div>
            ) : (
                <InfiniteScroll
                    data="talks"
                    onlyNext
                    preserveUrl
                    loading={
                        <div
                            role="status"
                            aria-label="Loading more talks"
                            className="px-5 py-6 text-sm text-muted-foreground sm:px-6"
                        >
                            Loading…
                        </div>
                    }
                >
                    {talks.data.map((talk) => (
                        <TalkCard key={talk.id} talk={talk} user={talk.user} />
                    ))}
                </InfiniteScroll>
            )}
        </>
    );
}

Index.layout = AppLayout;
