import type { CancelToken, PageProps } from '@inertiajs/core';
import { Head, Link, router, usePage, usePoll } from '@inertiajs/react';
import { ArrowUp } from 'lucide-react';
import {
    useCallback,
    useEffect,
    useEffectEvent,
    useLayoutEffect,
    useRef,
    useState,
} from 'react';
import LogController from '@/actions/App/Http/Controllers/LogController';
import { AuthorAvatar } from '@/components/author-avatar';
import { CommitComposer } from '@/components/commit-composer';
import { useCommitDialogClosedCount } from '@/components/commit-composer-dialog';
import { CommitFeed, EmptyFeed } from '@/components/commit-feed';
import { FollowButton } from '@/components/follow-button';
import { LinkifiedText } from '@/components/linkified-text';
import { PageTabs } from '@/components/page-tabs';
import { UserPreview } from '@/components/user-preview';
import { WhoToFollowInLog } from '@/components/who-to-follow';
import { useComposerVisibility } from '@/hooks/use-composer-visibility';
import { useLaneSwipe } from '@/hooks/use-lane-swipe';
import { useLastVisit } from '@/hooks/use-last-visit';
import { useOwnsCommit } from '@/hooks/use-owns-commit';
import { idsOf, useIsListening } from '@/hooks/use-realtime';
import AppLayout from '@/layouts/app-layout';
import {
    backgroundPollOptions,
    reloadInBackground,
} from '@/lib/background-poll';
import { emojify } from '@/lib/emoji';
import { batchLogArrivals } from '@/lib/log-arrivals';
import { animateScrollToTop, scrollLogToTop } from '@/lib/log-scroll';
import {
    atMostAtOnce,
    coalesce,
    listen,
    logChannel,
    userChannel,
} from '@/lib/realtime';
import { cn } from '@/lib/utils';
import { windowDigest } from '@/lib/window-digest';
import type { Commit, CursorPaginated, Log } from '@/types';

