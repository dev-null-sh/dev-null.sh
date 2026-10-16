import { Head, Link, router, usePoll } from '@inertiajs/react';
import { ArrowUp } from 'lucide-react';
import { useEffect } from 'react';
import LogController from '@/actions/App/Http/Controllers/LogController';
import UserFollowController from '@/actions/App/Http/Controllers/UserFollowController';
import { CommitComposer } from '@/components/commit-composer';
import { CommitFeed, EmptyFeed } from '@/components/commit-feed';
import { LinkifiedText } from '@/components/linkified-text';
import { PageTabs } from '@/components/page-tabs';
import { Button } from '@/components/ui/button';
import { UserAvatar } from '@/components/user-avatar';
import { UserPreview } from '@/components/user-preview';
import { WhoToFollowInLog } from '@/components/who-to-follow';
import { useLastVisit } from '@/hooks/use-last-visit';
import { useUser } from '@/hooks/use-user';
import AppLayout from '@/layouts/app-layout';
import { emojify } from '@/lib/emoji';
import type { Commit, CursorPaginated, Log } from '@/types';

export default function Show({
    log,
    commits,
    newCommitsCount = 0,
    suggestions,
}: {
    log: Log;
    commits: CursorPaginated<Commit>;
    newCommitsCount?: number;
    suggestions: Commit[];
}) {
    const { user } = useUser();
    const newestId = commits.data[0]?.id;
    const firstSeenId = useLastVisit(user.id, log, commits.data);

    const { stop: stopPolling } = usePoll(
        30_000,
        () => ({
            only: ['newCommitsCount'],
            data: { since: newestId },
            preserveUrl: true,
        }),
        { autoStart: newestId !== undefined },
    );

    // Showing a log remembers it, so pages prefetched before it still carry the previous log in their shared props.
    useEffect(() => {
        router.flushAll();
    }, [log]);

    useEffect(
        () =>
            router.on('location', (event) => {
                if (event.detail.versionChange) {
                    stopPolling();
                }
            }),
        [stopPolling],
    );

    const showNewCommits = () =>
        router.reload({
            only: ['commits', 'newCommitsCount'],
            reset: ['commits'],
            data: { since: '' },
            preserveUrl: true,
            onSuccess: () => window.scrollTo({ top: 0, behavior: 'smooth' }),
        });

    return (
        <>
            <Head title={log === 'following' ? 'Following' : 'Everyone'} />

            <div className="sticky top-0 z-10 bg-card">
                <h1 className="sr-only">
                    {log === 'following' ? 'Following' : 'Everyone'}
                </h1>
                <PageTabs
                    label="Logs"
                    prefetch={false}
                    tabs={[
                        {
                            title: 'Following',
                            href: LogController.show('following'),
                            isActive: log === 'following',
                        },
                        {
                            title: 'Everyone',
                            href: LogController.show('everyone'),
                            isActive: log === 'everyone',
                        },
                    ]}
                />

                {newCommitsCount > 0 && (
                    <div className="pointer-events-none absolute inset-x-0 top-full flex justify-center pt-3">
                        <button
                            type="button"
                            onClick={showNewCommits}
                            className="pointer-events-auto flex items-center gap-1.5 rounded-full bg-primary px-4 py-1.5 text-sm font-medium text-primary-foreground shadow-[0_4px_14px_-4px_rgb(0_0_0/0.35)] transition-colors hover:bg-primary-hover motion-safe:animate-in motion-safe:fade-in motion-safe:slide-in-from-top-2"
                        >
                            <ArrowUp className="size-4" />
                            {newCommitsCount}{' '}
                            {newCommitsCount === 1
                                ? 'new commit'
                                : 'new commits'}
                        </button>
                    </div>
                )}
            </div>

            <CommitComposer />

            <CommitFeed
                commits={commits}
                firstLane="var(--rail)"
                firstSeenId={firstSeenId}
                end="That's the root commit. There's nothing before it."
                interlude={(lane, isDimmed) => (
                    <WhoToFollowInLog lane={lane} isDimmed={isDimmed} />
                )}
                empty={
                    log === 'following' ? (
                        <EmptyFeed command="fatal: your log does not have any commits yet">
                            Your log shows your commits and commits from people
                            you follow.{' '}
                            {suggestions.length > 0
                                ? 'Here is what a few developers committed lately:'
                                : 'Find someone worth following on '}
                            {suggestions.length === 0 && (
                                <>
                                    <Link
                                        href={LogController.show('everyone')}
                                        className="font-medium text-primary underline"
                                    >
                                        Everyone
                                    </Link>
                                    .
                                </>
                            )}
                            {suggestions.length > 0 && (
                                <Suggestions suggestions={suggestions} />
                            )}
                        </EmptyFeed>
                    ) : (
                        <EmptyFeed command="fatal: no commits yet">
                            Nobody has committed anything. Be the first. It's
                            280 characters; you'll be fine.
                        </EmptyFeed>
                    )
                }
            />
        </>
    );
}

function Suggestions({ suggestions }: { suggestions: Commit[] }) {
    return (
        <>
            <ul className="mt-5 divide-y rounded-lg border text-left">
                {suggestions.map((commit) => (
                    <li key={commit.id} className="flex items-center gap-3 p-3">
                        <UserPreview user={commit.user} className="shrink-0">
                            <UserAvatar user={commit.user} className="size-9" />
                        </UserPreview>
                        <div className="min-w-0 flex-1">
                            <p className="truncate text-sm">
                                <UserPreview user={commit.user}>
                                    <span className="font-semibold">
                                        {commit.user.name}
                                    </span>
                                </UserPreview>{' '}
                                <span className="font-mono text-[13px] text-muted-foreground">
                                    @{commit.user.username}
                                </span>
                            </p>
                            <p className="truncate text-sm text-muted-foreground">
                                <LinkifiedText text={emojify(commit.body)} />
                            </p>
                        </div>
                        <Button size="sm" asChild>
                            <Link
                                href={UserFollowController.store(commit.user)}
                                as="button"
                                preserveScroll
                                aria-label={`Follow @${commit.user.username}`}
                            >
                                Follow
                            </Link>
                        </Button>
                    </li>
                ))}
            </ul>
            <p className="mt-4 text-sm text-muted-foreground">
                Or browse{' '}
                <Link
                    href={LogController.show('everyone')}
                    className="font-medium text-primary underline"
                >
                    Everyone
                </Link>{' '}
                for more.
            </p>
        </>
    );
}

Show.layout = AppLayout;
