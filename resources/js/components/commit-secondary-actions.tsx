import { router, usePage } from '@inertiajs/react';
import {
    Ban,
    EllipsisVertical,
    Flag,
    LinkIcon,
    Send,
    Trash2,
    UserCheck,
    UserPlus,
    Volume2,
    VolumeX,
} from 'lucide-react';
import { useRef, useState } from 'react';
import type { Ref } from 'react';
import CommitController from '@/actions/App/Http/Controllers/CommitController';
import ConversationController from '@/actions/App/Http/Controllers/ConversationController';
import UserBlockController from '@/actions/App/Http/Controllers/UserBlockController';
import UserFollowController from '@/actions/App/Http/Controllers/UserFollowController';
import UserMuteController from '@/actions/App/Http/Controllers/UserMuteController';
import {
    CommitReportDialog,
    useCanReport,
} from '@/components/commit-report-button';
import { useCopyLink } from '@/components/copy-link-button';
import {
    followVisitOptions,
    onFollowHttpException,
    useIsFollowing,
} from '@/components/follow-button';
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
    DropdownMenuSeparator,
    DropdownMenuShortcut,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import {
    forgetPreview,
    usePreviewRelationship,
} from '@/components/user-preview';
import { BlockUserDialog, visitSafety } from '@/components/user-safety-menu';
import { signIn } from '@/lib/auth';
import { sendMessage } from '@/lib/chat';
import { cn } from '@/lib/utils';
import type { Commit, CursorPaginated, User } from '@/types';

type PendingAction = 'send' | 'block' | 'report' | 'delete';

export function CommitSecondaryActions({
    commit,
    keyShortcut,
    isReply = false,
    onDelete,
}: {
    commit: Commit;
    keyShortcut?: string;
    isReply?: boolean;
    /** Set when the viewer can delete the commit: it gets the menu's trigger, to return focus to on cancel. */
    onDelete?: (trigger: HTMLButtonElement | null) => void;
}) {
    const { auth } = usePage().props;
    const shareTriggerRef = useRef<HTMLButtonElement>(null);
    const canReport = useCanReport(commit);
    const [isReporting, setIsReporting] = useState(false);
    const [isConfirmingBlock, setIsConfirmingBlock] = useState(false);
    const { author } = commit;
    const canBlock =
        auth.user !== null &&
        author.type === 'user' &&
        author.id !== auth.user.id;
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
            profile: commit.author,
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
                <CommitMoreMenu
                    commit={commit}
                    keyShortcut={keyShortcut}
                    canSend={canSend}
                    canReport={canReport}
                    canDelete={onDelete !== undefined}
                    triggerRef={shareTriggerRef}
                    onPending={(action) => {
                        if (action === 'send') {
                            pickRecipient();
                        } else if (action === 'block') {
                            setIsConfirmingBlock(true);
                        } else if (action === 'report') {
                            setIsReporting(true);
                        } else {
                            onDelete?.(shareTriggerRef.current);
                        }
                    }}
                />
            </div>
            {canBlock && (
                <BlockUserDialog
                    user={author}
                    open={isConfirmingBlock}
                    onOpenChange={setIsConfirmingBlock}
                />
            )}
            {canReport && (
                <CommitReportDialog
                    commit={commit}
                    open={isReporting}
                    onOpenChange={setIsReporting}
                />
            )}
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

function CommitMoreMenu({
    commit,
    keyShortcut,
    canSend,
    canReport,
    canDelete,
    triggerRef,
    onPending,
}: {
    commit: Commit;
    keyShortcut?: string;
    canSend: boolean;
    canReport: boolean;
    canDelete: boolean;
    triggerRef: Ref<HTMLButtonElement>;
    onPending: (action: PendingAction) => void;
}) {
    const { auth } = usePage().props;
    const { copyLink } = useCopyLink(commit);
    const pending = useRef<PendingAction | null>(null);
    const { author } = commit;

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
                                aria-label="More actions"
                                className="rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none data-[state=open]:bg-accent data-[state=open]:text-foreground"
                                data-test="commit-menu-button"
                            >
                                <EllipsisVertical
                                    aria-hidden
                                    className="size-4"
                                />
                            </button>
                        </DropdownMenuTrigger>
                    </TooltipTrigger>
                    <TooltipContent>More</TooltipContent>
                </Tooltip>
                <DropdownMenuContent
                    align="end"
                    className="w-56"
                    onCloseAutoFocus={(event) => {
                        const action = pending.current;

                        if (action === null) {
                            return;
                        }

                        event.preventDefault();
                        pending.current = null;
                        // Open what comes next after the menu has finished closing.
                        onPending(action);
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
                                pending.current = 'send';
                            }}
                            data-test="share-in-remotes"
                        >
                            <Send />
                            Send in Remotes
                        </DropdownMenuItem>
                    )}
                    {author.type === 'user' && author.id !== auth.user?.id && (
                        <>
                            <DropdownMenuSeparator />
                            <CommitAuthorItems
                                author={author}
                                onBlock={() => {
                                    pending.current = 'block';
                                }}
                            />
                        </>
                    )}
                    {(canReport || canDelete) && <DropdownMenuSeparator />}
                    {canReport && (
                        <DropdownMenuItem
                            onSelect={() => {
                                pending.current = 'report';
                            }}
                            data-test="report-commit"
                        >
                            <Flag />
                            Report commit
                        </DropdownMenuItem>
                    )}
                    {canDelete && (
                        <DropdownMenuItem
                            variant="destructive"
                            onSelect={() => {
                                pending.current = 'delete';
                            }}
                            data-test="delete-commit-button"
                        >
                            <Trash2 />
                            Delete commit
                        </DropdownMenuItem>
                    )}
                </DropdownMenuContent>
            </DropdownMenu>
        </>
    );
}

