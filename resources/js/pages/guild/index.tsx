import { Form, Head, Link, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Plus, Search } from 'lucide-react';
import { useEffect, useState } from 'react';
import { index, show } from '@/actions/App/Http/Controllers/GuildController';
import { GuildDialog } from '@/components/guild-dialog';
import { GuildIcon } from '@/components/guild-icon';
import {
    GuildMemberCount,
    GuildPreferences,
} from '@/components/guild-preferences';
import { Button } from '@/components/ui/button';
import { WordmarkText } from '@/components/wordmark-text';
import AppLayout from '@/layouts/app-layout';
import type { Guild } from '@/types/guild';

export default function Index({
    query,
    guildPage,
    guilds = [],
}: {
    query: string;
    guilds?: Guild[];
    guildPage: {
        data: Guild[];
        current_page: number;
        last_page: number;
        total: number;
    };
}) {
    const [search, setSearch] = useState({ query, value: query });
    if (search.query !== query) {
        setSearch({ query, value: query });
    }
    const ranked = guildPage.data.map(
        (guild) => guilds.find((fresh) => fresh.id === guild.id) ?? guild,
    );

    useEffect(
        () =>
            router.on('before', ({ detail: { visit } }) => {
                if (
                    !visit.prefetch &&
                    visit.only.includes('guilds') &&
                    !visit.only.includes('guildPage')
                ) {
                    visit.headers['X-Guild-Handles'] = JSON.stringify(
                        guildPage.data.map((guild) => guild.handle),
                    ).replace(
                        /[\u007f-\uffff]/g,
                        (character) =>
                            `\\u${character.charCodeAt(0).toString(16).padStart(4, '0')}`,
                    );
                }
            }),
        [guildPage.data],
    );

    return (
        <>
            <Head title="Guilds" />
            <header className="border-b px-4 py-4 sm:px-6">
                <h1 className="font-semibold">Guilds</h1>
                <p className="mt-1 text-sm text-muted-foreground">
                    Public groups, biggest first. Join to see their commits in
                    Following.
                </p>
                <Form
                    {...index.form()}
                    options={{
                        only: ['guildPage', 'query', 'guilds'],
                        preserveState: true,
                        preserveScroll: true,
                    }}
                    role="search"
                    className="mt-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="flex gap-2">
                                <div className="relative min-w-0 flex-1">
                                    <Search
                                        aria-hidden
                                        className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                    />
                                    <input
                                        type="search"
                                        name="q"
                                        value={search.value}
                                        onChange={(event) =>
                                            setSearch({
                                                query,
                                                value: event.target.value,
                                            })
                                        }
                                        maxLength={100}
                                        aria-label="Search guilds"
                                        aria-describedby="guild-search-help"
                                        placeholder="Search guilds"
                                        className="h-10 w-full rounded-md border bg-background pr-3 pl-9 text-base outline-none placeholder:text-muted-foreground focus-visible:border-ring md:text-sm"
                                    />
                                </div>
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    className="h-10 min-w-20"
                                >
                                    Search
                                </Button>
                            </div>
                            <p
                                id="guild-search-help"
                                className="mt-2 min-h-5 text-xs text-muted-foreground"
                            >
                                {errors.q ??
                                    'Search names, #handles, and descriptions.'}
                            </p>
                        </>
                    )}
                </Form>
            </header>
            <div className="flex items-center justify-between gap-3 border-b px-4 py-3 sm:px-6">
                <p className="text-sm text-muted-foreground">
                    Start a public group.
                </p>
                <GuildDialog>
                    <Button variant="outline">
                        <Plus aria-hidden className="size-4" />
                        Create a guild
                    </Button>
                </GuildDialog>
            </div>
            <nav
                aria-label="Guild pages"
                className="flex h-14 items-center justify-between gap-2 border-b px-4 sm:px-6"
            >
                <p className="text-sm text-muted-foreground" aria-live="polite">
                    <span className="tabular-nums">{guildPage.total}</span>{' '}
                    {guildPage.total === 1 ? 'guild' : 'guilds'}
                </p>
                <div className="flex items-center gap-2">
                    {guildPage.current_page > 1 ? (
                        <Button
                            asChild
                            variant="outline"
                            size="icon"
                            className="size-9"
                        >
                            <Link
                                prefetch
                                href={index({
                                    query: {
                                        q: query,
                                        page: guildPage.current_page - 1,
                                    },
                                })}
                                only={['guildPage', 'query', 'guilds']}
                                preserveState
                                preserveScroll
                                aria-label="Previous guild page"
                            >
                                <ChevronLeft aria-hidden className="size-4" />
                            </Link>
                        </Button>
                    ) : (
                        <Button
                            variant="outline"
                            size="icon"
                            className="size-9"
                            disabled
                            aria-label="Previous guild page"
                        >
                            <ChevronLeft aria-hidden className="size-4" />
                        </Button>
                    )}
                    <span
                        className="w-24 text-center text-sm text-muted-foreground tabular-nums"
                        aria-live="polite"
                    >
                        Page {guildPage.current_page} of {guildPage.last_page}
                    </span>
                    {guildPage.current_page < guildPage.last_page ? (
                        <Button
                            asChild
                            variant="outline"
                            size="icon"
                            className="size-9"
                        >
                            <Link
                                prefetch
                                href={index({
                                    query: {
                                        q: query,
                                        page: guildPage.current_page + 1,
                                    },
                                })}
                                only={['guildPage', 'query', 'guilds']}
                                preserveState
                                preserveScroll
                                aria-label="Next guild page"
                            >
                                <ChevronRight aria-hidden className="size-4" />
                            </Link>
                        </Button>
                    ) : (
                        <Button
                            variant="outline"
                            size="icon"
                            className="size-9"
                            disabled
                            aria-label="Next guild page"
                        >
                            <ChevronRight aria-hidden className="size-4" />
                        </Button>
                    )}
                </div>
            </nav>
            {ranked.length ? (
                <ul className="grid grid-cols-1 gap-3 p-4 min-[540px]:grid-cols-2 sm:p-6">
                    {ranked.map((guild) => (
                        <li
                            key={guild.id}
                            className="flex min-w-0 flex-col gap-3 rounded-lg border bg-card p-4"
                        >
                            <div className="flex items-start gap-3">
                                <GuildIcon
                                    guild={guild}
                                    className="size-10 rounded-md text-2xl"
                                />
                                <div className="min-w-0 flex-1">
                                    <Link
                                        prefetch
                                        href={show(guild.handle)}
                                        className="block truncate font-semibold hover:text-primary"
                                        title={guild.name}
                                    >
                                        {guild.name}
                                    </Link>
                                    <p
                                        className="truncate font-mono text-[13px] text-muted-foreground"
                                        title={`#${guild.handle}`}
                                    >
                                        #{guild.handle}
                                    </p>
                                </div>
                            </div>
                            <p
                                className="line-clamp-2 min-h-10 text-sm wrap-anywhere"
                                title={guild.description}
                            >
                                <WordmarkText>{guild.description}</WordmarkText>
                            </p>
                            <div className="mt-auto flex flex-wrap items-center justify-between gap-2">
                                <GuildMemberCount guild={guild} />
                                <GuildPreferences guild={guild} />
                            </div>
                        </li>
                    ))}
                </ul>
            ) : (
                <p className="p-6 text-sm text-muted-foreground">
                    {query !== ''
                        ? 'No guilds found on this page. Try another keyword or return to the first page.'
                        : guildPage.current_page > 1
                          ? 'No guilds on this page. Return to the first page.'
                          : 'No guilds yet. Create the first one.'}
                    {(query !== '' || guildPage.current_page > 1) && (
                        <Link
                            prefetch
                            href={index()}
                            only={['guildPage', 'query', 'guilds']}
                            className="mt-2 block text-primary hover:underline"
                        >
                            Browse all guilds
                        </Link>
                    )}
                </p>
            )}
        </>
    );
}

Index.layout = AppLayout;
