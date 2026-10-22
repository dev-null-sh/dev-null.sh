import { Link, router } from '@inertiajs/react';
import {
    Archive,
    ArchiveRestore,
    Ban,
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
import {
    blockPartner,
    leaveBroadcast,
    removeOrgMember,
    setConversationArchived,
} from '@/lib/chat';
import type { ChatTarget } from '@/lib/chat';
import { cn } from '@/lib/utils';
import { show } from '@/routes/user';
import type { User } from '@/types';

/**
 * Stash (archive), pop (unarchive), and block or leave, from the conversation's own header or from its row in the
 * inbox.
 */
export function ConversationActions({
    target,
    title,
    user,
    isArchived,
    canLeave = true,
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
    /**
     * Whether the user may leave at all: an owner stays with their broadcast.
     */
    canLeave?: boolean;
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

    const changeOpen = (menu: boolean, dialog: boolean) => {
        setIsMenuOpen(menu);
        setIsConfirmingBlock(dialog);
        onOpenChange?.(menu || dialog);
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

        if (target.kind === 'broadcast') {
            setIsBlocking(true);

            try {
                await leaveBroadcast(target.broadcast);
                changeOpen(false, false);
                onLeave?.();
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
                onOpenChange={(open) => changeOpen(open, isConfirmingBlock)}
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
                    <DropdownMenuSeparator />
                    {(target.kind !== 'broadcast' || canLeave) && (
                        <DropdownMenuItem
                            variant="destructive"
                            onSelect={() => changeOpen(false, true)}
                            data-test={
                                target.kind === 'org'
                                    ? 'leave-org'
                                    : target.kind === 'broadcast'
                                      ? 'leave-broadcast'
                                      : undefined
                            }
                        >
                            {target.kind === 'direct' ? <Ban /> : <LogOut />}
                            {target.kind === 'direct'
                                ? `Block ${title}`
                                : target.kind === 'org'
                                  ? 'Leave org'
                                  : 'Leave broadcast'}
                        </DropdownMenuItem>
                    )}
                </DropdownMenuContent>
            </DropdownMenu>

            <Dialog
                open={isConfirmingBlock}
                onOpenChange={(open) => changeOpen(false, open)}
            >
                <DialogContent onClick={(event) => event.stopPropagation()}>
                    <DialogTitle className="truncate" title={title}>
                        {target.kind === 'direct' ? 'Block' : 'Leave'} {title}?
                    </DialogTitle>
                    <DialogDescription>
                        {target.kind === 'org'
                            ? 'You stop seeing the org and its history. A maintainer would have to add you back. If you are its only maintainer, the longest-standing member takes over.'
                            : target.kind === 'broadcast'
                              ? 'You stop seeing the broadcast and what is pushed to it. Coming back starts where the history you have already seen ends.'
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
                            {target.kind === 'direct' ? 'Block' : 'Leave'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
