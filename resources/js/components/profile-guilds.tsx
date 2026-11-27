import { Link } from '@inertiajs/react';
import { useState } from 'react';
import { show } from '@/actions/App/Http/Controllers/GuildController';
import { GuildIcon } from '@/components/guild-icon';
import { GuildPreferences } from '@/components/guild-preferences';
import {
    Dialog,
    DialogClose,
    DialogDescription,
    DialogFloatingContent,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { exactCountTitle, formatCount } from '@/lib/numbers';
import type { Guild } from '@/types/guild';

export function ProfileGuilds({
    guilds,
    handle,
}: {
    guilds: Guild[];
    handle: string;
}) {
    const [listing, setListing] = useState({ source: guilds, guilds });
    const listedGuilds = listing.guilds.map(
        (listed) => guilds.find((guild) => guild.id === listed.id) ?? listed,
    );
    if (listing.source !== guilds) {
        setListing({ source: guilds, guilds: listedGuilds });
    }
    return (
        <Dialog
            onOpenChange={(open) => {
                if (open) {
                    setListing({ source: guilds, guilds });
                }
            }}
        >
            <DialogTrigger asChild>
                <button type="button" className="group flex gap-1.5">
                    <span
                        className="font-mono font-medium group-hover:underline"
                        title={exactCountTitle(guilds.length)}
                    >
                        {formatCount(guilds.length)}
                    </span>
                    <span className="text-muted-foreground group-hover:underline">
                        {guilds.length === 1 ? 'guild' : 'guilds'}
                    </span>
                </button>
            </DialogTrigger>
            <DialogFloatingContent className="flex flex-col [&>[data-slot=dialog-panel]]:flex [&>[data-slot=dialog-panel]]:flex-col [&>[data-slot=dialog-panel]]:overflow-hidden [&>[data-slot=dialog-panel]]:p-0">
                <DialogHeader className="shrink-0 border-b p-6 text-left">
                    <DialogTitle>Guilds</DialogTitle>
                    <DialogDescription>
                        Guilds @{handle} has joined.
                    </DialogDescription>
                </DialogHeader>
                <ul className="min-h-0 overflow-y-auto px-3 pb-3">
                    {listedGuilds.map((guild) => (
                        <li
                            key={guild.handle}
                            className="flex items-center gap-2 pr-3"
                        >
                            <DialogClose asChild>
                                <Link
                                    prefetch
                                    href={show(guild.handle)}
                                    className="flex min-w-0 flex-1 items-center gap-3 rounded-md p-3 hover:bg-accent focus-visible:bg-accent"
                                >
                                    <GuildIcon
                                        guild={guild}
                                        className="size-10 rounded-md text-2xl"
                                    />
                                    <span className="min-w-0">
                                        <span
                                            className="block truncate font-semibold"
                                            title={guild.name}
                                        >
                                            {guild.name}
                                        </span>
                                        <span
                                            className="block truncate font-mono text-[13px] text-muted-foreground"
                                            title={`#${guild.handle}`}
                                        >
                                            #{guild.handle}
                                        </span>
                                    </span>
                                </Link>
                            </DialogClose>
                            <GuildPreferences
                                guild={guild}
                                showMute={false}
                                onChange={(fresh) =>
                                    setListing((current) => ({
                                        ...current,
                                        guilds: current.guilds.map((listed) =>
                                            listed.id === fresh.id
                                                ? fresh
                                                : listed,
                                        ),
                                    }))
                                }
                            />
                        </li>
                    ))}
                    {listedGuilds.length === 0 && (
                        <li className="p-3 text-sm text-muted-foreground">
                            No guilds joined yet.
                        </li>
                    )}
                </ul>
            </DialogFloatingContent>
        </Dialog>
    );
}
