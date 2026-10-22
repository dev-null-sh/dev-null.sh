import { Head, Link, router, usePage, usePoll } from '@inertiajs/react';
import { ArrowUp } from 'lucide-react';
import { useEffect, useLayoutEffect, useRef, useState } from 'react';
import LogController from '@/actions/App/Http/Controllers/LogController';
import { CommitComposer } from '@/components/commit-composer';
import { useCommitDialogClosedCount } from '@/components/commit-composer-dialog';
import { CommitFeed, EmptyFeed } from '@/components/commit-feed';
import { FollowButton } from '@/components/follow-button';
import { LinkifiedText } from '@/components/linkified-text';
import { PageTabs } from '@/components/page-tabs';
import { UserAvatar } from '@/components/user-avatar';
import { UserPreview } from '@/components/user-preview';
import { WhoToFollowInLog } from '@/components/who-to-follow';
import { useComposerVisibility } from '@/hooks/use-composer-visibility';
import { useLaneSwipe } from '@/hooks/use-lane-swipe';
import { useLastVisit } from '@/hooks/use-last-visit';
import AppLayout from '@/layouts/app-layout';
import { backgroundPollOptions } from '@/lib/background-poll';
import { emojify } from '@/lib/emoji';
import type { Commit, CursorPaginated, Log } from '@/types';

