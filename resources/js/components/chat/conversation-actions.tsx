import { Link, router } from '@inertiajs/react';
import {
    Archive,
    ArchiveRestore,
    Ban,
    Bell,
    BellOff,
    Ellipsis,
    LogOut,
    UserRound,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { rememberBlock } from '@/components/follow-button';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useChat } from '@/hooks/use-chat';
import { useNow } from '@/hooks/use-now';
import {
    blockPartner,
    removeOrgMember,
    setConversationArchived,
    setConversationMute,
    targetKey,
} from '@/lib/chat';
import type { ChatTarget, MuteDuration } from '@/lib/chat';
import { isRemoteMuted } from '@/lib/remote-notifications';
import { cn } from '@/lib/utils';
import { show } from '@/routes/user';
import type { ConversationSummary, User } from '@/types';

/**
 * Stash (archive), pop (unarchive), and block or leave, from the conversation's own header or from its row in the
 * inbox.
 */
export function ConversationActions({
    target,
    title,
    user,
    isArchived,
    conversation,
    onLeave,
    onBlocked,
    onOpenChange,
    className,
}: {
    target: ChatTarget;
    /**
     * How the conversation is called in messages: a handle, or an org's name.
     */
    title: string;
    user: User;
    isArchived: boolean;
    conversation?: ConversationSummary;
    /**
     * Called once the conversation leaves the list being shown, so an open thread can step back to the inbox.
     */
    onLeave?: () => void;
    /**
     * Called after blocking, once the conversation is gone, instead of onLeave when given. It should make an
     * Inertia visit, which brings the server's confirmation toast.
     */
    onBlocked?: () => void;
    onOpenChange?: (isOpen: boolean) => void;
    className?: string;
}) {
    const [isMenuOpen, setIsMenuOpen] = useState(false);
    const [isConfirmingBlock, setIsConfirmingBlock] = useState(false);
    const [isBlocking, setIsBlocking] = useState(false);
    const [isChoosingMute, setIsChoosingMute] = useState(false);
    const [isSavingMute, setIsSavingMute] = useState(false);
    const chat = useChat();
    const now = useNow();
    const key = targetKey(target);
    const preference = chat.mutePreferences[key];
    const until =
        preference !== undefined
            ? preference.until
            : (conversation?.muted_until_at ?? null);
    const isMuted = isRemoteMuted(until, now.getTime());

    const changeOpen = (menu: boolean, dialog: boolean, mute = false) => {
        setIsMenuOpen(menu);
        setIsConfirmingBlock(dialog);
        setIsChoosingMute(mute);
        onOpenChange?.(menu || dialog || mute);
    };

    const mute = async (duration: MuteDuration) => {
        setIsSavingMute(true);
        try {
            await setConversationMute(target, duration);
            changeOpen(false, false);
            toast.success(
                duration === 'off' ? `Unmuted ${title}.` : `Muted ${title}.`,
            );
        } catch {
            toast.error('Could not update the mute. Try again.');
        } finally {
            setIsSavingMute(false);
        }
    };

    const archive = async () => {
        const archiving = !isArchived;

        onLeave?.();

        if (!(await setConversationArchived(target, archiving))) {
            toast.error('Could not update the conversation. Try again.');

            return;
        }

        toast.success(
            archiving
                ? `Stashed ${title}. It pops back when someone writes.`
                : `Popped ${title} back into Open.`,
        );
    };

    const block = async () => {
        if (target.kind === 'org') {
            setIsBlocking(true);

            try {
                await removeOrgMember(target.org, user, true);
                changeOpen(false, false);
                (onBlocked ?? onLeave)?.();
            } catch {
                toast.error(`Could not leave ${title}. Try again.`);
            } finally {
                setIsBlocking(false);
            }

            return;
        }

        setIsBlocking(true);
        const isBlocked = await blockPartner(target.partner);
        setIsBlocking(false);
        changeOpen(false, false);

        if (!isBlocked) {
            toast.error(`Could not block ${title}. Try again.`);

            return;
        }

        rememberBlock(target.partner);
        router.flushAll();
        (onBlocked ?? onLeave)?.();
    };

    return (
        <>
            <DropdownMenu
                modal={false}
                open={isMenuOpen}
                onOpenChange={(open) =>
                    changeOpen(open, isConfirmingBlock, isChoosingMute)
                }
            >
                <DropdownMenuTrigger asChild>
                    <button
                        type="button"
                        aria-label={`More options for ${title}`}
                        onClick={(event) => event.stopPropagation()}
                        className={cn(
                            'grid size-8 shrink-0 place-items-center rounded-md text-muted-foreground transition-colors hover:bg-accent hover:text-foreground data-[state=open]:bg-accent data-[state=open]:text-foreground',
                            className,
                        )}
                        data-test="conversation-actions"
                    >
                        <Ellipsis className="size-4" />
                    </button>
                </DropdownMenuTrigger>
                <DropdownMenuContent
                    align="end"
                    className="w-56"
                    onClick={(event) => event.stopPropagation()}
                >
                    {target.kind === 'direct' && (
                        <DropdownMenuItem asChild>
                            <Link href={show(target.partner)}>
                                <UserRound />
                                View profile
                            </Link>
                        </DropdownMenuItem>
                    )}
                    <DropdownMenuItem onSelect={() => void archive()}>
                        {isArchived ? <ArchiveRestore /> : <Archive />}
                        {isArchived ? 'Pop' : 'Stash'}
                    </DropdownMenuItem>
                    <DropdownMenuItem
                        disabled={
                            isSavingMute ||
                            (target.kind === 'direct' &&
                                conversation === undefined &&
                                !chat.threads[key]?.messages.length)
                        }
                        onSelect={(event) => {
                            event.preventDefault();
                            if (isMuted) {
                                void mute('off');
                            } else {
                                changeOpen(false, false, true);
                            }
                        }}
                    >
                        {isMuted ? <Bell /> : <BellOff />}
                        {isMuted ? 'Unmute' : 'Mute'}
                    </DropdownMenuItem>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem
                        variant="destructive"
                        onSelect={() => changeOpen(false, true)}
                        data-test={
                            target.kind === 'org' ? 'leave-org' : undefined
                        }
                    >
                        {target.kind === 'org' ? <LogOut /> : <Ban />}
                        {target.kind === 'org' ? 'Leave org' : `Block ${title}`}
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>

            <Dialog
                open={isChoosingMute}
                onOpenChange={(open) => changeOpen(false, false, open)}
            >
                <DialogContent
                    className="max-h-[calc(100dvh-2rem)] overflow-y-auto sm:max-w-sm"
                    onClick={(event) => event.stopPropagation()}
                >
                    <DialogTitle className="truncate" title={title}>
                        Mute {title}
                    </DialogTitle>
                    <DialogDescription>
                        Silence browser notifications and sound on all your
                        devices. Messages and unread counts stay visible.
                    </DialogDescription>
                    <div className="grid gap-2">
                        {(
                            [
                                ['hour', 'For one hour'],
                                ['tomorrow', 'Until tomorrow'],
                                ['week', 'For one week'],
                                ['forever', 'Forever'],
                            ] as const
                        ).map(([duration, label]) => (
                            <Button
                                key={duration}
                                variant="outline"
                                disabled={isSavingMute}
                                onClick={() => void mute(duration)}
                            >
                                {label}
                            </Button>
                        ))}
                    </div>
                </DialogContent>
            </Dialog>

            <Dialog
                open={isConfirmingBlock}
                onOpenChange={(open) => changeOpen(false, open)}
            >
                <DialogContent onClick={(event) => event.stopPropagation()}>
                    <DialogTitle className="truncate" title={title}>
                        {target.kind === 'org' ? 'Leave' : 'Block'} {title}?
                    </DialogTitle>
                    <DialogDescription>
                        {target.kind === 'org'
                            ? 'You stop seeing the org and its history. A maintainer would have to add you back. If you are its only maintainer, the longest-standing member takes over.'
                            : "This conversation goes away for both of you, and neither of you can message, follow, reply to, or star the other. They won't be told. You can unblock them any time from your settings."}
                    </DialogDescription>

                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button variant="outline">Cancel</Button>
                        </DialogClose>
                        <Button
                            variant="destructive"
                            disabled={isBlocking}
                            onClick={() => void block()}
                            className="min-w-20"
                            data-test="confirm-block-button"
                        >
                            {target.kind === 'org' ? 'Leave' : 'Block'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
