import { Head } from '@inertiajs/react';
import { PostFeed } from '@/components/post-feed';
import AppLayout from '@/layouts/app-layout';
import type { CursorPaginated, Post, Topic } from '@/types';

export default function Show({
    topic,
    posts,
}: {
    topic: Topic;
    posts: CursorPaginated<Post>;
}) {
    return (
        <>
            <Head title={`#${topic.name}`} />

            <div className="mx-auto w-full max-w-2xl border-x border-sidebar-border/70 dark:border-sidebar-border">
                <header className="border-b border-sidebar-border/70 px-4 py-6 dark:border-sidebar-border">
                    <h1 className="text-xl font-bold">#{topic.name}</h1>
                    <p className="text-sm text-muted-foreground">
                        {topic.posts_count}{' '}
                        {topic.posts_count === 1 ? 'Post' : 'Posts'}
                    </p>
                </header>

                <PostFeed
                    posts={posts}
                    empty={`Nobody has posted in #${topic.name} yet.`}
                />
            </div>
        </>
    );
}

Show.layout = [AppLayout, {}];