export default function Show({
    log,
    commits,
    isRanked,
    suggestions,
}: {
    log: Log;
    commits: CursorPaginated<Commit>;
    isRanked: boolean;
    suggestions: Commit[];
}) {
    const {
        props: { auth },
    } = usePage();
    const user = auth.user;
    const ownsCommit = useOwnsCommit();
    const [notice, setNotice] = useState<{
        commits: Commit[];
        leaving: boolean;
    } | null>(null);
    const [stateLog, setStateLog] = useState(log);
    const loadRequest = useRef<CancelToken | null>(null);
    const loadingNew = useRef(false);
    const freshIds = useRef<Set<string>>(new Set());
    const arrivalRead = useRef<(() => (ids: string[]) => void) | null>(null);

    // Another log has its own commits to announce, so nothing of the last one's pill carries over.
    if (stateLog !== log) {
        setStateLog(log);
        setNotice(null);
    }

    const commitDialogClosedCount = useCommitDialogClosedCount();
    const viewerId = user?.id ?? 'guest';
    const observedAt = useRef(new Map<string, string>());
    useEffect(() => {
        for (const commit of commits.data) {
            if (!observedAt.current.has(commit.id)) {
                observedAt.current.set(commit.id, commit.approved_at ?? commit.created_at);
            }
        }
    }, [commits.data]);

    const newestId = isRanked ? undefined : commits.data[0]?.id;
    const newestAt = newestId === undefined
        ? undefined
        : (observedAt.current.get(newestId) ?? commits.data[0]?.approved_at ?? commits.data[0]?.created_at);
    // Keep the exact boundary and its original timestamp when approvals reorder the feed.
    const throughRows = commits.data.slice(-20).reverse();
    const throughIds = throughRows.map((commit) => commit.id).join(',');
    const throughTimes = throughRows
        .map((commit) => observedAt.current.get(commit.id) ?? commit.approved_at ?? commit.created_at)
        .join(',');
    const tabsRef = useRef<HTMLDivElement>(null);
    const [tabsHeight, setTabsHeight] = useState(0);
    const feedRef = useRef<HTMLDivElement>(null);
    const followAnchor = useRef<{
        row: HTMLElement;
        top: number;
        scrollY: number;
    } | null>(null);
    const returningToTop = useRef(false);
    const nativeAnchoring = useRef<string | null>(null);
    const [composer, setComposer] = useState<HTMLDivElement | null>(null);
    useLaneSwipe(feedRef, log);
    useComposerVisibility(composer, tabsHeight);

    useLayoutEffect(() => {
        followAnchor.current = null;
        returningToTop.current = false;

        return () => {
            if (nativeAnchoring.current !== null) {
                document.documentElement.style.overflowAnchor =
                    nativeAnchoring.current;
                nativeAnchoring.current = null;
            }
        };
    }, [log]);

    useEffect(() => {
        let cancel: VoidFunction | undefined;
        const scroll = () => {
            cancel?.();
            returningToTop.current = window.scrollY > 0;
            followAnchor.current = null;
            cancel = animateScrollToTop();
        };
        window.addEventListener('log:scroll-top', scroll);

        return () => {
            cancel?.();
            window.removeEventListener('log:scroll-top', scroll);
        };
    }, [log]);

    useLayoutEffect(() => {
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

    const preserveReadingPosition = useCallback(
        (ids: Set<string>) => {
            if (returningToTop.current) return;
            const headerBottom =
                tabsRef.current?.getBoundingClientRect().bottom ?? 0;
            const focused = document.activeElement?.closest<HTMLElement>(
                'input, textarea, [contenteditable="true"]',
            );
            const rows =
                feedRef.current?.querySelectorAll<HTMLElement>(
                    '[data-commit]',
                ) ?? [];
            const row =
                focused &&
                composer?.contains(focused) &&
                focused.getBoundingClientRect().bottom > headerBottom &&
                focused.getBoundingClientRect().top < window.innerHeight
                    ? focused
                    : Array.from(rows).find(
                          (row) =>
                              ids.has(row.dataset.commit ?? '') &&
                              row.getBoundingClientRect().bottom > headerBottom,
                      );
            followAnchor.current = row
                ? {
                      row,
                      top: row.getBoundingClientRect().top,
                      scrollY: window.scrollY,
                  }
                : null;
            if (row && nativeAnchoring.current === null) {
                // Take ownership before insertion so the browser cannot also compensate for it.
                const style = document.documentElement.style;
                nativeAnchoring.current = style.overflowAnchor;
                style.overflowAnchor = 'none';
            }
        },
        [composer],
    );

    const restoreReadingPosition = useCallback(() => {
        if (returningToTop.current) return;
        const anchor = followAnchor.current;
        if (!anchor?.row.isConnected) return;

        const top = anchor.row.getBoundingClientRect().top;
        const scrollDelta = window.scrollY - anchor.scrollY;
        // Layout compensation must retain viewport movement, even when both change in one frame.
        const targetTop = anchor.top - scrollDelta;
        if (top !== targetTop) window.scrollBy(0, top - targetTop);
        anchor.top = targetTop;
        anchor.scrollY = window.scrollY;

        if (scrollDelta !== 0) {
            preserveReadingPosition(
                new Set(commits.data.map((commit) => commit.id)),
            );
        }
    }, [commits, preserveReadingPosition]);

    useLayoutEffect(() => {
        const feed = feedRef.current;
        if (!feed || isRanked) return;

        const observer = new ResizeObserver(restoreReadingPosition);
        observer.observe(feed);
        const mutations = new MutationObserver(restoreReadingPosition);
        mutations.observe(feed, {
            childList: true,
            subtree: true,
            characterData: true,
            attributes: true,
            attributeFilter: ['style'],
        });
        const updateFocus = () => {
            if (followAnchor.current)
                preserveReadingPosition(
                    new Set(commits.data.map((commit) => commit.id)),
                );
        };
        let scrollTimer: ReturnType<typeof setTimeout> | undefined;
        const updateScroll = () => {
            if (!returningToTop.current) {
                restoreReadingPosition();
                return;
            }

            // Smooth scrolling owns the viewport until it finishes or the reader interrupts it.
            clearTimeout(scrollTimer);
            scrollTimer = setTimeout(() => {
                returningToTop.current = false;
                preserveReadingPosition(
                    new Set(commits.data.map((commit) => commit.id)),
                );
            }, 150);
        };
        window.addEventListener('focusin', updateFocus);
        window.addEventListener('focusout', updateFocus);
        window.addEventListener('scroll', updateScroll, {
            passive: true,
        });
        if (returningToTop.current) updateScroll();

        return () => {
            observer.disconnect();
            mutations.disconnect();
            clearTimeout(scrollTimer);
            window.removeEventListener('focusin', updateFocus);
            window.removeEventListener('focusout', updateFocus);
            window.removeEventListener('scroll', updateScroll);
        };
    }, [commits, isRanked, preserveReadingPosition, restoreReadingPosition]);

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
                }

                preserveReadingPosition(
                    new Set(incoming.data.map((commit) => commit.id)),
                );
            }),
        [log, commits, preserveReadingPosition],
    );

    useLayoutEffect(restoreReadingPosition, [restoreReadingPosition]);

    useLayoutEffect(() => {
        const ids = freshIds.current;

        if (ids.size === 0) {
            return;
        }

        const rows = Array.from(
            feedRef.current?.querySelectorAll<HTMLElement>('[data-commit]') ??
                [],
        ).filter((row) => ids.has(row.dataset.commit ?? ''));
        rows.filter((row) => row.dataset.fresh === undefined).forEach((row) => {
            row.dataset.fresh = '';
        });

        // They stay lit until they are on screen, then while nobody is touching the page, and fade soon after the pointer
        // starts moving. Commits that arrived while the reader was further down wait for them to scroll back up.
        let timer: ReturnType<typeof setTimeout> | undefined;
        let sightedAt = 0;
        let hurried = false;
        const visible = new Set<Element>();

        const settle = () => {
            rows.forEach((row) => {
                row.dataset.fresh = 'fading';
                setTimeout(() => {
                    if (row.dataset.fresh === 'fading') {
                        row.removeAttribute('data-fresh');
                    }
                }, 1400);
            });
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

    const loadNewest = (
        count: number,
        signal?: AbortSignal,
    ): Promise<number | null> => {
        if (signal?.aborted || document.visibilityState === 'hidden') {
            return Promise.resolve(null);
        }

        if (isRanked || !count || loadingNew.current) {
            return Promise.resolve(loadingNew.current ? null : 0);
        }

        const known = new Set(commits.data.map((commit) => commit.id));
        const firstKnownIndex = (incoming: Commit[]) => {
            const index = incoming.findIndex((commit) => known.has(commit.id));

            return index === -1 ? incoming.length : index;
        };
        loadingNew.current = true;
        let added: number | null = null;
        const received = arrivalRead.current?.();

        return reloadInBackground(
            {
                only: ['commits', 'newCommitsCount'],
                reset: ['commits'],
                data: { since: '' },
                // Retain the loaded range so inserting new commits preserves the reader's row.
                headers: { 'X-Log-Through': throughIds, 'X-Log-Through-At': throughTimes },
                preserveUrl: true,
                onCancelToken: (token) => {
                    loadRequest.current = token;
                },
                onSuccess: (page) => {
                    const incoming = page.props
                        .commits as CursorPaginated<Commit>;
                    received?.(incoming.data.map((commit) => commit.id));
                    added = incoming.data.filter(
                        (commit) =>
                            !known.has(commit.id) && !ownsCommit(commit),
                    ).length;
                    // Only what came in above the commits already shown is news: older ones approved since are
                    // placed among them without a notice.
                    const arrivals = incoming.data
                        .slice(0, firstKnownIndex(incoming.data))
                        .filter(
                            (commit) =>
                                !known.has(commit.id) && !ownsCommit(commit),
                        );
                    if (
                        arrivals.length === 0 ||
                        (document.visibilityState === 'visible' &&
                            window.scrollY <= 0)
                    )
                        return;

                    setNotice((current) => ({
                        commits: [
                            ...arrivals,
                            ...(current?.leaving
                                ? []
                                : (current?.commits ?? [])
                            ).filter((commit) =>
                                incoming.data.some(
                                    (row) => row.id === commit.id,
                                ),
                            ),
                        ],
                        leaving: false,
                    }));
                },
                onFinish: () => {
                    loadingNew.current = false;
                    loadRequest.current = null;
                },
            },
            signal,
        ).then(() => added);
    };

    /**
     * Asks whether the log has new commits, and which of the commits it shows, or heard about, changed: the ones
     * gone leave, and older ones approved since are placed among the rest. A log that shows nothing yet asks for
     * everything, so its first commit is found too. Checking the window also counts what the log holds between the
     * oldest and newest commits loaded: a different count than shown means something was missed, and the loaded
     * range is read again. A ranked log is never reshaped, only rid of what is gone.
     */
    const probeOptions = ({
        heard = [],
        checked = [],
        checksWindow = false,
        checksArrivals = true,
        signal,
    }: {
        heard?: string[];
        checked?: string[];
        checksWindow?: boolean;
        checksArrivals?: boolean;
        signal?: AbortSignal;
    }) => {
        const arrived = isRanked || !checksArrivals ? [] : heard;
        let followUp: Promise<number | null> = Promise.resolve(null);

        const options = {
            only: [
                ...(checksArrivals ? ['newCommitsCount'] : []),
                'removedCommitIds',
                ...(arrived.length > 0 ? ['arrivedCommitIds'] : []),
                ...(checksWindow && !isRanked ? ['windowDigest'] : []),
            ],
            data: {
                since: newestId,
                since_at: newestAt,
                ...(newestId === undefined && !isRanked
                    ? { everything: 1 }
                    : {}),
                pending: [
                    ...(notice?.leaving
                        ? []
                        : (notice?.commits.map((commit) => commit.id) ?? [])),
                    ...checked,
                ].slice(0, 100),
                arrived,
            },
            headers: { 'X-Log-Through': throughIds, 'X-Log-Through-At': throughTimes },
            preserveUrl: true,
            onSuccess: (page: { props: PageProps }) => {
                const removed = new Set(
                    page.props.removedCommitIds as string[],
                );
                if (removed.size > 0) {
                    setNotice((current) => {
                        if (!current || current.leaving) return current;
                        const remaining = current.commits.filter(
                            (commit) => !removed.has(commit.id),
                        );
                        return remaining.length > 0
                            ? { ...current, commits: remaining }
                            : { ...current, leaving: true };
                    });
                    router.replaceProp(
                        'commits',
                        (value: unknown, props: PageProps) => {
                            if (props.log !== log) return value;
                            const current = value as CursorPaginated<Commit>;
                            const retained = current.data.filter(
                                (commit) => !removed.has(commit.id),
                            );
                            preserveReadingPosition(
                                new Set(retained.map((commit) => commit.id)),
                            );
                            return { ...current, data: retained };
                        },
                    );
                }
                if (!checksArrivals) {
                    followUp = Promise.resolve(0);
                    return;
                }
                const expected = page.props.windowDigest as
                    | string
                    | null
                    | undefined;
                const shown = commits.data
                    .map((commit) => commit.id)
                    .filter((id) => !removed.has(id));
                const newest = Math.max(
                    page.props.newCommitsCount as number,
                    (
                        (page.props.arrivedCommitIds as string[] | undefined) ??
                        []
                    ).length,
                );
                // The loaded window is read again, as deep as it was, when it no longer holds what it shows, or when it
                // can't be told, as none of its oldest commits is still the reader's to see.
                followUp =
                    typeof expected === 'string' && shown.length > 0
                        ? windowDigest(shown).then((digest) =>
                              loadNewest(
                                  digest === expected
                                      ? newest
                                      : commits.data.length + 1,
                                  signal,
                              ),
                          )
                        : expected === null && shown.length > 0
                          ? loadNewest(commits.data.length + 1, signal)
                          : loadNewest(newest, signal);
            },
        };

        return {
            options,
            settled: () => followUp,
        };
    };

    const isHearingLog = useIsListening(logChannel);
    const { start: startPolling, stop: stopPolling } = usePoll(
        isRanked ? 60_000 : 30_000,
        backgroundPollOptions(
            () =>
                probeOptions({
                    checked: isRanked
                        ? commits.data.slice(0, 100).map((commit) => commit.id)
                        : [],
                    checksWindow: true,
                }).options,
        ),
        { autoStart: false },
    );

    // Polling backs live updates up only while this page is visible and isn't listening.
    useEffect(() => {
        const update = () => {
            if (isHearingLog || document.visibilityState === 'hidden') {
                stopPolling();
            } else {
                startPolling();
            }
        };
        update();
        document.addEventListener('visibilitychange', update);
        return () => {
            document.removeEventListener('visibilitychange', update);
            stopPolling();
        };
    }, [isHearingLog, startPolling, stopPolling]);

    const splitVisibility = useEffectEvent((ids: string[]) => {
        const loaded = new Set([
            ...commits.data.map((commit) => commit.id),
            ...(notice?.commits.map((commit) => commit.id) ?? []),
        ]);
        return {
            checked: ids.filter((id) => loaded.has(id)),
            arrived: isRanked ? [] : ids.filter((id) => !loaded.has(id)),
        };
    });

    const probe = useEffectEvent(
        async (
            ids: string[],
            isCatchingUp: boolean,
            signal: AbortSignal,
            checksArrivals: boolean,
        ): Promise<number | null> => {
            const { checked, arrived } = splitVisibility(ids);
            const pending =
                isCatchingUp && isRanked
                    ? commits.data.map((commit) => commit.id)
                    : checked;
            let added = 0;

            // A wider arrival batch checks the whole loaded window too, so approvals beyond the hundred ids
            // named in the request are found even when they belong below its newest commit.
            for (
                let offset = 0;
                offset < Math.max(pending.length, 1);
                offset += 100
            ) {
                if (signal.aborted) return null;
                const { options, settled } = probeOptions({
                    heard: offset === 0 ? arrived.slice(0, 100) : [],
                    checked: pending.slice(offset, offset + 100),
                    checksWindow:
                        offset === 0 && (isCatchingUp || arrived.length > 100),
                    checksArrivals: checksArrivals && offset === 0,
                    signal,
                });
                await reloadInBackground(options, signal);
                const result = await settled();
                if (result === null) return null;
                added += result;
            }
            return added;
        },
    );

    useEffect(() => {
        const heard = new Set<string>();
        const controller = new AbortController();
        const read = atMostAtOnce(1);
        let isCatchingUp = false;
        const arrivals = batchLogArrivals((pending) =>
            read(async () => {
                if (
                    controller.signal.aborted ||
                    document.visibilityState === 'hidden'
                )
                    return null;
                const ids = pending();
                if (ids.length === 0) return 0;
                return probe(ids, false, controller.signal, true);
            }),
        );
        const refresh = coalesce(
            () =>
                read(async () => {
                    if (
                        controller.signal.aborted ||
                        document.visibilityState === 'hidden'
                    )
                        return;
                    const catchingUp = isCatchingUp;
                    const caughtUp = catchingUp ? arrivals.read() : null;
                    const ids = [...heard];
                    heard.clear();
                    isCatchingUp = false;
                    const result = await probe(
                        ids,
                        catchingUp,
                        controller.signal,
                        catchingUp,
                    ).catch(() => null);
                    if (controller.signal.aborted) return;
                    if (result === null) {
                        ids.forEach((id) => heard.add(id));
                        isCatchingUp ||= catchingUp;
                        refresh.request();
                    } else {
                        caughtUp?.();
                    }
                    if (!document.hidden) arrivals.resume();
                }),
            1_000,
        );

        const started = () => {
            const received = arrivals.read();
            return (ids: string[]) => {
                if (controller.signal.aborted) return;
                const newer = received(ids);
                newer.forEach((id) => heard.add(id));
                if (newer.length > 0) refresh.request();
            };
        };
        arrivalRead.current = started;

        const recover = () => {
            isCatchingUp = true;
            refresh.request();
        };
        const onVisible = () => {
            if (document.visibilityState === 'hidden') {
                arrivals.pause();
            } else {
                recover();
            }
        };
        if (document.visibilityState === 'hidden') arrivals.pause();
        document.addEventListener('visibilitychange', onVisible);

        const stop = listen(
            logChannel,
            {
                visibility: (payload) => {
                    const { checked, arrived } = splitVisibility(
                        idsOf(payload),
                    );
                    checked.forEach((id) => heard.add(id));
                    if (checked.length > 0) refresh.request();
                    arrivals.request(arrived);
                },
            },
            recover,
        );
        const stopRelationships =
            viewerId === 'guest'
                ? () => {}
                : listen(
                      userChannel(viewerId),
                      { relationships: recover },
                      recover,
                  );

        return () => {
            stop();
            stopRelationships();
            document.removeEventListener('visibilitychange', onVisible);
            if (arrivalRead.current === started) arrivalRead.current = null;
            arrivals.cancel();
            refresh.cancel();
            controller.abort();
        };
    }, [log, viewerId]);

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

    const dismissNotice = useCallback(() => {
        setNotice((current) => current && { ...current, leaving: true });
    }, []);

    const noticeLeaving = notice?.leaving;
    useEffect(() => {
        if (!noticeLeaving) return;
        const timer = setTimeout(() => setNotice(null), 450);
        return () => clearTimeout(timer);
    }, [noticeLeaving]);

    const noticeNewestId = notice?.commits[0]?.id;
    useEffect(() => {
        if (!noticeNewestId) return;
        const row = Array.from(
            feedRef.current?.querySelectorAll<HTMLElement>('[data-commit]') ??
                [],
        ).find((row) => row.dataset.commit === noticeNewestId);
        if (!row) return;

        const observer = new IntersectionObserver(
            (entries) => {
                if (
                    document.visibilityState === 'visible' &&
                    entries.some((entry) => entry.intersectionRatio >= 0.6)
                )
                    dismissNotice();
            },
            {
                rootMargin: `-${tabsHeight}px 0px 0px 0px`,
                threshold: 0.6,
            },
        );
        const update = () => {
            observer.disconnect();
            if (document.visibilityState === 'visible') observer.observe(row);
        };
        update();
        document.addEventListener('visibilitychange', update);

        return () => {
            observer.disconnect();
            document.removeEventListener('visibilitychange', update);
        };
    }, [noticeNewestId, commits, tabsHeight, dismissNotice]);

    useEffect(
        () => () => {
            loadRequest.current?.cancel();
        },
        [log],
    );

    useEffect(() => {
        const update = () => {
            if (document.visibilityState === 'visible' && window.scrollY <= 0)
                dismissNotice();
        };
        window.addEventListener('scroll', update, { passive: true });
        document.addEventListener('visibilitychange', update);

        return () => {
            window.removeEventListener('scroll', update);
            document.removeEventListener('visibilitychange', update);
        };
    }, [dismissNotice]);

    const authors = [
        ...new Map(
            notice?.commits.map((commit) => [commit.author.id, commit.author]),
        ).values(),
    ];
    const announcementLabel = `${notice?.commits.length ?? 0} new ${notice?.commits.length === 1 ? 'commit' : 'commits'} above. Scroll to top`;

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
                            onClick:
                                log === 'following'
                                    ? scrollLogToTop
                                    : undefined,
                        },
                        {
                            title: 'Latest',
                            href: LogController.show('latest'),
                            isActive: log === 'latest',
                            onClick:
                                log === 'latest' ? scrollLogToTop : undefined,
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

            {notice !== null && (
                <div
                    className="pointer-events-none sticky z-10 flex h-0 items-start justify-center"
                    style={{ top: tabsHeight }}
                >
                    <button
                        type="button"
                        onClick={() => scrollLogToTop()}
                        aria-label={announcementLabel}
                        title={announcementLabel}
                        className={cn(
                            notice.leaving && '-translate-y-2 opacity-0',
                            'pointer-events-auto mt-3 flex min-h-11 max-w-[calc(100vw-2rem)] items-center justify-center gap-1.5 rounded-md bg-primary px-4 py-1.5 text-sm font-medium text-primary-foreground shadow-[0_4px_14px_-4px_rgb(0_0_0/0.35)] transition-colors hover:bg-primary-hover motion-safe:animate-in motion-safe:transition-[opacity,translate,background-color] motion-safe:duration-450 motion-safe:fade-in motion-safe:slide-in-from-top-2',
                        )}
                    >
                        <ArrowUp className="size-4 shrink-0" />
                        <span
                            aria-hidden
                            className="flex shrink-0 items-center -space-x-1 [clip-path:inset(-2px)] motion-safe:transition-[width] motion-safe:duration-250 motion-safe:ease-out"
                            style={{
                                width:
                                    24 + (Math.min(authors.length, 4) - 1) * 20,
                            }}
                        >
                            {authors.slice(0, 3).map((author) => (
                                <span
                                    key={author.id}
                                    className="shrink-0 motion-safe:animate-in motion-safe:duration-250 motion-safe:fade-in"
                                >
                                    <AuthorAvatar
                                        author={author}
                                        pixels={48}
                                        className="size-6 ring-2 ring-primary"
                                    />
                                </span>
                            ))}
                            {authors.length > 3 && (
                                <span
                                    title={`${authors.length - 3} more authors`}
                                    className="relative grid size-6 shrink-0 place-items-center rounded-full bg-muted pt-0.5 font-mono text-[10px] leading-none font-medium text-foreground tabular-nums ring-2 ring-primary motion-safe:animate-in motion-safe:duration-250 motion-safe:fade-in"
                                >
                                    {authors.length - 3 > 99
                                        ? '99+'
                                        : `+${authors.length - 3}`}
                                </span>
                            )}
                        </span>
                        <span className="min-w-0 truncate">New commits</span>
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
                        <UserPreview user={commit.author} className="shrink-0">
                            <AuthorAvatar
                                author={commit.author}
                                className="size-9"
                            />
                        </UserPreview>
                        <div className="min-w-0 flex-1">
                            <p className="truncate text-sm">
                                <UserPreview user={commit.author}>
                                    <span className="font-semibold">
                                        {commit.author.name}
                                    </span>
                                </UserPreview>{' '}
                                <span className="font-mono text-[13px] text-muted-foreground">
                                    @{commit.author.handle}
                                </span>
                            </p>
                            <p className="truncate text-sm text-muted-foreground">
                                <LinkifiedText
                                    text={emojify(commit.body)}
                                    mentions={commit.mentions}
                                    organizationMentions={
                                        commit.organization_mentions
                                    }
                                    startsText
                                />
                            </p>
                        </div>
                        <FollowButton
                            user={commit.author}
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
