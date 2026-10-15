import { Head, Link, router } from '@inertiajs/react';
import TagFollowController from '@/actions/App/Http/Controllers/TagFollowController';
import { CommitFeed, EmptyFeed } from '@/components/commit-feed';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { Commit, CursorPaginated, Tag } from '@/types';

function pluralize(count: number, singular: string, plural: string) {
    return count === 1 ? singular : plural;
}

export default function Show({
    tag,
    commitsCount,
    followersCount,
    isFollowing,
    commits,
}: {
    tag: Tag;
    commitsCount: number;
    followersCount: number;
    isFollowing: boolean;
    commits: CursorPaginated<Commit>;
}) {
    return (
        <>
            <Head title={`#${tag.name}`} />

            <div className="sticky top-0 z-10 flex h-14 items-center gap-3 border-b bg-card px-4">
                <p className="min-w-0 truncate font-semibold">#{tag.name}</p>
                <p className="shrink-0 font-mono text-[13px] text-muted-foreground">
                    {commitsCount}{' '}
                    {pluralize(commitsCount, 'commit', 'commits')}
                </p>
            </div>

            <header className="border-b px-4 pt-6 pb-5 sm:px-6">
                <div className="flex items-start justify-between gap-4">
                    <h1 className="min-w-0 pt-1 text-2xl font-semibold tracking-tight [overflow-wrap:anywhere]">
                        <span aria-hidden className="text-muted-foreground">
                            #
                        </span>
                        <span className="sr-only">Tag </span>
                        {tag.name}
                    </h1>

                    <Button
                        variant={isFollowing ? 'outline' : 'default'}
                        className="group/follow min-w-28 shrink-0"
                        asChild
                    >
                        <Link
                            href={
                                isFollowing
                                    ? TagFollowController.destroy(tag)
                                    : TagFollowController.store(tag)
                            }
                            as="button"
                            preserveScroll
                            aria-label={
                                isFollowing
                                    ? `Unfollow #${tag.name}`
                                    : `Follow #${tag.name}`
                            }
                            onSuccess={() => router.flushAll()}
                            data-test="tag-follow-button"
                        >
                            {isFollowing ? (
                                <>
                                    <span className="group-hover/follow:hidden">
                                        Following
                                    </span>
                                    <span className="hidden text-destructive group-hover/follow:inline">
                                        Unfollow
                                    </span>
                                </>
                            ) : (
                                'Follow'
                            )}
                        </Link>
                    </Button>
                </div>

                <p className="mt-2 max-w-prose text-[15px] text-pretty text-muted-foreground">
                    {isFollowing
                        ? `Commits tagged #${tag.name} show up in your log.`
                        : `Follow #${tag.name} to see its commits in your log.`}
                </p>

                <dl className="mt-4 flex gap-5 text-sm">
                    {[
                        {
                            count: commitsCount,
                            label: pluralize(commitsCount, 'commit', 'commits'),
                        },
                        {
                            count: followersCount,
                            label: pluralize(
                                followersCount,
                                'follower',
                                'followers',
                            ),
                        },
                    ].map((stat) => (
                        <div
                            key={stat.label}
                            className="flex flex-row-reverse gap-1.5"
                        >
                            <dt className="text-muted-foreground">
                                {stat.label}
                            </dt>
                            <dd className="font-mono font-medium">
                                {stat.count}
                            </dd>
                        </div>
                    ))}
                </dl>
            </header>

            <CommitFeed
                key={tag.name}
                commits={commits}
                end={`That's the first commit tagged #${tag.name}.`}
                empty={
                    <EmptyFeed
                        command={`fatal: no commits tagged '${tag.name}' yet`}
                    >
                        Nothing here is tagged #{tag.name} yet. Add it to a
                        commit and it shows up here.
                    </EmptyFeed>
                }
            />
        </>
    );
}

Show.layout = AppLayout;
