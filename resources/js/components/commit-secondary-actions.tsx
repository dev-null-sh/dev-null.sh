import { router, usePage } from '@inertiajs/react';
import { LinkIcon, Send, Share2 } from 'lucide-react';
import { useRef, useState } from 'react';
import type { Ref } from 'react';
import CommitController from '@/actions/App/Http/Controllers/CommitController';
import ConversationController from '@/actions/App/Http/Controllers/ConversationController';
import { CommitReportButton } from '@/components/commit-report-button';
import { useCopyLink } from '@/components/copy-link-button';
import { QuotedCommit } from '@/components/quoted-commit';
import { SearchDialog } from '@/components/search-dialog';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuShortcut,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { sendMessage } from '@/lib/chat';
import { cn } from '@/lib/utils';
import type { Commit, User } from '@/types';

export function CommitSecondaryActions({
    commit,
    keyShortcut,
    isReply = false,
}: {
    commit: Commit;
    keyShortcut?: string;
    isReply?: boolean;
}) {
    const { auth } = usePage().props;
    const shareTriggerRef = useRef<HTMLButtonElement>(null);
    const [isPicking, setIsPicking] = useState(false);
    const [isPickerMounted, setIsPickerMounted] = useState(false);
    const [recipient, setRecipient] = useState<User | null>(null);
    const [isConfirming, setIsConfirming] = useState(false);
    const canSend =
        auth.user !== null &&
        auth.remotes !== null &&
        commit.status === 'approved';

    const pickRecipient = () => {
        setRecipient(null);
        setIsPickerMounted(true);
        setIsPicking(true);
    };

    const send = () => {
        if (!canSend || recipient === null || !isConfirming) {
            return;
        }

        const target = { kind: 'direct' as const, partner: recipient };
        const permalink = CommitController.show({
            user: commit.user,
            commit,
        }).url;

        sendMessage(target, {
            body: new URL(permalink, window.location.origin).href,
            replyTo: null,
            images: [],
            descriptions: [],
            linkPreviewUrl: undefined,
        });
        setIsConfirming(false);
        router.visit(ConversationController.show(recipient));
    };

    return (
        <div className="ml-auto flex items-center gap-0.5">
            <div
                className={cn(
                    'flex items-center gap-0.5 transition-opacity md:opacity-0 md:focus-within:opacity-100 [@media(hover:none)]:opacity-100',
                    isReply
                        ? 'md:reply-hover:opacity-100'
                        : 'md:commit-hover:opacity-100 md:commit-focus-within:opacity-100',
                )}
            >
                <CommitShareMenu
                    commit={commit}
                    keyShortcut={keyShortcut}
                    canSend={canSend}
                    triggerRef={shareTriggerRef}
                    onSend={pickRecipient}
                />
                <CommitReportButton commit={commit} />
            </div>
            {canSend && isPickerMounted && (
                <SearchDialog
                    open={isPicking}
                    onOpenChange={setIsPicking}
                    onPickDeveloper={setRecipient}
                    excludeId={auth.user?.id}
                    pickerTitle="Send commit in Remotes"
                    pickerPlaceholder="Find a developer"
                    pickerScope="share"
                    onCloseAutoFocus={(event) => {
                        event.preventDefault();
                        setIsPickerMounted(false);

                        if (recipient !== null) {
                            setIsConfirming(true);
                        } else {
                            shareTriggerRef.current?.focus();
                        }
                    }}
                />
            )}
            {canSend && recipient !== null && (
                <Dialog open={isConfirming} onOpenChange={setIsConfirming}>
                    <DialogContent
                        className="grid-rows-[auto_minmax(0,1fr)_auto] overflow-hidden bg-card"
                        onCloseAutoFocus={(event) => {
                            event.preventDefault();
                            shareTriggerRef.current?.focus();
                        }}
                    >
                        <DialogHeader className="text-left">
                            <DialogTitle className="min-w-0 pr-6 wrap-anywhere">
                                Send commit to @{recipient.handle}?
                            </DialogTitle>
                            <DialogDescription>
                                Send a link to this commit in Remotes.
                            </DialogDescription>
                        </DialogHeader>
                        <div className="min-h-0 overflow-y-auto">
                            <QuotedCommit
                                commit={commit}
                                isPreview
                                label="Sharing"
                            />
                        </div>
                        <DialogFooter>
                            <Button
                                variant="outline"
                                onClick={() => setIsConfirming(false)}
                            >
                                Cancel
                            </Button>
                            <Button
                                onClick={send}
                                data-test="confirm-share-commit"
                            >
                                Send
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            )}
        </div>
    );
}

function CommitShareMenu({
    commit,
    keyShortcut,
    canSend,
    triggerRef,
    onSend,
}: {
    commit: Commit;
    keyShortcut?: string;
    canSend: boolean;
    triggerRef: Ref<HTMLButtonElement>;
    onSend: () => void;
}) {
    const { copyLink } = useCopyLink(commit);
    const pendingSend = useRef(false);

    return (
        <>
            {keyShortcut && (
                <button
                    type="button"
                    tabIndex={-1}
                    aria-label="Copy link"
                    aria-keyshortcuts={keyShortcut}
                    className="hidden"
                    onClick={copyLink}
                />
            )}
            <DropdownMenu modal={false}>
                <Tooltip>
                    <TooltipTrigger asChild>
                        <DropdownMenuTrigger asChild>
                            <button
                                ref={triggerRef}
                                type="button"
                                aria-label="Share commit"
                                className="rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none data-[state=open]:bg-accent data-[state=open]:text-foreground"
                                data-test="share-commit-button"
                            >
                                <Share2 aria-hidden className="size-4" />
                            </button>
                        </DropdownMenuTrigger>
                    </TooltipTrigger>
                    <TooltipContent>Share</TooltipContent>
                </Tooltip>
                <DropdownMenuContent
                    align="end"
                    className="w-48"
                    onCloseAutoFocus={(event) => {
                        if (!pendingSend.current) {
                            return;
                        }

                        event.preventDefault();
                        pendingSend.current = false;
                        // Open the picker after the menu has finished closing.
                        onSend();
                    }}
                >
                    <DropdownMenuItem
                        onSelect={copyLink}
                        data-test="copy-commit-link"
                    >
                        <LinkIcon />
                        Copy link
                        {keyShortcut && (
                            <DropdownMenuShortcut>
                                {keyShortcut}
                            </DropdownMenuShortcut>
                        )}
                    </DropdownMenuItem>
                    {canSend && (
                        <DropdownMenuItem
                            onSelect={() => {
                                pendingSend.current = true;
                            }}
                            data-test="share-in-remotes"
                        >
                            <Send />
                            Send in Remotes
                        </DropdownMenuItem>
                    )}
                </DropdownMenuContent>
            </DropdownMenu>
        </>
    );
}
