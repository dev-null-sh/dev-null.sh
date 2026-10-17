import { useLang } from '@erag/lang-sync-inertia/react';
import { Head, Link, router, usePage, usePoll } from '@inertiajs/react';
import { ArrowUp } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import LogController from '@/actions/App/Http/Controllers/LogController';
import UserFollowController from '@/actions/App/Http/Controllers/UserFollowController';
import { CommitComposer } from '@/components/commit-composer';
import { useCommitDialogClosedCount } from '@/components/commit-composer-dialog';
import { CommitFeed, EmptyFeed } from '@/components/commit-feed';
import { LinkifiedText } from '@/components/linkified-text';
import { PageTabs } from '@/components/page-tabs';
import { Button } from '@/components/ui/button';
import { UserAvatar } from '@/components/user-avatar';
import { UserPreview } from '@/components/user-preview';
import { WhoToFollowInLog } from '@/components/who-to-follow';
import { useLastVisit } from '@/hooks/use-last-visit';
import AppLayout from '@/layouts/app-layout';
import { emojify } from '@/lib/emoji';
import { readStorage, writeStorage } from '@/lib/storage';
import type { Commit, CursorPaginated, Log } from '@/types';

export default function Show({
    log,
    commits,
    isRanked,
    newestCommitId,
    latestCommits,
    newCommitsCount = 0,
    suggestions,
}: {
    log: Log;
    commits: CursorPaginated<Commit>;
    isRanked: boolean;
    newestCommitId: string | null;
    latestCommits: Commit[];
    newCommitsCount?: number;
    suggestions: Commit[];
}) {
    const {
        flash,
        props: { auth },
    } = usePage();
    const { __, transChoice } = useLang();
    const user = auth.user;
    const commitDialogClosedCount = useCommitDialogClosedCount();
    const viewerId = user?.id ?? 'guest';
    const markerKey = `latest-marker:${viewerId}:${log}`;
    const [markerId, setMarkerId] = useState(
        () =>
            (flash.committed ? readStorage(markerKey) : '') ||
            (newestCommitId ?? ''),
    );
    const newestId = isRanked ? markerId || undefined : commits.data[0]?.id;
    const tabsRef = useRef<HTMLDivElement>(null);
    const [tabsHeight, setTabsHeight] = useState(0);

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

    useEffect(() => writeStorage(markerKey, markerId), [markerKey, markerId]);
    const firstSeenId = useLastVisit(viewerId, log, commits.data);

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
        isRanked ? prependNewCommits() : reloadNewestFirst();

    const prependNewCommits = () =>
        router.reload({
            only: ['latestCommits'],
            data: { since: newestId },
            preserveUrl: true,
            onSuccess: (page) => {
                const next =
                    newestOf(
                        (page.props.latestCommits as Commit[]).filter(
                            (commit) => commit.user_id !== user?.id,
                        ),
                    )?.id ?? markerId;

                setMarkerId(next);
                router.replaceProp('newCommitsCount', 0);
                router.reload({
                    only: ['newCommitsCount'],
                    data: { since: next },
                    preserveUrl: true,
                });
                window.scrollTo({ top: 0, behavior: 'smooth' });
            },
        });

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
            <Head title={__(`log.titles.${log}`)} />

            <div
                ref={tabsRef}
                className="sticky top-0 z-10 bg-card [view-transition-class:tab-header] [view-transition-name:log-header]"
            >
                <h1 className="sr-only">{__(`log.titles.${log}`)}</h1>
                <PageTabs
                    label={__('log.tabs_label')}
                    name="Logs"
                    prefetch={false}
                    tabs={[
                        {
                            title: __('log.titles.following'),
                            href: LogController.show('following'),
                            isActive: log === 'following',
                        },
                        {
                            title: __('log.titles.latest'),
                            href: LogController.show('latest'),
                            isActive: log === 'latest',
                        },
                        {
                            title: __('log.titles.trending'),
                            href: LogController.show('trending'),
                            isActive: log === 'trending',
                        },
                    ]}
                />
            </div>

            {user !== null && <CommitComposer key={commitDialogClosedCount} />}

            {newCommitsCount > 0 && (
                <div
                    className="sticky z-10 flex justify-center border-b bg-card py-3"
                    style={{ top: tabsHeight }}
                >
                    <button
                        type="button"
                        onClick={showNewCommits}
                        className="flex items-center gap-1.5 rounded-full bg-primary px-4 py-1.5 text-sm font-medium text-primary-foreground shadow-[0_4px_14px_-4px_rgb(0_0_0/0.35)] transition-colors hover:bg-primary-hover motion-safe:animate-in motion-safe:fade-in motion-safe:slide-in-from-top-2"
                    >
                        <ArrowUp className="size-4" />
                        {transChoice('log.new_commits', newCommitsCount)}
                    </button>
                </div>
            )}

            <div className="[view-transition-class:tab-content] [view-transition-name:log-feed]">
                <CommitFeed
                    commits={commits}
                    firstLane="var(--rail)"
                    firstSeenId={firstSeenId}
                    latestCommits={latestCommits}
                    isRanked={isRanked}
                    end={__('log.end')}
                    interlude={(lane, isDimmed) => (
                        <WhoToFollowInLog lane={lane} isDimmed={isDimmed} />
                    )}
                    empty={
                        log === 'following' ? (
                            <EmptyFeed
                                command={__('log.empty.following_command')}
                            >
                                {__('log.empty.following')}{' '}
                                {suggestions.length > 0
                                    ? __('log.empty.suggestions_intro')
                                    : `${__('log.empty.find_someone_before')} `}
                                {suggestions.length === 0 && (
                                    <>
                                        <Link
                                            href={LogController.show(
                                                'trending',
                                            )}
                                            className="font-medium text-primary underline"
                                        >
                                            {__('log.titles.trending')}
                                        </Link>
                                        {__('log.empty.find_someone_after')}
                                    </>
                                )}
                                {suggestions.length > 0 && (
                                    <Suggestions suggestions={suggestions} />
                                )}
                            </EmptyFeed>
                        ) : (
                            <EmptyFeed command={__('log.empty.command')}>
                                {__('log.empty.nobody')}
                            </EmptyFeed>
                        )
                    }
                />
            </div>
        </>
    );
}

/**
 * Commits fetched earlier are older than the ones fetched last, so the newest of all is where the next fetch starts.
 */
function newestOf(commits: Commit[]): Commit | undefined {
    return commits.reduce<Commit | undefined>(
        (newest, commit) =>
            newest === undefined ||
            commit.created_at > newest.created_at ||
            (commit.created_at === newest.created_at && commit.id > newest.id)
                ? commit
                : newest,
        undefined,
    );
}

function Suggestions({ suggestions }: { suggestions: Commit[] }) {
    const { __ } = useLang();

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
                                aria-label={__('log.suggestions.follow_label', {
                                    username: commit.user.username,
                                })}
                            >
                                {__('log.suggestions.follow')}
                            </Link>
                        </Button>
                    </li>
                ))}
            </ul>
            <p className="mt-4 text-sm text-muted-foreground">
                {__('log.suggestions.browse_before')}{' '}
                <Link
                    href={LogController.show('trending')}
                    className="font-medium text-primary underline"
                >
                    {__('log.titles.trending')}
                </Link>{' '}
                {__('log.suggestions.browse_after')}
            </p>
        </>
    );
}

Show.layout = AppLayout;
