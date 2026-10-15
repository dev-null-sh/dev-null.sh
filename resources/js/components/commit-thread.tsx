import { Form, Link, router, usePage } from '@inertiajs/react';
import {
    ChevronDown,
    CornerDownRight,
    MessageSquareReply,
    Trash2,
} from 'lucide-react';
import type { KeyboardEvent } from 'react';
import { useState } from 'react';
import CommitController from '@/actions/App/Http/Controllers/CommitController';
import CommitReplyController from '@/actions/App/Http/Controllers/CommitReplyController';
import InputError from '@/components/input-error';
import { StashLabel } from '@/components/stash-label';
import { Button } from '@/components/ui/button';
import { UserAvatar } from '@/components/user-avatar';
import { UserPreview } from '@/components/user-preview';
import { useNow } from '@/hooks/use-now';
import { moveCaretToEnd, useHasStash, useStash } from '@/hooks/use-stash';
import { cn, formatRelativeTime } from '@/lib/utils';
import { show } from '@/routes/user';
import type { Commit } from '@/types';

const maxIndentDepth = 3;
const maxBodyLength = 280;

type Thread = {
    commit: Commit;
    count: number;
    replies: Commit[] | null;
    isOpen: boolean;
    isLoading: boolean;
    isReplying: boolean;
    toggle: () => void;
    startReply: () => void;
    cancelReply: () => void;
    reload: () => void;
    onReplied: () => void;
};