export default function Show({
    log,
    commits,
    isRanked,
    newCommitsCount = 0,
    suggestions,
}: {
    log: Log;
    commits: CursorPaginated<Commit>;
    isRanked: boolean;
    newCommitsCount?: number;
    suggestions: Commit[];
}) {
    const {
        props: { auth },
    } = usePage();
    const user = auth.user;
    const commitDialogClosedCount = useCommitDialogClosedCount();
    const viewerId = user?.id ?? 'guest';
    const newestId = isRanked ? undefined : commits.data[0]?.id;
    const tabsRef = useRef<HTMLDivElement>(null);
    const [tabsHeight, setTabsHeight] = useState(0);
    const feedRef = useRef<HTMLDivElement>(null);
    const followAnchor = useRef<{ row: HTMLElement; top: number } | null>(null);
    const [composer, setComposer] = useState<HTMLDivElement | null>(null);
    useLaneSwipe(feedRef, log);
    useComposerVisibility(composer, tabsHeight);

    useEffect(() => {
        const tabs = tabsRef.current;

        if (!tabs) {
            return;
        }

        const updateHeight = () =>
            setTabsHeight(tabs.getBoundingClientRect().height);
        updateHeight();

        const observer = new ResizeObserver(updateHeight);
        observer.observe(tabs);

        return () => observer.disconnect();
    }, []);

    useEffect(
        () =>
            router.on('beforeUpdate', ({ detail: { page } }) => {
                if (
                    log !== 'following' ||
                    page.component !== 'log/show' ||
                    !page.scrollProps?.commits?.reset
                ) {
                    return;
                }

                const incoming = page.props.commits as CursorPaginated<Commit>;
                followAnchor.current = null;

                if (incoming === commits) {
                    return;
                }

                const ids = new Set(incoming.data.map((commit) => commit.id));
                const headerBottom =
                    tabsRef.current?.getBoundingClientRect().bottom ?? 0;
                const rows =
                    feedRef.current?.querySelectorAll<HTMLElement>(
                        '[data-commit]',
                    ) ?? [];
                const row = Array.from(rows).find(
                    (row) =>
                        ids.has(row.dataset.commit ?? '') &&
                        row.getBoundingClientRect().bottom > headerBottom,
                );
                followAnchor.current = row
                    ? { row, top: row.getBoundingClientRect().top }
                    : null;
            }),
        [log, commits],
    );

    useLayoutEffect(() => {
        const anchor = followAnchor.current;
        followAnchor.current = null;

        if (anchor?.row.isConnected) {
            window.scrollBy(
                0,
                anchor.row.getBoundingClientRect().top - anchor.top,
            );
        }
    }, [commits]);

    const firstSeenId = useLastVisit(viewerId, log, commits.data);

    const { stop: stopPolling } = usePoll(
        30_000,
        backgroundPollOptions(() => ({
            only: ['newCommitsCount'],
            data: { since: newestId },
            preserveUrl: true,
        })),
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

    const reloadNewestFirst = () =>
        router.reload({
            only: ['commits', 'newCommitsCount'],
            reset: ['commits'],
            data: { since: '' },
            preserveUrl: true,
            onSuccess: () => window.scrollTo({ top: 0, behavior: 'smooth' }),
        });

    return (
        <>
            <Head title={titles[log]} />

            <div
                ref={tabsRef}
                className="sticky top-0 z-10 bg-card [view-transition-class:tab-header] [view-transition-name:log-header]"
            >
                <h1 className="sr-only">{titles[log]}</h1>
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
                            title: 'Latest',
                            href: LogController.show('latest'),
                            isActive: log === 'latest',
                        },
                        {
                            title: 'Trending',
                            href: LogController.show('trending'),
                            isActive: log === 'trending',
                        },
                    ]}
                />
            </div>

            {user !== null && (
                <div ref={setComposer}>
                    <CommitComposer key={commitDialogClosedCount} />
                </div>
            )}

            {newCommitsCount > 0 && (
                <div
                    className="pointer-events-none sticky z-10 flex h-0 items-start justify-center"
                    style={{ top: tabsHeight }}
                >
                    <button
                        type="button"
                        onClick={reloadNewestFirst}
                        className="pointer-events-auto mt-3 flex items-center gap-1.5 rounded-md bg-primary px-4 py-1.5 text-sm font-medium text-primary-foreground shadow-[0_4px_14px_-4px_rgb(0_0_0/0.35)] transition-colors hover:bg-primary-hover motion-safe:animate-in motion-safe:fade-in motion-safe:slide-in-from-top-2"
                    >
                        <ArrowUp className="size-4" />
                        {newCommitsCount}{' '}
                        {newCommitsCount === 1 ? 'new commit' : 'new commits'}
                    </button>
                </div>
            )}

            <div
                ref={feedRef}
                className="[view-transition-class:tab-content] [view-transition-name:log-feed]"
            >
                <CommitFeed
                    commits={commits}
                    firstSeenId={firstSeenId}
                    isRanked={isRanked}
                    isBelowComposer={user !== null}
                    end="That's the root commit. There's nothing before it."
                    interlude={() => <WhoToFollowInLog />}
                    empty={
                        log === 'following' ? (
                            <EmptyFeed command="fatal: your log does not have any commits yet">
                                Your log shows your commits and commits from
                                people you follow.{' '}
                                {suggestions.length > 0
                                    ? 'Here is what a few developers committed lately:'
                                    : 'Find someone worth following on '}
                                {suggestions.length === 0 && (
                                    <>
                                        <Link
                                            href={LogController.show(
                                                'trending',
                                            )}
                                            className="font-medium text-primary underline"
                                        >
                                            Trending
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
                                Nobody has committed anything. Be the first.
                                It's just plain text; you'll be fine.
                            </EmptyFeed>
                        )
                    }
                />
            </div>
        </>
    );
}

const titles: Record<Log, string> = {
    following: 'Following',
    latest: 'Latest',
    trending: 'Trending',
};

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
                                <LinkifiedText
                                    text={emojify(commit.body)}
                                    mentions={commit.mentions}
                                />
                            </p>
                        </div>
                        <FollowButton
                            user={commit.user}
                            isFollowing={false}
                            size="sm"
                            className="min-w-24 shrink-0"
                        />
                    </li>
                ))}
            </ul>
            <p className="mt-4 text-sm text-muted-foreground">
                Or browse{' '}
                <Link
                    href={LogController.show('trending')}
                    className="font-medium text-primary underline"
                >
                    Trending
                </Link>{' '}
                for more.
            </p>
        </>
    );
}

Show.layout = AppLayout;
