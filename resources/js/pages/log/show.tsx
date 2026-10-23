import { Head, Link, router, usePage, usePoll } from '@inertiajs/react';
import { ArrowUp, LoaderCircle } from 'lucide-react';
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
import { cn } from '@/lib/utils';
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
    const autoLoads = log === 'latest';
    const [isFetching, setIsFetching] = useState(false);
    const [announced, setAnnounced] = useState(0);
    const [loaded, setLoaded] = useState<'shown' | 'leaving' | null>(null);
    const [isAway, setIsAway] = useState(false);
    const [stateLog, setStateLog] = useState(log);
    const [waiting, setWaiting] = useState(0);
    const loadedTimers = useRef<ReturnType<typeof setTimeout>[]>([]);
    const seenFresh = useRef<() => void>(() => undefined);
    const fetchTimer = useRef<ReturnType<typeof setTimeout>>(undefined);
    const loadingNew = useRef(false);
    const freshIds = useRef<Set<string>>(new Set());

    // Another log has its own commits to announce, so nothing of the last one's pill carries over.
    if (stateLog !== log) {
        setStateLog(log);
        setIsFetching(false);
        setLoaded(null);
        setWaiting(0);
    }

    const commitDialogClosedCount = useCommitDialogClosedCount();
    const viewerId = user?.id ?? 'guest';
    const newestId = isRanked ? undefined : commits.data[0]?.id;
    const oldestLoadedId = commits.data.at(-1)?.id;
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
                    (log !== 'following' && !loadingNew.current) ||
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

                if (loadingNew.current) {
                    const known = new Set(commits.data.map((c) => c.id));
                    freshIds.current = new Set([
                        ...freshIds.current,
                        ...incoming.data
                            .map((commit) => commit.id)
                            .filter((id) => !known.has(id)),
                    ]);

                    // Someone reading further down keeps their place, while someone at the top watches the commits arrive.
                    if (window.scrollY < 40) {
                        return;
                    }
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

    useLayoutEffect(() => {
        const ids = freshIds.current;

        if (ids.size === 0) {
            return;
        }

        const rows = Array.from(
            feedRef.current?.querySelectorAll<HTMLElement>('[data-commit]') ??
                [],
        ).filter((row) => ids.has(row.dataset.commit ?? ''));
        const reduced = window.matchMedia(
            '(prefers-reduced-motion: reduce)',
        ).matches;

        rows.filter((row) => row.dataset.fresh === undefined).forEach(
            (row, index) => {
                row.dataset.fresh = '';

                if (!reduced) {
                    const height = row.getBoundingClientRect().height;
                    row.animate(
                        [
                            {
                                height: '0px',
                                opacity: 0,
                                transform: 'translateY(-12px)',
                            },
                            {
                                height: `${height}px`,
                                opacity: 1,
                                transform: 'none',
                            },
                        ],
                        {
                            duration: 520,
                            delay: index * 140,
                            easing: 'cubic-bezier(0.22, 1, 0.36, 1)',
                            fill: 'backwards',
                        },
                    );
                }
            },
        );

        // They stay lit until they are on screen, then while nobody is touching the page, and fade soon after the pointer
        // starts moving. Commits that arrived while the reader was further down wait for them to scroll back up.
        let timer: ReturnType<typeof setTimeout> | undefined;
        let sightedAt = 0;
        let hurried = false;
        const visible = new Set<Element>();

        const settle = () => {
            rows.forEach((row) => row.removeAttribute('data-fresh'));
            freshIds.current = new Set();
            cleanup();
        };

        const hurry = () => {
            if (hurried || sightedAt === 0 || Date.now() - sightedAt < 700) {
                return;
            }

            hurried = true;
            clearTimeout(timer);
            timer = setTimeout(settle, 1800);
        };

        const observer = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) =>
                    entry.isIntersecting
                        ? visible.add(entry.target)
                        : visible.delete(entry.target),
                );

                if (visible.size > 0 && sightedAt === 0) {
                    sightedAt = Date.now();
                    timer = setTimeout(settle, 7000);
                    seenFresh.current();
                }
            },
            { threshold: 0.6 },
        );

        rows.forEach((row) => observer.observe(row));

        function cleanup() {
            clearTimeout(timer);
            observer.disconnect();
            window.removeEventListener('pointermove', hurry);
            window.removeEventListener('wheel', hurry);
            window.removeEventListener('keydown', hurry);
        }

        window.addEventListener('pointermove', hurry, { passive: true });
        window.addEventListener('wheel', hurry, { passive: true });
        window.addEventListener('keydown', hurry);

        return cleanup;
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

    // The pill stays until the new commits have been seen, then lingers a moment and fades.
    const dismissLoaded = (after: number) => {
        loadedTimers.current.forEach(clearTimeout);
        loadedTimers.current = [
            setTimeout(() => setLoaded('leaving'), after),
            setTimeout(() => {
                setLoaded(null);
                setWaiting(0);
            }, after + 450),
        ];
    };
    useEffect(() => {
        seenFresh.current = () => dismissLoaded(1500);
    });

    useEffect(
        () => () => {
            loadedTimers.current.forEach(clearTimeout);
            clearTimeout(fetchTimer.current);
        },
        [],
    );

    useEffect(() => {
        const update = () => setIsAway(window.scrollY > 120);
        update();
        window.addEventListener('scroll', update, { passive: true });

        return () => window.removeEventListener('scroll', update);
    }, []);

    // Back at the top, the new commits are right there, so the pill has nothing left to ask for.
    useEffect(() => {
        if (!isAway && loaded === 'shown') {
            seenFresh.current();
        }
    }, [isAway, loaded]);

    // On Latest, new commits are fetched on their own, once the pill has had a moment to be read.
    useEffect(() => {
        if (!autoLoads || newCommitsCount === 0 || isFetching) {
            return;
        }

        const timer = setTimeout(() => {
            const count = newCommitsCount;
            setAnnounced(count);
            setIsFetching(true);
            loadingNew.current = true;

            // The pill says it is fetching for a beat, so it reads as a step even when the answer is quick.
            fetchTimer.current = setTimeout(
                () =>
                    router.reload({
                        only: ['commits', 'newCommitsCount'],
                        reset: ['commits'],
                        data: { since: '' },
                        // The new commits come with everything already loaded, so the reader keeps their row.
                        headers: {
                            'X-Log-Through': oldestLoadedId ?? '',
                        },
                        preserveUrl: true,
                        onFinish: () => {
                            loadingNew.current = false;
                            setIsFetching(false);
                            setWaiting((total) => total + count);
                            setLoaded('shown');
                            loadedTimers.current.forEach(clearTimeout);
                        },
                    }),
                700,
            );
        }, 1000);

        return () => {
            clearTimeout(timer);
        };
    }, [autoLoads, newCommitsCount, isFetching, oldestLoadedId]);

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

            {(newCommitsCount > 0 || isFetching || loaded !== null) && (
                <div
                    className="pointer-events-none sticky z-10 flex h-0 items-start justify-center"
                    style={{ top: tabsHeight }}
                >
                    <button
                        type="button"
                        onClick={
                            newCommitsCount === 0 && loaded !== null
                                ? () => {
                                      window.scrollTo({
                                          top: 0,
                                          behavior: 'smooth',
                                      });
                                      dismissLoaded(900);
                                  }
                                : reloadNewestFirst
                        }
                        disabled={isFetching}
                        className={cn(
                            loaded === 'leaving' &&
                                newCommitsCount === 0 &&
                                '-translate-y-2 opacity-0',
                            'pointer-events-auto mt-3 flex items-center gap-1.5 rounded-md bg-primary px-4 py-1.5 text-sm font-medium text-primary-foreground shadow-[0_4px_14px_-4px_rgb(0_0_0/0.35)] transition-colors hover:bg-primary-hover motion-safe:animate-in motion-safe:transition-[opacity,translate,background-color] motion-safe:duration-450 motion-safe:fade-in motion-safe:slide-in-from-top-2',
                        )}
                    >
                        {isFetching ? (
                            <LoaderCircle className="size-4 motion-safe:animate-spin" />
                        ) : (
                            <ArrowUp className="size-4" />
                        )}
                        {isFetching
                            ? `Fetching ${announced === 1 ? 'commit' : `${announced} commits`}…`
                            : newCommitsCount === 0
                              ? `${waiting === 1 ? '1 new commit' : `${waiting} new commits`} loaded${isAway ? ` · ${window.matchMedia('(pointer: coarse)').matches ? 'Tap' : 'Click'} to scroll up` : ''}`
                              : `${newCommitsCount} ${newCommitsCount === 1 ? 'new commit' : 'new commits'}`}
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