function useThread(commit: Commit, initialReplies?: Commit[]): Thread {
    const [replies, setReplies] = useState<Commit[] | null>(
        initialReplies ?? null,
    );
    const [isOpen, setIsOpen] = useState(initialReplies !== undefined);
    const [isLoading, setIsLoading] = useState(false);
    const [isReplying, setIsReplying] = useState(false);

    const reload = () => {
        setIsLoading(true);

        fetch(CommitReplyController.index(commit).url, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
            .then((response) => response.json())
            .then((data: { replies: Commit[] }) => setReplies(data.replies))
            .catch(() => undefined)
            .finally(() => setIsLoading(false));
    };

    return {
        commit,
        count: replies?.length ?? commit.replies_count ?? 0,
        replies,
        isOpen,
        isLoading,
        isReplying,
        toggle: () => {
            if (!isOpen && replies === null) {
                reload();
            }

            setIsOpen(!isOpen);
        },
        startReply: () => setIsReplying(true),
        cancelReply: () => setIsReplying(false),
        reload,
        onReplied: () => {
            setIsReplying(false);
            setIsOpen(true);
            reload();
        },
    };
}

export function CommitThread({
    commit,
    initialReplies,
}: {
    commit: Commit;
    initialReplies?: Commit[];
}) {
    const thread = useThread(commit, initialReplies);

    return (
        <>
            <ThreadActions thread={thread} />
            <ThreadReplies thread={thread} depth={0} />
        </>
    );
}

function ThreadActions({ thread }: { thread: Thread }) {
    const { auth } = usePage().props;
    const hasStash = useHasStash(auth.user.id, `reply-${thread.commit.id}`);

    return (
        <>
            <div className="mt-1 -ml-2 flex flex-wrap items-center gap-0.5 text-[13px]">
                <button
                    type="button"
                    onClick={
                        thread.isReplying
                            ? thread.cancelReply
                            : thread.startReply
                    }
                    aria-expanded={thread.isReplying}
                    className="flex items-center gap-1.5 rounded-md px-2 py-0.5 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                    data-test="reply-button"
                >
                    <MessageSquareReply className="size-4" />
                    Reply
                    {hasStash && !thread.isReplying && (
                        <span
                            title="You have an unsent reply here"
                            className="font-mono text-[11px] opacity-70"
                            data-test="reply-stash-hint"
                        >
                            {'stash@{0}'}
                        </span>
                    )}
                </button>

                {thread.count > 0 && (
                    <button
                        type="button"
                        onClick={thread.toggle}
                        aria-expanded={thread.isOpen}
                        className="flex items-center gap-1 rounded-md px-2 py-0.5 font-medium text-primary transition-colors hover:bg-primary-soft"
                        data-test="toggle-replies-button"
                    >
                        <ChevronDown
                            className={cn('size-4 transition-transform', {
                                '-rotate-90': !thread.isOpen,
                            })}
                        />
                        {thread.isOpen
                            ? 'Hide replies'
                            : `${thread.count} ${thread.count === 1 ? 'reply' : 'replies'}`}
                    </button>
                )}
            </div>

            {thread.isReplying && (
                <ReplyComposer
                    parent={thread.commit}
                    onCancel={thread.cancelReply}
                    onReplied={thread.onReplied}
                />
            )}
        </>
    );
}

function ThreadReplies({ thread, depth }: { thread: Thread; depth: number }) {
    if (!thread.isOpen) {
        return null;
    }

    return (
        <div
            className={cn('mt-1', {
                'ml-3 border-l border-rail pl-2 sm:ml-3.5 sm:pl-3':
                    depth < maxIndentDepth && depth > 0,
            })}
            data-test="replies"
        >
            {thread.replies === null ? (
                <p className="py-2 text-sm text-muted-foreground" role="status">
                    Loading replies…
                </p>
            ) : (
                thread.replies.map((reply) => (
                    <ReplyItem
                        key={reply.id}
                        reply={reply}
                        parent={thread.commit}
                        depth={depth + 1}
                        onDeleted={thread.reload}
                    />
                ))
            )}
        </div>
    );
}

function ReplyItem({
    reply,
    parent,
    depth,
    onDeleted,
}: {
    reply: Commit;
    parent: Commit;
    depth: number;
    onDeleted: () => void;
}) {
    const { auth } = usePage().props;
    const now = useNow();
    const thread = useThread(reply);
    const [isConfirming, setIsConfirming] = useState(false);

    const isOwn = reply.user_id === auth.user.id;
    const isFlattened = depth > maxIndentDepth;

    const deleteReply = () =>
        router.visit(CommitController.destroy(reply), {
            preserveScroll: true,
            preserveState: true,
            onSuccess: onDeleted,
        });

    return (
        <article className="group/reply pt-2.5" data-test="reply">
            <div className="flex gap-2 sm:gap-2.5">
                <UserPreview user={reply.user} className="shrink-0">
                    <Link
                        href={show(reply.user)}
                        className="block rounded-full"
                    >
                        <UserAvatar
                            user={reply.user}
                            className="size-6 sm:size-7"
                        />
                    </Link>
                </UserPreview>

                <div className="min-w-0 flex-1">
                    <div className="flex items-baseline gap-1.5 text-sm">
                        <UserPreview
                            user={reply.user}
                            className="flex min-w-0 items-baseline gap-1.5"
                        >
                            <Link
                                href={show(reply.user)}
                                className="truncate font-semibold hover:underline"
                            >
                                {reply.user.name}
                            </Link>
                            <Link
                                href={show(reply.user)}
                                tabIndex={-1}
                                className="hidden truncate font-mono text-xs text-muted-foreground sm:inline"
                            >
                                @{reply.user.username}
                            </Link>
                        </UserPreview>
                        <span aria-hidden className="text-muted-foreground">
                            ·
                        </span>
                        <Link
                            href={CommitController.show({
                                user: reply.user,
                                commit: reply,
                            })}
                            className="shrink-0 font-mono text-xs text-muted-foreground hover:underline"
                        >
                            <time
                                dateTime={reply.created_at}
                                title={new Date(
                                    reply.created_at,
                                ).toLocaleString()}
                            >
                                {formatRelativeTime(reply.created_at, now)}
                            </time>
                        </Link>
                        {reply.status !== 'approved' && (
                            <span
                                className={cn(
                                    'shrink-0 self-center rounded-full px-1.5 font-mono text-[11px]',
                                    reply.status === 'pending'
                                        ? 'bg-amber-500/10 text-amber-700 dark:text-amber-400'
                                        : 'bg-destructive/10 text-destructive',
                                )}
                            >
                                {reply.status}
                            </span>
                        )}
                        {isOwn && (
                            <button
                                type="button"
                                onClick={() => setIsConfirming(true)}
                                aria-label="Delete reply"
                                title="Delete reply"
                                className="-my-1 ml-auto shrink-0 self-center rounded-md p-1 text-muted-foreground transition hover:bg-destructive/10 hover:text-destructive focus-visible:opacity-100 sm:opacity-0 sm:group-hover/reply:opacity-100 [@media(hover:none)]:opacity-100"
                            >
                                <Trash2 className="size-3.5" />
                            </button>
                        )}
                    </div>

                    {isFlattened && (
                        <p className="flex min-w-0 items-center gap-1 font-mono text-xs text-muted-foreground">
                            <CornerDownRight className="size-3 shrink-0" />
                            <span className="truncate">
                                replying to @{parent.user.username}
                            </span>
                        </p>
                    )}

                    <p className="text-[15px] leading-relaxed [overflow-wrap:anywhere] break-words whitespace-pre-wrap">
                        {reply.body}
                    </p>

                    {isConfirming ? (
                        <div className="mt-2 flex flex-wrap items-center gap-2 rounded-md bg-destructive/8 px-3 py-1.5 text-sm">
                            <span className="mr-auto">Delete this reply?</span>
                            <Button
                                variant="ghost"
                                size="sm"
                                autoFocus
                                onClick={() => setIsConfirming(false)}
                            >
                                Cancel
                            </Button>
                            <Button
                                variant="destructive"
                                size="sm"
                                onClick={deleteReply}
                            >
                                Delete
                            </Button>
                        </div>
                    ) : (
                        <ThreadActions thread={thread} />
                    )}
                </div>
            </div>

            <ThreadReplies thread={thread} depth={depth} />
        </article>
    );
}

function ReplyComposer({
    parent,
    onCancel,
    onReplied,
}: {
    parent: Commit;
    onCancel: () => void;
    onReplied: () => void;
}) {
    const { auth } = usePage().props;
    const { body, isRestored, updateBody, drop } = useStash(
        auth.user.id,
        `reply-${parent.id}`,
    );
    const remaining = maxBodyLength - body.length;
    const isReplyable = body.trim().length >= 3;

    const onKeyDown = (event: KeyboardEvent<HTMLTextAreaElement>) => {
        if (event.key === 'Escape') {
            onCancel();
        }

        if (
            event.key === 'Enter' &&
            (event.metaKey || event.ctrlKey) &&
            isReplyable
        ) {
            event.preventDefault();
            event.currentTarget.form?.requestSubmit();
        }
    };

    return (
        <Form
            {...CommitController.store.form()}
            errorBag="reply"
            options={{ preserveScroll: true, preserveState: true }}
            onSuccess={(page) => {
                if (page.flash.committed) {
                    drop();
                    onReplied();
                }
            }}
            className="mt-2 rounded-lg border bg-background p-2.5"
            data-test="reply-composer"
        >
            {({ processing, errors }) => (
                <>
                    <input type="hidden" name="parent_id" value={parent.id} />
                    <label htmlFor={`reply-${parent.id}`} className="sr-only">
                        Reply to @{parent.user.username}
                    </label>
                    <textarea
                        id={`reply-${parent.id}`}
                        name="body"
                        rows={1}
                        autoFocus
                        value={body}
                        onChange={(event) => updateBody(event.target.value)}
                        onKeyDown={onKeyDown}
                        onFocus={isRestored ? moveCaretToEnd : undefined}
                        maxLength={maxBodyLength}
                        placeholder={`Reply to @${parent.user.username}`}
                        className="block field-sizing-content min-h-9 w-full resize-none bg-transparent px-1 text-[15px] leading-relaxed outline-none placeholder:text-muted-foreground focus-visible:outline-none"
                    />
                    <InputError
                        message={errors.body ?? errors.parent_id}
                        className="px-1"
                    />
                    <div className="mt-1.5 flex items-center justify-end gap-2">
                        {isRestored && (
                            <StashLabel
                                onDrop={() => {
                                    drop();
                                    document
                                        .getElementById(`reply-${parent.id}`)
                                        ?.focus();
                                }}
                                className="mr-auto px-1 text-xs"
                            />
                        )}
                        <span
                            className={cn(
                                'mr-1 font-mono text-xs text-muted-foreground',
                                {
                                    'text-amber-700 dark:text-amber-400':
                                        remaining <= 20,
                                },
                            )}
                        >
                            {remaining}
                        </span>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={onCancel}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            size="sm"
                            disabled={processing || !isReplyable}
                            data-test="submit-reply-button"
                        >
                            {processing ? 'Replying…' : 'Reply'}
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}
