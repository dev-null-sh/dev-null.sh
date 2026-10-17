import { useLang } from '@erag/lang-sync-inertia/react';
import { router } from '@inertiajs/react';
import { Ban, Ellipsis, Volume2, VolumeX } from 'lucide-react';
import { useState } from 'react';
import UserBlockController from '@/actions/App/Http/Controllers/UserBlockController';
import UserMuteController from '@/actions/App/Http/Controllers/UserMuteController';
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
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';
import type { User } from '@/types';

export function UserSafetyMenu({
    user,
    isMuting,
    isBlocking,
    className,
    onOpenChange,
}: {
    user: Pick<User, 'username'>;
    isMuting: boolean;
    isBlocking: boolean;
    className?: string;
    onOpenChange?: (isOpen: boolean) => void;
}) {
    const { __ } = useLang();
    const [isMenuOpen, setIsMenuOpen] = useState(false);
    const [isConfirmingBlock, setIsConfirmingBlock] = useState(false);
    const [isSaving, setIsSaving] = useState(false);

    const changeOpen = (menu: boolean, dialog: boolean) => {
        setIsMenuOpen(menu);
        setIsConfirmingBlock(dialog);
        onOpenChange?.(menu || dialog);
    };

    const visit = (action: Parameters<typeof router.visit>[0]) => {
        router.visit(action, {
            preserveScroll: true,
            onStart: () => setIsSaving(true),
            onFinish: () => {
                setIsSaving(false);
                changeOpen(false, false);
            },
            onSuccess: () => router.flushAll(),
        });
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
                        aria-label={__('users.safety.more_options', {
                            username: user.username,
                        })}
                        data-test="user-safety-menu"
                    >
                        <Ellipsis />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="w-52">
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
                        {__(
                            isMuting
                                ? 'users.safety.unmute'
                                : 'users.safety.mute',
                            { username: user.username },
                        )}
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
                        {__(
                            isBlocking
                                ? 'users.safety.unblock'
                                : 'users.safety.block',
                            { username: user.username },
                        )}
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>

            <Dialog
                open={isConfirmingBlock}
                onOpenChange={(open) => changeOpen(false, open)}
            >
                <DialogContent>
                    <DialogTitle>
                        {__('users.safety.block_title', {
                            username: user.username,
                        })}
                    </DialogTitle>
                    <DialogDescription>
                        {__('users.safety.block_description')}
                    </DialogDescription>

                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button variant="outline">
                                {__('users.safety.cancel')}
                            </Button>
                        </DialogClose>

                        <Button
                            variant="destructive"
                            disabled={isSaving}
                            onClick={() =>
                                visit(UserBlockController.store(user))
                            }
                            data-test="confirm-block-button"
                        >
                            {__('users.safety.confirm_block')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
