import { Head } from '@inertiajs/react';
import { CommitFeed, EmptyFeed } from '@/components/commit-feed';
import AppLayout from '@/layouts/app-layout';
import type { Commit, CursorPaginated, Topic } from '@/types';

export default function Show({
    topic,
    commits,
}: {
    topic: Topic;
    commits: CursorPaginated<Commit>;
}) {
    return (
        <>
            <Head title={topic.name} />

            <div className="sticky top-0 z-10 flex h-14 items-center gap-3 border-b bg-card px-4">
                <h1 className="min-w-0 truncate font-semibold">{topic.name}</h1>
                <p className="shrink-0 font-mono text-[13px] text-muted-foreground">
                    {topic.commits_count}{' '}
                    {topic.commits_count === 1 ? 'commit' : 'commits'}
                </p>
            </div>

            <CommitFeed
                commits={commits}
                firstLane="var(--rail)"
                end={`That's the first commit about ${topic.name}.`}
                empty={
                    <EmptyFeed
                        command={`fatal: no commits about ${topic.name}`}
                    >
                        Nobody has committed about {topic.name} yet.
                    </EmptyFeed>
                }
            />
        </>
    );
}

Show.layout = AppLayout;
