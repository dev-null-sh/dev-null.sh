import { router, usePage } from '@inertiajs/react';
import { Ban, Ellipsis, UserPlus, Volume2, VolumeX } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import UserBlockController from '@/actions/App/Http/Controllers/UserBlockController';
import UserMuteController from '@/actions/App/Http/Controllers/UserMuteController';
import { orgTitle } from '@/components/chat/org';
import { rememberBlock } from '@/components/follow-button';
import { clearMentionSuggestionsCache } from '@/components/mention-textarea';
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
    DropdownMenuPortal,
    DropdownMenuSeparator,
    DropdownMenuSub,
    DropdownMenuSubContent,
    DropdownMenuSubTrigger,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { addOrgMembers, ChatRequestError } from '@/lib/chat';
import { cn } from '@/lib/utils';
import type { MaintainedOrg, User } from '@/types';

export function UserSafetyMenu({
    user,
    isMuting,
    isBlocking,
    orgs = [],
    className,
    onOpenChange,
    onOrgAdded,
}: {
    user: Pick<User, 'id' | 'username'>;
    isMuting: boolean;
    isBlocking: boolean;
    orgs?: MaintainedOrg[];
    className?: string;
    onOpenChange?: (isOpen: boolean) => void;
    onOrgAdded?: (org: MaintainedOrg) => void;
}) {
    const viewer = usePage().props.auth.user;
    const [isMenuOpen, setIsMenuOpen] = useState(false);
    const [isConfirmingBlock, setIsConfirmingBlock] = useState(false);
    const [isSaving, setIsSaving] = useState(false);
    const [addedOrgIds, setAddedOrgIds] = useState<string[]>([]);

    const changeOpen = (menu: boolean, dialog: boolean) => {
        setIsMenuOpen(menu);
        setIsConfirmingBlock(dialog);
        onOpenChange?.(menu || dialog);
    };

    const visit = (
        action: Parameters<typeof router.visit>[0],
        onSuccess?: () => void,
    ) => {
        router.visit(action, {
            preserveScroll: true,
            onStart: () => setIsSaving(true),
            onFinish: () => {
                setIsSaving(false);
                changeOpen(false, false);
            },
            onSuccess: () => {
                clearMentionSuggestionsCache();

                onSuccess?.();

                router.flushAll();
            },
        });
    };

    /**
     * Adds them as a contributor who only sees what's pushed from now on, the same default as the org's own picker.
     */
    const addToOrg = (org: MaintainedOrg, title: string) => {
        setIsSaving(true);

        addOrgMembers(org, [user.username], false)
            .then(() => {
                setAddedOrgIds((ids) => [...ids, org.id]);
                onOrgAdded?.(org);
                toast.success(`Added @${user.username} to ${title}`);
            })
            .catch((error: unknown) =>
                toast.error(
                    error instanceof ChatRequestError
                        ? error.firstError()
                        : 'Something went wrong. Try again.',
                ),
            )
            .finally(() => setIsSaving(false));
    };

    return (
        <>
            <DropdownMenu
                modal={false}
                open={isMenuOpen}
                onOpenChange={(open) => changeOpen(open, isConfirmingBlock)}
            >
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="outline"
                        size="icon"
                        className={cn('shrink-0', className)}
                        aria-label={`More options for @${user.username}`}
                        data-test="user-safety-menu"
                    >
                        <Ellipsis />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="w-52">
                    {viewer && orgs.length > 0 && (
                        <>
                            <DropdownMenuSub>
                                <DropdownMenuSubTrigger
                                    disabled={isSaving}
                                    className="gap-2 data-disabled:pointer-events-none data-disabled:opacity-50 [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*='size-'])]:size-4 [&_svg:not([class*='text-'])]:text-muted-foreground"
                                    data-test="user-org-menu"
                                >
                                    <UserPlus />
                                    Add to org
                                </DropdownMenuSubTrigger>
                                <DropdownMenuPortal>
                                    <DropdownMenuSubContent
                                        collisionPadding={8}
                                        className="max-h-(--radix-dropdown-menu-content-available-height) w-56 max-w-(--radix-dropdown-menu-content-available-width) overflow-y-auto"
                                    >
                                        {orgs.map((org) => {
                                            const title = orgTitle(
                                                org,
                                                org.members,
                                                viewer,
                                            );
                                            const reason = addedOrgIds.includes(
                                                org.id,
                                            )
                                                ? 'already in the org'
                                                : org.reason;

                                            return (
                                                <DropdownMenuItem
                                                    key={org.id}
                                                    disabled={
                                                        isSaving ||
                                                        reason !== null
                                                    }
                                                    onSelect={() =>
                                                        addToOrg(org, title)
                                                    }
                                                    className="flex-col items-stretch gap-0"
                                                    data-test="user-org-option"
                                                >
                                                    <span
                                                        className="truncate"
                                                        title={title}
                                                    >
                                                        {title}
                                                    </span>
                                                    {reason !== null && (
                                                        <span className="truncate font-mono text-xs text-muted-foreground">
                                                            {reason}
                                                        </span>
                                                    )}
                                                </DropdownMenuItem>
                                            );
                                        })}
                                    </DropdownMenuSubContent>
                                </DropdownMenuPortal>
                            </DropdownMenuSub>
                            <DropdownMenuSeparator />
                        </>
                    )}
                    <DropdownMenuItem
                        disabled={isSaving}
                        onSelect={() =>
                            visit(
                                isMuting
                                    ? UserMuteController.destroy(user)
                                    : UserMuteController.store(user),
                            )
                        }
                    >
                        {isMuting ? <Volume2 /> : <VolumeX />}
                        {isMuting
                            ? `Unmute @${user.username}`
                            : `Mute @${user.username}`}
                    </DropdownMenuItem>
                    <DropdownMenuItem
                        variant={isBlocking ? 'default' : 'destructive'}
                        disabled={isSaving}
                        onSelect={() =>
                            isBlocking
                                ? visit(UserBlockController.destroy(user))
                                : changeOpen(false, true)
                        }
                    >
                        <Ban />
                        {isBlocking
                            ? `Unblock @${user.username}`
                            : `Block @${user.username}`}
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>

            <Dialog
                open={isConfirmingBlock}
                onOpenChange={(open) => changeOpen(false, open)}
            >
                <DialogContent>
                    <DialogTitle>Block @{user.username}?</DialogTitle>
                    <DialogDescription>
                        You'll stop following each other. Neither of you will be
                        able to message, follow, reply to, or star the other, or
                        see each other's commits. They won't be told, but
                        they'll see your profile as unavailable. You can unblock
                        them any time from your settings.
                    </DialogDescription>

                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button variant="outline">Cancel</Button>
                        </DialogClose>

                        <Button
                            variant="destructive"
                            disabled={isSaving}
                            onClick={() =>
                                visit(UserBlockController.store(user), () =>
                                    rememberBlock(user),
                                )
                            }
                            data-test="confirm-block-button"
                        >
                            Block
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
