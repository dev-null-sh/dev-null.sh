import { Link, usePage } from '@inertiajs/react';
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

export function ProfileGuilds({
    handles,
    handle,
}: {
    handles: string[];
    handle: string;
}) {
    const { guilds } = usePage().props;
    const [listedHandles, setListedHandles] = useState(handles);

    return (
        <Dialog
            onOpenChange={(open) => {
                if (open) {
                    setListedHandles(handles);
                }
            }}
        >
            <DialogTrigger asChild>
                <button type="button" className="group flex gap-1.5">
                    <span
                        className="font-mono font-medium group-hover:underline"
                        title={exactCountTitle(handles.length)}
                    >
                        {formatCount(handles.length)}
                    </span>
                    <span className="text-muted-foreground group-hover:underline">
                        {handles.length === 1 ? 'guild' : 'guilds'}
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
                    {listedHandles.map((joinedHandle) => {
                        const guild = guilds?.find(
                            (guild) => guild.handle === joinedHandle,
                        );

                        return (
                            <li
                                key={joinedHandle}
                                className="flex items-center gap-2 pr-3"
                            >
                                <DialogClose asChild>
                                    <Link
                                        prefetch
                                        href={show(joinedHandle)}
                                        className="flex min-w-0 flex-1 items-center gap-3 rounded-md p-3 hover:bg-accent focus-visible:bg-accent"
                                    >
                                        {guild && (
                                            <GuildIcon
                                                guild={guild}
                                                className="size-10 rounded-md text-2xl"
                                            />
                                        )}
                                        <span className="min-w-0">
                                            <span
                                                className="block truncate font-semibold"
                                                title={
                                                    guild?.name ?? joinedHandle
                                                }
                                            >
                                                {guild?.name ?? joinedHandle}
                                            </span>
                                            <span
                                                className="block truncate font-mono text-[13px] text-muted-foreground"
                                                title={`#${joinedHandle}`}
                                            >
                                                #{joinedHandle}
                                            </span>
                                        </span>
                                    </Link>
                                </DialogClose>
                                {guild && (
                                    <GuildPreferences
                                        guild={guild}
                                        showMute={false}
                                    />
                                )}
                            </li>
                        );
                    })}
                    {listedHandles.length === 0 && (
                        <li className="p-3 text-sm text-muted-foreground">
                            No guilds joined yet.
                        </li>
                    )}
                </ul>
            </DialogFloatingContent>
        </Dialog>
    );
}
