import { Head, Link, router, usePage } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import { useState } from 'react';
import { show } from '@/actions/App/Http/Controllers/GuildController';
import { show as showProfile } from '@/actions/App/Http/Controllers/UserController';
import { CommitCard } from '@/components/commit-card';
import { CommitComposer } from '@/components/commit-composer';
import { EmptyFeed } from '@/components/commit-feed';
import { GraphCanvas } from '@/components/graph-canvas';
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
import { UserAvatar } from '@/components/user-avatar';
import AppLayout from '@/layouts/app-layout';
import type { Commit } from '@/types';

export default function Show({
    handle,
    canModerate,
    moderator,
    commits,
    page,
    hasMore,
}: {
    handle: string;
    canModerate: boolean;
    moderator: {
        handle: string;
        name: string;
        avatar_url: string | null;
    } | null;
    commits: Commit[];
    page: number;
    hasMore: boolean;
}) {
    const { guilds, auth } = usePage().props;
    const [isLoading, setIsLoading] = useState(false);
    const guild = guilds?.find((guild) => guild.handle === handle);

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
                    {guild.description}
                </p>
                {moderator && (
                    <div className="mt-4 flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                        <span>Moderators</span>
                        <Link
                            href={showProfile(moderator.handle)}
                            prefetch
                            className="flex items-center gap-1.5 text-foreground hover:underline"
                        >
                            <UserAvatar
                                user={moderator}
                                className="size-5"
                                pixels={40}
                            />
                            @{moderator.handle}
                        </Link>
                    </div>
                )}
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
                                          only: ['commits', 'page', 'hasMore'],
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
                {commits.length === 0 ? (
                    <div className="px-6 py-16 text-center sm:px-12">
                        <EmptyFeed command="No commits yet.">
                            Post to the board or include #{handle} in a commit.
                        </EmptyFeed>
                    </div>
                ) : (
                    <GraphCanvas>
                        {commits.map((commit) => (
                            <CommitCard
                                key={commit.id}
                                commit={commit}
                                endsWithDot
                            />
                        ))}
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
                        disabled={isLoading}
                        onClick={() =>
                            router.get(
                                show.url(handle, { query: { page: page + 1 } }),
                                {},
                                {
                                    only: ['commits', 'page', 'hasMore'],
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
