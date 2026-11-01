import type { VisitOptions } from '@inertiajs/core';
import { router } from '@inertiajs/react';
import { Ban, Ellipsis, Volume2, VolumeX } from 'lucide-react';
import { useState } from 'react';
import UserBlockController from '@/actions/App/Http/Controllers/UserBlockController';
import UserMuteController from '@/actions/App/Http/Controllers/UserMuteController';
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
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useSharedState } from '@/hooks/use-shared-state';
import { cn } from '@/lib/utils';
import type { User } from '@/types';

export function useIsMuting(
    user: Pick<User, 'id'>,
    isMuting: boolean,
): [boolean, (isMuting: boolean) => void] {
    return useSharedState(`is_muting:${user.id}`, isMuting);
}

export function UserSafetyMenu({
    user,
    isMuting: initialIsMuting,
    isBlocking,
    className,
    onOpenChange,
}: {
    user: Pick<User, 'id' | 'handle'>;
    isMuting: boolean;
    isBlocking: boolean;
    className?: string;
    onOpenChange?: (isOpen: boolean) => void;
}) {
    const [isMenuOpen, setIsMenuOpen] = useState(false);
    const [isConfirmingBlock, setIsConfirmingBlock] = useState(false);
    const [isSaving, setIsSaving] = useState(false);
    const [isMuting, setIsMuting] = useIsMuting(user, initialIsMuting);

    const changeOpen = (menu: boolean, dialog: boolean) => {
        setIsMenuOpen(menu);
        setIsConfirmingBlock(dialog);
        onOpenChange?.(menu || dialog);
    };

    const visit = (
        action: Parameters<typeof router.visit>[0],
        options: Pick<
            VisitOptions,
            | 'onStart'
            | 'onSuccess'
            | 'onCancel'
            | 'onNetworkError'
            | 'onHttpException'
        >,
    ) => {
        router.visit(action, {
            preserveScroll: true,
            preserveState: true,
            ...options,
            onStart: (visit) => {
                setIsSaving(true);
                options.onStart?.(visit);
            },
            onFinish: () => {
                setIsSaving(false);
                changeOpen(false, false);
            },
            onSuccess: (page) => {
                clearMentionSuggestionsCache();

                options.onSuccess?.(page);

                router.flushAll();
            },
        });
    };

    const toggleMute = () => {
        const rollBack = () => setIsMuting(isMuting);

        visit(
            isMuting
                ? UserMuteController.destroy(user)
                : UserMuteController.store(user),
            {
                onStart: () => setIsMuting(!isMuting),
                onCancel: rollBack,
                onNetworkError: rollBack,
                onHttpException: rollBack,
            },
        );
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
                        aria-label={`More options for @${user.handle}`}
                        data-test="user-safety-menu"
                    >
                        <Ellipsis />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="w-52">
                    <DropdownMenuItem disabled={isSaving} onSelect={toggleMute}>
                        {isMuting ? <Volume2 /> : <VolumeX />}
                        {isMuting
                            ? `Unmute @${user.handle}`
                            : `Mute @${user.handle}`}
                    </DropdownMenuItem>
                    <DropdownMenuItem
                        variant={isBlocking ? 'default' : 'destructive'}
                        disabled={isSaving}
                        onSelect={() =>
                            isBlocking
                                ? visit(UserBlockController.destroy(user), {})
                                : changeOpen(false, true)
                        }
                    >
                        <Ban />
                        {isBlocking
                            ? `Unblock @${user.handle}`
                            : `Block @${user.handle}`}
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>

            <Dialog
                open={isConfirmingBlock}
                onOpenChange={(open) => changeOpen(false, open)}
            >
                <DialogContent>
                    <DialogTitle>Block @{user.handle}?</DialogTitle>
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
                                visit(UserBlockController.store(user), {
                                    onSuccess: () => rememberBlock(user),
                                })
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