/**
 * Commits don't say whether you follow, mute or block their author, so the items ask the author's preview once the
 * menu opens, and wait for it before offering any of them.
 */
function CommitAuthorItems({
    author,
    onBlock,
}: {
    author: User;
    onBlock: () => void;
}) {
    const {
        component,
        props: { auth, log, commits },
    } = usePage<{ commits?: CursorPaginated<Commit> }>();
    const isSignedIn = auth.user !== null;
    const relationship = usePreviewRelationship(author, isSignedIn);
    const isLoading = isSignedIn && relationship === null;
    const [isFollowing, setIsFollowing] = useIsFollowing(
        author,
        relationship?.isFollowing ?? false,
    );

    const toggleFollow = () => {
        if (!isSignedIn) {
            signIn();

            return;
        }

        router.visit(
            isFollowing
                ? UserFollowController.destroy(author)
                : UserFollowController.store(author),
            {
                preserveScroll: true,
                preserveState: true,
                async: true,
                ...followVisitOptions(component, log, commits),
                onSuccess: () => {
                    setIsFollowing(!isFollowing);
                    router.flushAll();
                },
                onHttpException: (response) =>
                    onFollowHttpException(author, response),
            },
        );
    };

    const visit = (action: Parameters<typeof router.visit>[0]) =>
        visitSafety(action, { onSuccess: () => forgetPreview(author) });

    return (
        <>
            {!relationship?.isBlocking && (
                <DropdownMenuItem
                    disabled={isLoading}
                    onSelect={toggleFollow}
                    data-test="follow-commit-author"
                >
                    {isFollowing ? <UserCheck /> : <UserPlus />}
                    <span
                        className="min-w-0 truncate"
                        title={`@${author.handle}`}
                    >
                        {isFollowing ? 'Unfollow' : 'Follow'} @{author.handle}
                    </span>
                </DropdownMenuItem>
            )}
            {isSignedIn && (
                <>
                    <DropdownMenuItem
                        disabled={isLoading}
                        onSelect={() =>
                            visit(
                                relationship?.isMuting
                                    ? UserMuteController.destroy(author)
                                    : UserMuteController.store(author),
                            )
                        }
                        data-test="mute-commit-author"
                    >
                        {relationship?.isMuting ? <Volume2 /> : <VolumeX />}
                        <span
                            className="min-w-0 truncate"
                            title={`@${author.handle}`}
                        >
                            {relationship?.isMuting ? 'Unmute' : 'Mute'} @
                            {author.handle}
                        </span>
                    </DropdownMenuItem>
                    <DropdownMenuItem
                        variant={
                            relationship?.isBlocking ? 'default' : 'destructive'
                        }
                        disabled={isLoading}
                        onSelect={() =>
                            relationship?.isBlocking
                                ? visit(UserBlockController.destroy(author))
                                : onBlock()
                        }
                        data-test="block-commit-author"
                    >
                        <Ban />
                        <span
                            className="min-w-0 truncate"
                            title={`@${author.handle}`}
                        >
                            {relationship?.isBlocking ? 'Unblock' : 'Block'} @
                            {author.handle}
                        </span>
                    </DropdownMenuItem>
                </>
            )}
        </>
    );
}
