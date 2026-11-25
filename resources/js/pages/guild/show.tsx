import { Head, Link, router, usePage } from '@inertiajs/react';
import { Pencil, Pin } from 'lucide-react';
import { useEffect, useLayoutEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import { show as showCommit } from '@/actions/App/Http/Controllers/CommitController';
import { update as updatePin } from '@/actions/App/Http/Controllers/GuildCommitPinController';
import { show } from '@/actions/App/Http/Controllers/GuildController';
import { update as updateRead } from '@/actions/App/Http/Controllers/GuildReadController';
import { CommitCard } from '@/components/commit-card';
import { CommitComposer } from '@/components/commit-composer';
import { EmptyFeed } from '@/components/commit-feed';
import { GraphCanvas } from '@/components/graph-canvas';
import { RailSegment } from '@/components/graph-rail';
import { GuildDialog } from '@/components/guild-dialog';
import { GuildIcon } from '@/components/guild-icon';
import {
    GuildRemovalButton,
    GuildReportButton,
} from '@/components/guild-moderation';
import {
    GuildMemberCount,
    GuildPreferences,
} from '@/components/guild-preferences';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { WordmarkText } from '@/components/wordmark-text';
import AppLayout from '@/layouts/app-layout';
import { firstReadCommit } from '@/lib/guild-read-marker';
import type { Commit } from '@/types';
import type { Guild, JoinedGuild } from '@/types/guild';

export default function Show({
    handle,
    readFrom,
    guild,
    canModerate,
    pinnedCommit,
    commits,
    page,
    hasMore,
}: {
    handle: string;
    readFrom: string | null;
    guild: Guild;
    canModerate: boolean;
    pinnedCommit: Commit | null;
    commits: Commit[];
    page: number;
    hasMore: boolean;
}) {
    const { auth } = usePage().props;
    const [isLoading, setIsLoading] = useState(false);
    const [isPinning, setIsPinning] = useState(false);
    const pinnedCommitId = pinnedCommit?.id;
    const shownCommits = pinnedCommit
        ? [
              pinnedCommit,
              ...commits.filter((commit) => commit.id !== pinnedCommit.id),
          ]
        : commits;
    const readingPosition = useRef<{
        row: HTMLElement;
        top: number;
        scrollY: number;
        overflowAnchor: string;
    } | null>(null);
    const guildId = guild.id;
    const isJoined = guild.is_joined;
    const firstReadCommitId = firstReadCommit(
        commits.filter((commit) => commit.id !== pinnedCommitId),
        readFrom,
        auth.user?.handle,
    )?.id;

    useEffect(() => {
        if (!isJoined) {
            return;
        }

        router
            .optimistic<{ joinedGuilds: JoinedGuild[] }>((props) => ({
                joinedGuilds: props.joinedGuilds.map((joinedGuild) =>
                    joinedGuild.handle === handle
                        ? { ...joinedGuild, missed_commits: 0 }
                        : joinedGuild,
                ),
            }))
            .put(
                updateRead.url(guildId),
                {},
                {
                    async: true,
                    only: ['joinedGuilds'],
                    preserveScroll: true,
                    preserveState: true,
                    preserveUrl: true,
                },
            );
    }, [guildId, handle, isJoined]);

    useLayoutEffect(() => {
        const anchor = readingPosition.current;
        if (!anchor) return;

        if (anchor.row.isConnected) {
            const targetTop = anchor.top - (window.scrollY - anchor.scrollY);
            window.scrollBy(
                0,
                anchor.row.getBoundingClientRect().top - targetTop,
            );
            anchor.top = targetTop;
            anchor.scrollY = window.scrollY;
        }

        if (!isPinning) {
            document.documentElement.style.overflowAnchor =
                anchor.overflowAnchor;
            readingPosition.current = null;
        }
    }, [commits, pinnedCommit, isPinning]);

    useLayoutEffect(
        () => () => {
            if (readingPosition.current) {
                document.documentElement.style.overflowAnchor =
                    readingPosition.current.overflowAnchor;
            }
        },
        [],
    );

    function pin(commit: Commit) {
        const pinned = pinnedCommitId !== commit.id;
        const row = Array.from(
            document.querySelectorAll<HTMLElement>('[data-commit]'),
        ).find(
            (row) =>
                row.dataset.commit !== commit.id &&
                row.dataset.commit !== pinnedCommitId &&
                row.getBoundingClientRect().bottom > 0 &&
                row.getBoundingClientRect().top < window.innerHeight,
        );
        if (row && window.scrollY > 0) {
            readingPosition.current = {
                row,
                top: row.getBoundingClientRect().top,
                scrollY: window.scrollY,
                overflowAnchor: document.documentElement.style.overflowAnchor,
            };
            document.documentElement.style.overflowAnchor = 'none';
        }
        setIsPinning(true);
        router
            .optimistic(() => ({
                pinnedCommit: pinned ? commit : null,
            }))
            .put(
                updatePin.url({ guild: handle, commit: commit.id }),
                { pinned },
                {
                    only: ['pinnedCommit', 'canModerate'],
                    preserveScroll: true,
                    preserveUrl: true,
                    onSuccess: () => router.flushAll(),
                    onError: (errors) =>
                        toast.error(errors.pin ?? 'Could not update the pin.'),
                    onFinish: () => setIsPinning(false),
                },
            );
    }

    if (!guild) {
        return (
            <p className="p-6 text-muted-foreground">
                This guild is no longer available.
            </p>
        );
    }

    return (
        <>
            <Head title={`${guild.name} · Guild`} />
            <header className="border-b px-4 py-6 sm:px-6">
                <div className="flex items-start gap-3">
                    <GuildIcon
                        guild={guild}
                        className="size-12 rounded-md text-3xl"
                    />
                    <div className="min-w-0 flex-1">
                        <h1 className="text-2xl font-semibold tracking-tight wrap-anywhere">
                            {guild.name}
                        </h1>
                    </div>
                    <div className="flex shrink-0 items-center gap-2">
                        <GuildPreferences guild={guild} />
                        {canModerate && (
                            <GuildDialog
                                key={handle}
                                handle={handle}
                                guild={guild}
                            >
                                <Button
                                    variant="outline"
                                    size="icon"
                                    aria-label="Edit guild"
                                    title="Edit guild"
                                >
                                    <Pencil className="size-4" />
                                </Button>
                            </GuildDialog>
                        )}
                    </div>
                </div>
                <div className="mt-2 flex flex-wrap items-center gap-x-2 gap-y-1 font-mono text-[13px] text-muted-foreground">
                    <span className="min-w-0 truncate" title={`#${handle}`}>
                        #{handle}
                    </span>
                    <span aria-hidden>·</span>
                    <span>
                        {guild.is_paused
                            ? 'Public guild · Paused'
                            : 'Public guild'}
                    </span>
                    <span aria-hidden>·</span>
                    <GuildMemberCount guild={guild} />
                </div>
                <p className="mt-4 text-[15px] leading-relaxed wrap-anywhere whitespace-pre-wrap">
                    <WordmarkText>{guild.description}</WordmarkText>
                </p>
                <div className="mt-4 flex flex-wrap items-center gap-3">
                    {!canModerate && <GuildReportButton guild={guild} />}
                    {auth.isAdmin && !guild.is_paused && (
                        <GuildRemovalButton guild={guild} />
                    )}
                    <p className="text-sm text-muted-foreground">
                        {guild.is_paused
                            ? 'This guild is paused until it has a moderator. Its board and members are preserved.'
                            : `Include #${handle} in any commit to post here.`}
                    </p>
                </div>
            </header>
            {!guild.is_paused && (
                <CommitComposer
                    key={handle}
                    id="guild-commit-body"
                    guildHandle={handle}
                    onCommitted={
                        page > 1
                            ? () =>
                                  router.get(
                                      show.url(handle),
                                      {},
                                      {
                                          only: [
                                              'commits',
                                              'page',
                                              'hasMore',
                                              'pinnedCommit',
                                              'canModerate',
                                          ],
                                          preserveScroll: true,
                                      },
                                  )
                            : undefined
                    }
                />
            )}
            <section aria-label="Bulletin board">
                <h2 className="flex h-11 items-center border-b px-4 font-semibold sm:px-6">
                    Bulletin board · Latest commits
                </h2>
                {shownCommits.length === 0 ? (
                    <div className="px-6 py-16 text-center sm:px-12">
                        <EmptyFeed command="No commits yet.">
                            Post to the board or include #{handle} in a commit.
                        </EmptyFeed>
                    </div>
                ) : (
                    <GraphCanvas>
                        {shownCommits.map((commit) => {
                            const isPinned = pinnedCommitId === commit.id;

                            return (
                                <section
                                    key={commit.id}
                                    aria-label={
                                        isPinned ? 'Pinned commit' : undefined
                                    }
                                >
                                    {commit.id === firstReadCommitId && (
                                        <div
                                            className="flex"
                                            data-test="guild-read-marker"
                                        >
                                            <RailSegment />
                                            <p className="pt-3 pb-1 font-mono text-xs text-primary">
                                                ↑ new since your last visit
                                            </p>
                                        </div>
                                    )}
                                    {isPinned && (
                                        <div className="flex">
                                            <RailSegment />
                                            <p className="flex items-center gap-1.5 pt-3 pb-1 font-mono text-xs text-muted-foreground">
                                                <Pin
                                                    aria-hidden
                                                    className="size-3"
                                                />
                                                Pinned to board
                                            </p>
                                        </div>
                                    )}
                                    <CommitCard
                                        commit={commit}
                                        endsWithDot
                                        threadActions={
                                            commit.parent_id ? (
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    className="min-h-9 px-2 text-[13px] text-muted-foreground"
                                                    asChild
                                                >
                                                    <Link
                                                        prefetch
                                                        href={showCommit({
                                                            profile:
                                                                commit.author,
                                                            commit,
                                                        })}
                                                    >
                                                        View full thread
                                                    </Link>
                                                </Button>
                                            ) : (
                                                canModerate && (
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        className="min-h-9 px-2 text-[13px] text-muted-foreground aria-pressed:text-primary"
                                                        aria-label={`${isPinned ? 'Unpin from' : 'Pin to'} board`}
                                                        aria-pressed={isPinned}
                                                        title={
                                                            isPinned
                                                                ? 'Unpin from board'
                                                                : 'Pin to board'
                                                        }
                                                        disabled={
                                                            isPinning ||
                                                            isLoading
                                                        }
                                                        onClick={() =>
                                                            pin(commit)
                                                        }
                                                    >
                                                        <Pin
                                                            aria-hidden
                                                            className="size-3.5"
                                                        />
                                                        Pin
                                                    </Button>
                                                )
                                            )
                                        }
                                    />
                                </section>
                            );
                        })}
                    </GraphCanvas>
                )}
            </section>
            {hasMore && (
                <nav
                    aria-label="Guild commits"
                    className="flex justify-center border-t p-4 sm:px-6"
                >
                    <Button
                        variant="outline"
                        disabled={isLoading || isPinning}
                        onClick={() =>
                            router.get(
                                show.url(handle, { query: { page: page + 1 } }),
                                {},
                                {
                                    only: [
                                        'commits',
                                        'page',
                                        'hasMore',
                                        'pinnedCommit',
                                        'canModerate',
                                    ],
                                    preserveScroll: true,
                                    preserveState: true,
                                    preserveUrl: true,
                                    replace: true,
                                    onStart: () => setIsLoading(true),
                                    onFinish: () => setIsLoading(false),
                                },
                            )
                        }
                    >
                        <span className="grid size-4 place-items-center">
                            {isLoading && <Spinner className="size-4" />}
                        </span>
                        Load more
                        <span aria-hidden className="size-4" />
                    </Button>
                </nav>
            )}
        </>
    );
}

Show.layout = AppLayout;
