import { Head, Link, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { show } from '@/actions/App/Http/Controllers/GuildController';
import { GuildIcon } from '@/components/guild-icon';
import {
    GuildMemberCount,
    GuildPreferences,
} from '@/components/guild-preferences';
import AppLayout from '@/layouts/app-layout';

export default function Index() {
    const { guilds } = usePage().props;
    const [order] = useState(() => guilds?.map((guild) => guild.id) ?? []);
    const ranked = guilds?.toSorted(
        (a, b) => order.indexOf(a.id) - order.indexOf(b.id),
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
            </header>
            {guilds?.length ? (
                <ul className="divide-y">
                    {ranked?.map((guild) => (
                        <li
                            key={guild.id}
                            className="space-y-3 px-4 py-4 sm:px-6"
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
                                className="line-clamp-2 text-sm wrap-anywhere"
                                title={guild.description}
                            >
                                {guild.description}
                            </p>
                            <div className="flex items-center justify-between gap-3">
                                <GuildMemberCount guild={guild} />
                                <GuildPreferences guild={guild} />
                            </div>
                        </li>
                    ))}
                </ul>
            ) : (
                <p className="p-6 text-sm text-muted-foreground">
                    No guilds yet. Search for a hashtag to create one.
                </p>
            )}
        </>
    );
}

Index.layout = AppLayout;
