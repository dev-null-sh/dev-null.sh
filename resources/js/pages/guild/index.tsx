import { Head, Link, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import { show } from '@/actions/App/Http/Controllers/GuildController';
import { GuildDialog } from '@/components/guild-dialog';
import { GuildIcon } from '@/components/guild-icon';
import {
    GuildMemberCount,
    GuildPreferences,
} from '@/components/guild-preferences';
import { Button } from '@/components/ui/button';
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
            {guilds?.length ? (
                <ul className="grid grid-cols-1 gap-3 p-4 min-[540px]:grid-cols-2 sm:p-6">
                    {ranked?.map((guild) => (
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
                                {guild.description}
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
                    No guilds yet. Create the first one.
                </p>
            )}
        </>
    );
}

Index.layout = AppLayout;
