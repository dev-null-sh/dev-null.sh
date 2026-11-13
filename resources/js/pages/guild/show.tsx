import { Head, Link, router, usePage } from '@inertiajs/react';
import { show } from '@/actions/App/Http/Controllers/GuildController';
import { CommitComposer } from '@/components/commit-composer';
import { SearchResults } from '@/components/commit-search-results';
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
import AppLayout from '@/layouts/app-layout';
import type { Commit } from '@/types';

export default function Show({
    handle,
    canModerate,
    commits,
    page,
    hasMore,
}: {
    handle: string;
    canModerate: boolean;
    commits: Commit[];
    page: number;
    hasMore: boolean;
}) {
    const { guilds, auth } = usePage().props;
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
                        <p
                            className="mt-1 truncate font-mono text-[13px] text-muted-foreground"
                            title={`#${handle} · Public guild`}
                        >
                            #{handle} · Public guild
                        </p>
                    </div>
                </div>
                <p className="mt-4 text-[15px] leading-relaxed wrap-anywhere whitespace-pre-wrap">
                    {guild.description}
                </p>
                <div className="mt-4 flex flex-wrap items-center gap-3">
                    <GuildPreferences guild={guild} />
                    <GuildMemberCount guild={guild} />
                    {canModerate && (
                        <GuildDialog key={handle} handle={handle} guild={guild}>
                            <Button variant="outline">Edit guild</Button>
                        </GuildDialog>
                    )}
                    {!canModerate && <GuildReportButton guild={guild} />}
                    {auth.isAdmin && <GuildRemovalButton guild={guild} />}
                    <p className="text-sm text-muted-foreground">
                        Include #{handle} in a commit to post here.
                    </p>
                </div>
            </header>
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
            <SearchResults
                results={{ term: `#${handle}`, commits }}
                heading="Bulletin board · Latest commits"
                empty={`No commits with #${handle} yet.`}
            />
            {(page > 1 || hasMore) && (
                <nav
                    aria-label="Guild commits"
                    className="flex justify-between gap-3 border-t p-4 sm:px-6"
                >
                    <div>
                        {page > 1 && (
                            <Button asChild variant="outline">
                                <Link
                                    prefetch
                                    href={show(handle, {
                                        query: { page: page - 1 },
                                    })}
                                    only={['commits', 'page', 'hasMore']}
                                >
                                    Newer commits
                                </Link>
                            </Button>
                        )}
                    </div>
                    {hasMore && (
                        <Button asChild variant="outline">
                            <Link
                                prefetch
                                href={show(handle, {
                                    query: { page: page + 1 },
                                })}
                                only={['commits', 'page', 'hasMore']}
                            >
                                Older commits
                            </Link>
                        </Button>
                    )}
                </nav>
            )}
        </>
    );
}

Show.layout = AppLayout;
