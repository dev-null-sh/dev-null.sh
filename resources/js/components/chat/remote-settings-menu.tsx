import { Link, router, usePage } from '@inertiajs/react';
import { Info, Settings2 } from 'lucide-react';
import { useId } from 'react';
import { toast } from 'sonner';
import { update } from '@/actions/App/Http/Controllers/UserRemotePreferencesController';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { edit } from '@/routes/user-profile';
import type { RemotesFrom } from '@/types';

/**
 * Who can push to the user straight away, and whether they share pulls. Changing either only affects
 * conversations started from now on.
 */
export function RemoteSettingsMenu() {
    const { auth } = usePage().props;
    const fromDescription = useId();
    const pullsDescription = useId();

    if (auth.remotes === null) {
        return null;
    }

    const { pull_receipts: pullReceipts, requests, everyone } = auth.remotes;
    const from: RemotesFrom = everyone ? auth.remotes.from : 'following';

    const save = (preferences: { from: RemotesFrom; pull_receipts: boolean }) =>
        router.put(update.url(), preferences, {
            preserveScroll: true,
            preserveState: true,
            only: ['auth'],
            onError: () =>
                toast.error('Could not save your remote settings. Try again.'),
        });

    return (
        <DropdownMenu modal={false}>
            <Tooltip>
                <TooltipTrigger asChild>
                    <DropdownMenuTrigger
                        aria-label="Remote settings"
                        className="grid size-8 place-items-center rounded-md text-muted-foreground transition-colors hover:bg-accent hover:text-foreground data-[state=open]:bg-accent data-[state=open]:text-foreground"
                        data-test="remote-settings"
                    >
                        <Settings2 className="size-[18px]" />
                    </DropdownMenuTrigger>
                </TooltipTrigger>
                <TooltipContent>Remote settings</TooltipContent>
            </Tooltip>
            <DropdownMenuContent
                align="end"
                className="max-h-[var(--radix-dropdown-menu-content-available-height)] w-72 overflow-y-auto"
            >
                {everyone && (
                    <>
                        <DropdownMenuLabel className="font-mono text-xs font-normal text-muted-foreground">
                            who can push to you
                        </DropdownMenuLabel>
                        <DropdownMenuRadioGroup
                            aria-describedby={fromDescription}
                            value={from}
                            onValueChange={(value) =>
                                save({
                                    from: value as RemotesFrom,
                                    pull_receipts: pullReceipts,
                                })
                            }
                        >
                            <DropdownMenuRadioItem value="following">
                                People you follow
                            </DropdownMenuRadioItem>
                            <DropdownMenuRadioItem value="everyone">
                                Everyone
                            </DropdownMenuRadioItem>
                        </DropdownMenuRadioGroup>
                        <div className="mx-2 mt-2 mb-2 flex items-start gap-2 border-t pt-2 text-xs leading-relaxed text-muted-foreground">
                            <Info
                                className="mt-0.5 size-3.5 shrink-0"
                                aria-hidden
                            />
                            <p
                                id={fromDescription}
                                className="cursor-text select-text"
                            >
                                {requests
                                    ? 'Anyone else lands in Fetched until you merge them. Requests already fetched stay there.'
                                    : 'Anyone else can push to you once you follow them.'}
                            </p>
                        </div>
                        <DropdownMenuSeparator />
                    </>
                )}
                <DropdownMenuCheckboxItem
                    aria-describedby={pullsDescription}
                    checked={pullReceipts}
                    onCheckedChange={(checked) =>
                        save({ from, pull_receipts: checked === true })
                    }
                    onSelect={(event) => event.preventDefault()}
                    data-test="pull-receipts"
                >
                    Share pulls
                </DropdownMenuCheckboxItem>
                <div className="mx-2 mt-2 mb-2 flex items-start gap-2 border-t pt-2 text-xs leading-relaxed text-muted-foreground">
                    <Info className="mt-0.5 size-3.5 shrink-0" aria-hidden />
                    <p
                        id={pullsDescription}
                        className="cursor-text select-text"
                    >
                        When off, nobody sees when you pull their messages, and
                        you don&apos;t see when they pull yours.
                    </p>
                </div>
                <DropdownMenuSeparator />
                <DropdownMenuItem asChild>
                    <Link href={edit()}>Turn remotes off in settings</Link>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
