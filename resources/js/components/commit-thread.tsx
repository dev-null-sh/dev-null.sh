import { Link, router, useRemember, useHttp, usePage } from '@inertiajs/react';
import {
    ChevronDown,
    CornerDownRight,
    ListTree,
    MessageSquareReply,
    Trash2,
} from 'lucide-react';
import type { CSSProperties, FormEvent, KeyboardEvent, ReactNode } from 'react';
import {
    useCallback,
    useEffect,
    useEffectEvent,
    useRef,
    useState,
} from 'react';
import { toast } from 'sonner';
import CommitController from '@/actions/App/Http/Controllers/CommitController';
import CommitLinkPreviewController from '@/actions/App/Http/Controllers/CommitLinkPreviewController';
import CommitReplyController from '@/actions/App/Http/Controllers/CommitReplyController';
import { ActionTooltip } from '@/components/action-tooltip';
import { BodyCounter } from '@/components/body-counter';
import { FoldedCommitBody } from '@/components/commit-body';
import {
    ImageDropOverlay,
    ImagePickerButton,
    ImagePickerPreviews,
    imageErrorFrom,
} from '@/components/commit-image-picker';
import { CommitImages } from '@/components/commit-images';
import { CommitPastelButton } from '@/components/commit-pastel-button';
import { CommitRulesDialog, useCommitRules } from '@/components/commit-rules';
import { CommitSecondaryActions } from '@/components/commit-secondary-actions';
import { CommitStarButton } from '@/components/commit-star-button';
import { CommitStatusBadge } from '@/components/commit-status-badge';
import { useCommitTranslation } from '@/components/commit-translation';
import {
    ComposerPreviewBody,
    PreviewToggle,
    useComposerPreview,
} from '@/components/composer-preview';
import {
    useGraphDimmed,
    useGraphNode,
    useGraphNodeId,
    useGraphOverlay,
} from '@/components/graph-canvas';
import { RailDot, railDotRing } from '@/components/graph-rail';
import InputError from '@/components/input-error';
import { LinkCardPicker } from '@/components/link-card-picker';
import { LinkPreview } from '@/components/link-preview';
import { MentionTextarea } from '@/components/mention-textarea';
import { StashLabel } from '@/components/stash-label';
import { showTimeout, timeoutFrom } from '@/components/timeout-dialog';
import { TimestampTooltip } from '@/components/timestamp-tooltip';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { UserAvatar } from '@/components/user-avatar';
import { UserPreview } from '@/components/user-preview';
import { useDateTime } from '@/hooks/use-date-time';
import {
    useCommitNotFound,
    useIsCommitDeleted,
} from '@/hooks/use-deleted-resources';
import { useImageAttachments } from '@/hooks/use-image-attachments';
import { focusCommit } from '@/hooks/use-keyboard-shortcuts';
import { useLinkCard } from '@/hooks/use-link-card';
import { useNow } from '@/hooks/use-now';
import { useSharedState } from '@/hooks/use-shared-state';
import { moveCaretToEnd, useHasStash, useStash } from '@/hooks/use-stash';
import { useUser } from '@/hooks/use-user';
import { signIn } from '@/lib/auth';
import {
    bodyCharacterCount,
    maxBodyLength,
    maxRawBodyLength,
    minBodyLength,
} from '@/lib/commit';
import { commitStateKey } from '@/lib/commit-state';
import { hasModKey } from '@/lib/keyboard';
import { exactCountTitle, formatCount, formatExactCount } from '@/lib/numbers';
import {
    characterCount,
    cn,
    isDeleted,
    laneOf,
    laneStrokeStyle,
} from '@/lib/utils';
import { show } from '@/routes/user';
import type {
    Commit,
    CommittedFlash,
    CursorPaginated,
    DeletedFlash,
    FlashToast,
    ThreadCommit,
} from '@/types';

const maxIndentDepth = 3;

const maxRepliesPerRequest = 50;

type RepliedParent = {
    id: string;
    replies_count: number;
    continuation_id: string | null;
};

type CreatedReply = {
    committed: CommittedFlash;
    parent: RepliedParent;
    toast?: FlashToast;
};

const replyFailedMessage = 'Could not send your reply. Try again.';

const changedThreadIds = new Set<string>();

function replyFailureMessage(response: { status: number; data: string }) {
    if (response.status !== 429) {
        return replyFailedMessage;
    }

    const { message } = JSON.parse(response.data) as { message: string };

    return message;
}

function useCommitsCountUpdate(): (change: number) => void {
    const { auth } = usePage().props;
    const [, setCommitsCount] = useSharedState(
        commitStateKey('commits_count', auth.user?.id ?? ''),
        auth.user?.commits_count ?? 0,
    );

    return (change) => setCommitsCount((count) => count + change);
}
const threadLayouts = {
    // Root replies line up with the commit text, and their elbow branches off the rail.
    root: '[--indent:0px] [--spine:-32px] sm:[--spine:-36px]',
    nested: '[--indent:32px] [--spine:12px] sm:[--indent:38px] sm:[--spine:14px]',
    flattened: '[--indent:0px]',
};

type Thread = {
    commit: ThreadCommit;
    count: number;
    replies: ThreadCommit[] | null;
    hasMore: boolean;
    continuationId: string | null;
    remaining: number;
    perPage: number;
    isOpen: boolean;
    isPreview: boolean;
    isLoading: boolean;
    isReplying: boolean;
    toggle: () => void;
    loadMore: () => void;
    loadAll: () => void;
    startReply: () => void;
    cancelReply: () => void;
    onReplied: (parent: RepliedParent) => void;
    onDeleted: () => void;
};

async function fetchReplies(
    commit: Pick<ThreadCommit, 'id'>,
    cursor: string | null,
    newest: boolean,
    limit: number,
    except?: string,
): Promise<CursorPaginated<ThreadCommit>> {
    const response = await fetch(
        CommitReplyController.index(commit, {
            query: {
                cursor,
                newest: newest ? 1 : 0,
                limit,
                ...(except ? { except } : {}),
            },
        }).url,
        {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        },
    );

    if (!response.ok) {
        throw new Error('Unable to load replies');
    }

    return (await response.json()) as CursorPaginated<ThreadCommit>;
}

async function fetchRepliesAfter(
    commit: Pick<ThreadCommit, 'id'>,
    replies: ThreadCommit[],
    cursor: string | null,
    minimum: number,
    newest: boolean,
    except?: string,
): Promise<Pick<CursorPaginated<ThreadCommit>, 'data' | 'next_cursor'>> {
    let data = replies;

    while (cursor !== null && data.length < minimum) {
        const next = await fetchReplies(
            commit,
            cursor,
            newest,
            Math.min(maxRepliesPerRequest, minimum - data.length),
            except,
        );
        data = [...data, ...next.data];
        cursor = next.next_cursor;
    }

    return { data, next_cursor: cursor };
}

async function fetchRepliesUntil(
    commit: Pick<ThreadCommit, 'id'>,
    minimum: number,
    perPage: number,
    newest: boolean,
    except?: string,
): Promise<Pick<CursorPaginated<ThreadCommit>, 'data' | 'next_cursor'>> {
    const page = await fetchReplies(commit, null, newest, perPage, except);

    return fetchRepliesAfter(
        commit,
        page.data,
        page.next_cursor,
        minimum,
        newest,
        except,
    );
}

export function useThread(
    commit: ThreadCommit,
    initialReplies?: CursorPaginated<ThreadCommit>,
    loadsReplies = false,
    exceptReplyId?: string,
): Thread {
    const preview = !isDeleted(commit) ? commit.reply_preview : undefined;
    const [continuationId, setContinuationId] = useSharedState<string | null>(
        commitStateKey('continuation_id', commit.id),
        isDeleted(commit) ? null : (commit.continuation_id ?? null),
    );
    const except =
        exceptReplyId ??
        (isDeleted(commit) ? undefined : (continuationId ?? undefined));
    const isPreview = preview !== undefined;
    const initial = preview ?? initialReplies;
    const { repliesPerPage, continuation } = usePage().props;
    const historyKey = `thread:${commit.id}:${except ?? ''}`;
    const remembered = router.restore<{
        replies: ThreadCommit[] | null;
        nextCursor: string | null;
        isOpen: boolean;
    }>(historyKey);
    const [count, setCount] = useSharedState(
        commitStateKey('replies_count', commit.id),
        commit.replies_count ?? 0,
    );
    const restoresDocument =
        remembered !== undefined &&
        (
            performance.getEntriesByType('navigation')[0] as
                | PerformanceNavigationTiming
                | undefined
        )?.type === 'back_forward';
    const [shouldLoadReplies] = useState(
        (remembered?.isOpen === true && remembered.replies === null) ||
            (remembered === undefined && loadsReplies && count > 0) ||
            ((initial !== undefined || remembered?.replies != null) &&
                (changedThreadIds.has(commit.id) || restoresDocument)),
    );
    const [initialReplyLimit] = useState(
        remembered?.replies?.length
            ? remembered.nextCursor === null
                ? Infinity
                : remembered.replies.length
            : repliesPerPage,
    );
    const [{ replies, nextCursor, isOpen }, setThread] = useRemember(
        {
            replies: initial?.data ?? null,
            nextCursor: initial?.next_cursor ?? null,
            isOpen: initial !== undefined || shouldLoadReplies,
        },
        historyKey,
    );
    const [isLoading, setIsLoading] = useState(shouldLoadReplies);
    const replyRequest = useRef(0);
    const [isReplying, setIsReplying] = useState(false);

    const [previousInitial, setPreviousInitial] = useState(initial);
    if (initial !== previousInitial) {
        setPreviousInitial(initial);
        if (initial !== undefined && (replies?.length ?? 0) <= repliesPerPage) {
            ++replyRequest.current;
            setIsLoading(false);
            setThread((current) => ({
                ...current,
                replies: initial.data,
                nextCursor: initial.next_cursor,
            }));
        }
    }

    const applyPage = (loaded: ThreadCommit[], cursor: string | null) => {
        setThread((current) => ({
            ...current,
            replies: loaded,
            nextCursor: cursor,
        }));

        if (cursor === null) {
            setCount(loaded.length);
        }
    };

    const reload = (extra = 0) => {
        const minimum =
            replies === null || (isPreview && replies.length <= repliesPerPage)
                ? repliesPerPage
                : nextCursor === null
                  ? Infinity
                  : replies.length + extra;

        const request = ++replyRequest.current;
        setIsLoading(true);

        fetchRepliesUntil(commit, minimum, repliesPerPage, isPreview, except)
            .then((page) => {
                if (request === replyRequest.current) {
                    applyPage(page.data, page.next_cursor);
                }
            })
            .catch(() => undefined)
            .finally(() => {
                if (request === replyRequest.current) {
                    setIsLoading(false);
                }
            });
    };

    const loadUntil = (minimum: number) => {
        if (isLoading || replies === null || nextCursor === null) {
            return;
        }

        const request = ++replyRequest.current;
        setIsLoading(true);

        fetchRepliesAfter(
            commit,
            replies,
            nextCursor,
            minimum,
            isPreview,
            except,
        )
            .then((page) => {
                if (request === replyRequest.current) {
                    applyPage(page.data, page.next_cursor);
                }
            })
            .catch(() => undefined)
            .finally(() => {
                if (request === replyRequest.current) {
                    setIsLoading(false);
                }
            });
    };

    const commitId = commit.id;
    const onRepliesLoaded = useEffectEvent(applyPage);

    useEffect(() => {
        if (!shouldLoadReplies) {
            return;
        }

        let isCurrent = true;
        const request = ++replyRequest.current;
        setIsLoading(true);

        fetchRepliesUntil(
            { id: commitId },
            initialReplyLimit,
            repliesPerPage,
            isPreview,
            except,
        )
            .then((page) => {
                if (isCurrent && request === replyRequest.current) {
                    onRepliesLoaded(page.data, page.next_cursor);
                }
            })
            .catch(() => undefined)
            .finally(() => {
                if (isCurrent && request === replyRequest.current) {
                    setIsLoading(false);
                }
            });

        return () => {
            isCurrent = false;
        };
    }, [
        commitId,
        shouldLoadReplies,
        isPreview,
        except,
        repliesPerPage,
        initialReplyLimit,
    ]);

    const onExceptChanged = useEffectEvent(() => {
        if (replies !== null && !shouldLoadReplies) {
            reload();
        }
    });

    const onInitialChanged = useEffectEvent(() => {
        if (
            replies !== null &&
            (initial === undefined || replies.length > repliesPerPage)
        ) {
            reload();
        }
    });
    const loadedInitial = useRef({ initial, count: commit.replies_count });
    useEffect(() => {
        if (
            loadedInitial.current.initial !== initial ||
            loadedInitial.current.count !== commit.replies_count
        ) {
            loadedInitial.current = { initial, count: commit.replies_count };
            onInitialChanged();
        }
    }, [initial, commit.replies_count]);

    const loadedExcept = useRef(except);
    useEffect(() => {
        if (loadedExcept.current !== except) {
            loadedExcept.current = except;
            onExceptChanged();
        }
    }, [except]);

    const onRestored = useEffectEvent((restored: Commit) => {
        changedThreadIds.add(commit.id);
        const target =
            restored.parent_id === commit.id
                ? restored.parent
                : restored.quoted_commit;
        if (target?.replies_count !== undefined) {
            setCount(target.replies_count);
        }
        if (replies !== null) {
            reload(replies.some((reply) => reply.id === restored.id) ? 0 : 1);
        }
    });

    useEffect(
        () =>
            router.on('flash', (event) => {
                const restored = event.detail.flash.restored;
                if (
                    restored &&
                    (restored.parent_id === commit.id ||
                        restored.quoted_commit_id === commit.id)
                ) {
                    onRestored(restored);
                }
            }),
        [commit.id],
    );

    return {
        commit,
        count,
        replies,
        hasMore: replies !== null && nextCursor !== null,
        continuationId,
        remaining: Math.max(count - (replies?.length ?? 0), 0),
        perPage: repliesPerPage,
        isOpen,
        isPreview,
        isLoading,
        isReplying,
        toggle: () => {
            if (!isOpen && replies === null) {
                reload();
            }

            setThread((current) => ({ ...current, isOpen: !current.isOpen }));
        },
        loadMore: () => loadUntil((replies?.length ?? 0) + repliesPerPage),
        loadAll: () => loadUntil(Infinity),
        startReply: () => setIsReplying(true),
        cancelReply: () => setIsReplying(false),
        onReplied: (parent) => {
            changedThreadIds.add(commit.id);
            setIsReplying(false);
            setCount(parent.replies_count);

            if (parent.continuation_id === continuationId) {
                setThread((current) => ({ ...current, isOpen: true }));
                reload();

                return;
            }

            setContinuationId(parent.continuation_id);

            if (continuation !== undefined) {
                router.reload({ only: ['commit', 'continuation', 'replies'] });
            }
        },
        onDeleted: () => {
            changedThreadIds.add(commit.id);
            setCount((current) => Math.max(current - 1, 0));
            reload();
        },
    };
}

export function ShowThreadLink({ thread }: { thread: Thread }) {
    const { commit } = thread;

    if (thread.continuationId === null || isDeleted(commit)) {
        return null;
    }

    return (
        <Link
            href={CommitController.show({ user: commit.user, commit })}
            className="mt-2 -ml-2 flex w-fit items-center gap-1 rounded-md px-2 py-0.5 text-[13px] text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
            aria-label={`Show thread by @${commit.user.username}`}
            data-test="show-thread-link"
        >
            <ListTree className="size-4" aria-hidden />
            Show thread
        </Link>
    );
}

export function ThreadActions({
    thread,
    actions,
    hasKeyboardShortcuts = false,
}: {
    thread: Thread;
    actions?: ReactNode;
    hasKeyboardShortcuts?: boolean;
}) {
    const { auth } = usePage().props;
    const { commit } = thread;
    const hasStash = useHasStash(auth.user?.id ?? null, `reply-${commit.id}`);
    const hasReplyShortcut = hasKeyboardShortcuts && !thread.isReplying;

    return (
        <>
            <div className="mt-1 -ml-2 flex flex-wrap items-center gap-0.5 text-[13px]">
                <div className="flex flex-wrap items-center gap-0.5 max-md:contents">
                    {actions}

                    {!isDeleted(commit) && (
                        <ActionTooltip
                            label="Reply"
                            shortcut={hasReplyShortcut ? 'r' : undefined}
                        >
                            <button
                                type="button"
                                onClick={
                                    auth.user === null
                                        ? signIn
                                        : thread.isReplying
                                          ? thread.cancelReply
                                          : thread.startReply
                                }
                                aria-expanded={thread.isReplying}
                                aria-keyshortcuts={
                                    hasReplyShortcut ? 'r' : undefined
                                }
                                className="flex items-center gap-1.5 rounded-md px-2 py-0.5 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                                data-test="reply-button"
                            >
                                <MessageSquareReply className="size-4" />
                                <span className="sr-only sm:not-sr-only">
                                    Reply
                                </span>
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
                        </ActionTooltip>
                    )}

                    {thread.count > 0 && !thread.isPreview && (
                        <ActionTooltip
                            label={
                                thread.isOpen
                                    ? 'Hide replies'
                                    : exactCountTitle(thread.count) ===
                                        undefined
                                      ? 'Show replies'
                                      : `Show ${exactCountTitle(thread.count)} replies`
                            }
                            shortcut={hasKeyboardShortcuts ? 'o' : undefined}
                        >
                            <button
                                type="button"
                                onClick={thread.toggle}
                                aria-expanded={thread.isOpen}
                                aria-keyshortcuts={
                                    hasKeyboardShortcuts ? 'o' : undefined
                                }
                                className="flex items-center gap-1 rounded-md px-2 py-0.5 font-medium text-primary transition-colors hover:bg-primary-soft"
                                data-test="toggle-replies-button"
                            >
                                <ChevronDown
                                    className={cn(
                                        'size-4 transition-transform',
                                        {
                                            '-rotate-90': !thread.isOpen,
                                        },
                                    )}
                                />
                                {thread.isOpen
                                    ? 'Hide replies'
                                    : `${formatCount(thread.count)} ${thread.count === 1 ? 'reply' : 'replies'}`}
                            </button>
                        </ActionTooltip>
                    )}
                </div>

                {!isDeleted(commit) && (
                    <CommitSecondaryActions
                        commit={commit}
                        keyShortcut={hasKeyboardShortcuts ? 'y' : undefined}
                    />
                )}
            </div>

            {thread.isReplying && !isDeleted(commit) && (
                <ReplyComposer
                    parent={commit}
                    onCancel={thread.cancelReply}
                    onReplied={thread.onReplied}
                />
            )}
        </>
    );
}

export function ThreadSpine({
    thread,
    className,
}: {
    thread: Thread;
    className?: string;
}) {
    const isOverlaid = useGraphOverlay();

    if (isOverlaid || !thread.isOpen || !thread.replies?.length) {
        return null;
    }

    return (
        <span
            aria-hidden
            className={cn(
                'absolute bottom-0 left-1/2 w-px -translate-x-1/2',
                className,
            )}
            style={laneStrokeStyle(laneOf(thread.commit))}
            data-test="thread-spine"
        />
    );
}

const replyLink =
    'absolute left-[calc(var(--indent)+12px)] w-px sm:left-[calc(var(--indent)+14px)]';

function ReplyLinks({
    previousLane,
    lane,
    hasNext,
}: {
    previousLane: string | null;
    lane: string;
    hasNext: boolean;
}) {
    return (
        <span aria-hidden>
            {previousLane === null ? (
                <ThreadElbow />
            ) : (
                <span
                    className={cn(replyLink, 'top-0 h-1.5')}
                    style={laneStrokeStyle(previousLane)}
                    data-test="reply-link"
                />
            )}
            {hasNext && (
                <span
                    className={cn(
                        replyLink,
                        'top-[38px] bottom-0 sm:top-[42px]',
                    )}
                    style={laneStrokeStyle(lane)}
                    data-test="reply-tail"
                />
            )}
        </span>
    );
}

function ThreadElbow() {
    return (
        <svg
            aria-hidden
            className="pointer-events-none absolute top-0 left-(--spine) h-[22px] w-[calc(var(--indent)-var(--spine)-4px)] overflow-visible sm:h-6"
            viewBox="0 0 32 24"
            preserveAspectRatio="none"
            fill="none"
            data-test="thread-elbow"
        >
            {/* Quarter-circle corner matching rounded-bl-xl. */}
            <path
                d="M 0 0 L 0 12 Q 0 23.5 12 23.5 L 32 23.5"
                stroke="var(--lane)"
                strokeWidth="1"
                strokeDasharray="2 2"
                vectorEffect="non-scaling-stroke"
            />
        </svg>
    );
}

export function ThreadReplies({
    thread,
    depth,
    graphParent,
}: {
    thread: Thread;
    depth: number;
    /** In a graph canvas, the node the first reply branches off. */
    graphParent?: string;
}) {
    const isOverlaid = useGraphOverlay();
    const nodeId = useGraphNodeId();

    if (!thread.isOpen) {
        return null;
    }

    const branchFrom = graphParent ?? nodeId(thread.commit.id);

    const isFlattened = depth >= maxIndentDepth;
    const layout =
        depth === 0
            ? threadLayouts.root
            : isFlattened
              ? threadLayouts.flattened
              : threadLayouts.nested;

    return (
        <div
            className={cn('relative', layout)}
            style={{ '--lane': laneOf(thread.commit) } as CSSProperties}
            data-test="replies"
        >
            {thread.replies === null ? (
                <ReplySkeleton />
            ) : (
                <>
                    {thread.replies.map((reply, index, replies) => (
                        <ReplyItem
                            key={reply.id}
                            reply={reply}
                            parent={thread.commit}
                            depth={depth + 1}
                            graph={
                                isFlattened
                                    ? {}
                                    : {
                                          after:
                                              index === 0
                                                  ? undefined
                                                  : nodeId(
                                                        replies[index - 1].id,
                                                    ),
                                          parent:
                                              index === 0
                                                  ? branchFrom
                                                  : undefined,
                                      }
                            }
                            links={
                                isFlattened || isOverlaid ? null : (
                                    <ReplyLinks
                                        previousLane={
                                            index === 0
                                                ? null
                                                : laneOf(replies[index - 1])
                                        }
                                        lane={laneOf(reply)}
                                        hasNext={
                                            index < replies.length - 1 ||
                                            thread.hasMore
                                        }
                                    />
                                )
                            }
                            onDeleted={thread.onDeleted}
                        />
                    ))}
                    {thread.hasMore && (
                        <div className="relative pt-1.5 pl-(--indent)">
                            {!isFlattened &&
                                !isOverlaid &&
                                thread.replies.length > 0 && (
                                    <span
                                        aria-hidden
                                        className={cn(
                                            replyLink,
                                            'top-0 h-5 sm:h-5.5',
                                        )}
                                        style={laneStrokeStyle(
                                            laneOf(
                                                thread.replies[
                                                    thread.replies.length - 1
                                                ],
                                            ),
                                        )}
                                    />
                                )}
                            <MoreRepliesButton
                                thread={thread}
                                isFlattened={depth + 1 > maxIndentDepth}
                                graph={
                                    isFlattened
                                        ? {}
                                        : thread.replies.length > 0
                                          ? {
                                                after: nodeId(
                                                    thread.replies[
                                                        thread.replies.length -
                                                            1
                                                    ].id,
                                                ),
                                            }
                                          : { parent: branchFrom }
                                }
                            />
                        </div>
                    )}
                </>
            )}
        </div>
    );
}

function ReplySkeleton() {
    return (
        <div
            role="status"
            aria-label="Loading replies"
            className="flex gap-2 pt-2.5 pl-(--indent) motion-safe:animate-pulse sm:gap-2.5"
        >
            <span className="size-6 shrink-0 rounded-full bg-muted sm:size-7" />
            <span className="flex-1">
                <span className="my-1 block h-3 w-1/3 rounded bg-muted" />
                <span className="my-1.5 block h-3 w-3/4 rounded bg-muted" />
                <span className="mt-2.5 block h-3 w-24 rounded bg-muted" />
            </span>
        </div>
    );
}

function moreRepliesLabel(thread: Thread): string {
    if (thread.remaining > thread.perPage) {
        return `Show ${thread.perPage} more`;
    }

    if (thread.remaining === 1) {
        return 'Show the last reply';
    }

    return thread.remaining > 1
        ? `Show the last ${formatCount(thread.remaining)}`
        : 'Show more';
}

function MoreRepliesButton({
    thread,
    isFlattened,
    graph,
}: {
    thread: Thread;
    isFlattened: boolean;
    graph: ReplyGraph;
}) {
    const dotRef = useGraphNode({
        id: useGraphNodeId()(`${thread.commit.id}:more`),
        lane: 'var(--muted-foreground)',
        dashed: true,
        dimmed: useGraphDimmed(),
        ring: railDotRing,
        ...graph,
    });

    return (
        <div className="flex max-w-full items-center gap-0.5 text-[13px] text-muted-foreground">
            <button
                type="button"
                onClick={thread.loadMore}
                aria-disabled={thread.isLoading}
                className="-ml-2 flex min-w-0 items-center gap-2 rounded-md px-2 py-0.5 transition-colors [overflow-anchor:none] hover:bg-accent hover:text-foreground aria-disabled:pointer-events-none sm:gap-2.5"
                data-test="more-replies-button"
            >
                <span
                    aria-hidden
                    className="relative flex size-6 shrink-0 items-center justify-center [--lane:var(--muted-foreground)] sm:size-7"
                >
                    <span ref={dotRef} className="flex">
                        <RailDot />
                    </span>
                </span>
                {thread.isLoading ? (
                    <span role="status">Loading replies…</span>
                ) : (
                    <span className="truncate tabular-nums">
                        {moreRepliesLabel(thread)}
                        {isFlattened && !isDeleted(thread.commit) && (
                            <>
                                {' to '}
                                <span className="font-mono text-xs">
                                    @{thread.commit.user.username}
                                </span>
                            </>
                        )}
                    </span>
                )}
            </button>
            {thread.remaining > thread.perPage && (
                <>
                    <span aria-hidden className="opacity-50">
                        ·
                    </span>
                    <button
                        type="button"
                        onClick={thread.loadAll}
                        aria-disabled={thread.isLoading}
                        className="shrink-0 rounded-md px-2 py-0.5 tabular-nums transition-colors [overflow-anchor:none] hover:bg-accent hover:text-foreground aria-disabled:pointer-events-none"
                        data-test="all-replies-button"
                        title={exactCountTitle(thread.remaining)}
                    >
                        Show all {formatCount(thread.remaining)}
                    </button>
                </>
            )}
        </div>
    );
}

type ReplyGraph = { after?: string; parent?: string };

/** A reply avatar's ring is a 2px ring, 2px away from the picture. */
const replyRing = 4;

function ReplyItem({
    reply,
    parent,
    depth,
    links,
    graph,
    onDeleted,
}: {
    reply: ThreadCommit;
    parent: ThreadCommit;
    depth: number;
    links: ReactNode;
    graph: ReplyGraph;
    onDeleted: () => void;
}) {
    const thread = useThread(reply);
    const nodeRef = useGraphNode({
        id: useGraphNodeId()(reply.id),
        lane: laneOf(reply),
        dashed: true,
        dimmed: useGraphDimmed(),
        ring: isDeleted(reply) ? 0 : replyRing,
        ...graph,
    });

    if (isDeleted(reply)) {
        return (
            <article
                className="relative cursor-default pt-2.5 pl-(--indent)"
                data-reply
                data-test="reply-tombstone"
            >
                {links}
                <div className="flex gap-2 sm:gap-2.5">
                    <div className="relative shrink-0">
                        <span
                            ref={nodeRef}
                            aria-hidden
                            className="block size-6 rounded-full border-2 border-dashed border-rail sm:size-7"
                        />
                        {depth < maxIndentDepth && (
                            <ThreadSpine
                                thread={thread}
                                className="top-7 sm:top-8"
                            />
                        )}
                    </div>
                    <div className="min-w-0 flex-1">
                        <p className="flex min-h-6 items-center gap-1.5 text-sm text-muted-foreground sm:min-h-7">
                            <span className="font-mono text-xs">[deleted]</span>
                            This commit was deleted.
                        </p>
                        <ThreadActions thread={thread} />
                    </div>
                </div>

                <ThreadReplies thread={thread} depth={depth} />
            </article>
        );
    }

    return (
        <CommitReply
            reply={reply}
            parent={parent}
            depth={depth}
            links={links}
            thread={thread}
            nodeRef={nodeRef}
            onDeleted={onDeleted}
        />
    );
}

function CommitReply({
    reply,
    parent,
    depth,
    links,
    thread,
    nodeRef,
    onDeleted,
}: {
    reply: Commit;
    parent: ThreadCommit;
    depth: number;
    links: ReactNode;
    thread: Thread;
    nodeRef: (element: HTMLElement | null) => void;
    onDeleted: () => void;
}) {
    const { auth } = usePage().props;
    const { formatRelativeTime } = useDateTime();
    const now = useNow();
    const { bodyRef, ...translation } = useCommitTranslation(reply);
    const wasDeleted = useIsCommitDeleted(reply.id);
    const commitNotFound = useCommitNotFound();
    const [isConfirming, setIsConfirming] = useState(false);
    const request = useHttp<Record<string, never>, { deleted: DeletedFlash }>(
        {},
    );
    const updateCommitsCount = useCommitsCountUpdate();

    const avatarRef = useRef<HTMLAnchorElement>(null);
    // A stable ref, so the node stays registered across renders, like hover dimming its lane.
    const avatarNodeRef = useCallback(
        (element: HTMLAnchorElement | null) => {
            avatarRef.current = element;
            nodeRef(element);
        },
        [nodeRef],
    );

    const isOwn = reply.user_id === auth.user?.id;
    const isFlattened = depth > maxIndentDepth;
    const isQuote = reply.quoted_commit_id === parent.id;
    const permalink = CommitController.show({
        user: reply.user,
        commit: reply,
    });

    const deleteReply = async () => {
        let isNotFound = false;

        try {
            const response = await request.submit(
                CommitReplyController.destroy({ commit: parent, reply }),
                {
                    onHttpException: (response) => {
                        isNotFound = commitNotFound(reply.id, response);
                    },
                },
            );

            router.flushAll();
            updateCommitsCount(-1);
            onDeleted();
            router.flash(() => response);
        } catch {
            if (isNotFound) {
                setIsConfirming(false);
            } else {
                toast.error('Could not delete your reply. Try again.');
            }

            return;
        }
    };

    return (
        <article
            className={cn(
                'relative pt-2.5 pl-(--indent)',
                wasDeleted ? 'cursor-default' : 'cursor-pointer',
            )}
            data-reply
            data-permalink={wasDeleted ? undefined : permalink.url}
            data-test="reply"
        >
            <div
                className={cn(
                    'relative isolate flex gap-2 before:absolute before:-inset-x-2 before:-top-1.5 before:-bottom-1.5 before:-z-10 before:rounded-lg before:transition-colors sm:gap-2.5',
                    { 'reply-hover:before:bg-accent': !wasDeleted },
                )}
            >
                <div className="relative shrink-0">
                    <UserPreview user={reply.user} anchor={avatarRef}>
                        <Link
                            ref={avatarNodeRef}
                            href={show(reply.user)}
                            className="block rounded-full"
                        >
                            <UserAvatar
                                user={reply.user}
                                className="size-6 ring-2 ring-(--lane) ring-offset-2 ring-offset-card sm:size-7"
                                style={
                                    {
                                        '--lane': laneOf(reply),
                                    } as CSSProperties
                                }
                            />
                        </Link>
                    </UserPreview>
                    {depth < maxIndentDepth && (
                        <ThreadSpine
                            thread={thread}
                            className="top-7 sm:top-8"
                        />
                    )}
                </div>

                <div
                    inert={wasDeleted}
                    className={cn('min-w-0 flex-1', {
                        'opacity-50': wasDeleted,
                    })}
                >
                    <div className="flex items-baseline gap-1.5 text-sm">
                        <UserPreview
                            user={reply.user}
                            anchor={avatarRef}
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
                        {isQuote && (
                            <Link
                                href={permalink}
                                className="shrink-0 font-mono text-xs text-primary hover:underline"
                                data-test="quote-reply-label"
                            >
                                quoted
                            </Link>
                        )}
                        <span aria-hidden className="text-muted-foreground">
                            ·
                        </span>
                        <TimestampTooltip
                            date={reply.created_at}
                            author={reply.user}
                        >
                            <Link
                                href={permalink}
                                className="shrink-0 font-mono text-xs text-muted-foreground hover:underline"
                            >
                                <time dateTime={reply.created_at}>
                                    {formatRelativeTime(reply.created_at, now)}
                                </time>
                            </Link>
                        </TimestampTooltip>
                        <CommitStatusBadge commit={reply} size="small" />
                        {isOwn && (
                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <button
                                        type="button"
                                        onClick={() => setIsConfirming(true)}
                                        aria-label="Delete reply"
                                        className="-my-1 ml-auto shrink-0 self-center rounded-md p-1 text-muted-foreground transition hover:bg-destructive/10 hover:text-destructive focus-visible:opacity-100 sm:opacity-0 sm:reply-hover:opacity-100 [@media(hover:none)]:opacity-100"
                                    >
                                        <Trash2 className="size-3.5" />
                                    </button>
                                </TooltipTrigger>
                                <TooltipContent>Delete reply</TooltipContent>
                            </Tooltip>
                        )}
                    </div>

                    {isFlattened && (
                        <p className="flex min-w-0 items-center gap-1 font-mono text-xs text-muted-foreground">
                            <CornerDownRight className="size-3 shrink-0" />
                            <span className="truncate">
                                {isQuote ? 'quoting' : 'replying to'}{' '}
                                {isDeleted(parent)
                                    ? '[deleted]'
                                    : `@${parent.user.username}`}
                            </span>
                        </p>
                    )}

                    <div ref={bodyRef} style={translation.bodyStyle}>
                        <FoldedCommitBody
                            segments={translation.segments}
                            mentions={reply.mentions}
                            previewSegments={translation.previewSegments}
                            className="text-[15px]"
                        />
                    </div>
                    {translation.notice}
                    <CommitImages commit={reply} />
                    {reply.link_preview && (
                        <LinkPreview
                            refreshUrl={CommitLinkPreviewController.show.url(
                                reply.id,
                            )}
                            preview={reply.link_preview}
                        />
                    )}
                    <ShowThreadLink thread={thread} />

                    {isConfirming ? (
                        <div className="mt-2 flex flex-wrap items-center gap-2 rounded-md bg-destructive/8 px-3 py-1.5 text-sm">
                            <span className="mr-auto">
                                Delete this reply? You can undo for 30 seconds.
                            </span>
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
                                disabled={request.processing}
                                onClick={deleteReply}
                            >
                                Delete
                            </Button>
                        </div>
                    ) : (
                        <ThreadActions
                            thread={thread}
                            actions={
                                <>
                                    <CommitStarButton
                                        commit={reply}
                                        className="px-2 py-0.5"
                                    />
                                    <CommitPastelButton
                                        commit={reply}
                                        className="px-2 py-0.5"
                                    />
                                    {translation.button}
                                </>
                            }
                        />
                    )}
                </div>
            </div>
            {links}

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
    onReplied: (parent: RepliedParent) => void;
}) {
    const { user } = useUser();
    const commitNotFound = useCommitNotFound();
    const {
        body,
        images,
        descriptions,
        isRestored,
        key: stashKey,
        updateBody,
        updateImages,
        drop,
    } = useStash(user.id, `reply-${parent.id}`);
    const attachments = useImageAttachments(
        images,
        descriptions,
        updateImages,
        stashKey,
    );
    const linkCard = useLinkCard(body);
    const updateCommitsCount = useCommitsCountUpdate();
    const request = useHttp<
        {
            body: string;
            images: File[];
            image_descriptions: string[];
            link_preview_url?: string;
            commit_rules_accepted?: boolean;
        },
        CreatedReply
    >({
        body: '',
        images: [],
        image_descriptions: [],
    });
    const textareaRef = useRef<HTMLTextAreaElement>(null);
    const commitRules = useCommitRules();
    const isReplyable =
        (images.length > 0 || characterCount(body.trim()) >= minBodyLength) &&
        bodyCharacterCount(body) <= maxBodyLength;
    const preview = useComposerPreview({
        body,
        textareaRef,
        isSubmittable: isReplyable,
    });

    const returnFocusToCommit = () => {
        const commit =
            textareaRef.current?.closest<HTMLElement>('[data-commit]');

        if (
            commit &&
            textareaRef.current?.form?.contains(document.activeElement)
        ) {
            focusCommit(commit);
        }
    };

    const onKeyDown = (event: KeyboardEvent<HTMLTextAreaElement>) => {
        if (preview.handleShortcut(event)) {
            return;
        }

        if (event.key === 'Escape') {
            returnFocusToCommit();
            onCancel();
        }

        if (
            event.key === 'Enter' &&
            hasModKey(event) &&
            isReplyable &&
            !attachments.isReading()
        ) {
            event.preventDefault();
            event.currentTarget.form?.requestSubmit();
        }
    };

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (
            request.processing ||
            attachments.isReading() ||
            !commitRules.beforeSubmit()
        ) {
            return;
        }

        request.setData(() => ({
            body,
            images,
            image_descriptions: descriptions,
            commit_rules_accepted: commitRules.hasAccepted(),
            ...(linkCard.isChosen
                ? { link_preview_url: linkCard.link ?? '' }
                : {}),
        }));

        request
            .submit(CommitReplyController.store(parent), {
                onSuccess: (reply) => {
                    attachments.clear();
                    drop();
                    linkCard.reset();
                    returnFocusToCommit();
                    router.flash(() => reply);
                    router.flushAll();
                    updateCommitsCount(1);
                    onReplied(reply.parent);
                },
                onHttpException: (response) => {
                    const timeout = timeoutFrom(response.status, response.data);

                    if (timeout) {
                        showTimeout(timeout);
                    } else if (!commitNotFound(parent.id, response)) {
                        toast.error(replyFailureMessage(response));
                    }
                },
                onNetworkError: () => {
                    toast.error(replyFailedMessage);
                },
            })
            .catch(() => undefined);
    };

    const { processing, errors } = request;

    return (
        <form
            onSubmit={submit}
            {...attachments.dropzone}
            className="relative mt-2 rounded-lg border bg-background p-2.5 transition-[color,box-shadow] focus-within:border-ring focus-within:ring-[3px] focus-within:ring-ring/50"
            data-test="reply-composer"
        >
            {attachments.isDraggingFiles && (
                <ImageDropOverlay className="-inset-px" />
            )}
            <label htmlFor={`reply-${parent.id}`} className="sr-only">
                Reply to @{parent.user.username}
            </label>
            <div className="grid">
                <MentionTextarea
                    ref={textareaRef}
                    reply={parent.id}
                    id={`reply-${parent.id}`}
                    name="body"
                    rows={1}
                    autoFocus
                    value={body}
                    onValueChange={updateBody}
                    onKeyDown={onKeyDown}
                    onPaste={attachments.onPaste}
                    onFocus={isRestored ? moveCaretToEnd : undefined}
                    maxLength={maxRawBodyLength}
                    placeholder={`Reply to @${parent.user.username}`}
                    className={cn(
                        'col-start-1 row-start-1 block field-sizing-content max-h-[50dvh] min-h-9 w-full resize-none overflow-x-hidden overflow-y-auto bg-transparent px-1 text-base leading-relaxed outline-none placeholder:text-muted-foreground focus-visible:outline-none md:text-[15px]',
                        { invisible: preview.isPreviewing },
                    )}
                />
                {preview.isPreviewing && (
                    <ComposerPreviewBody
                        preview={preview}
                        url={linkCard.link}
                        images={attachments.images}
                        descriptions={attachments.descriptions}
                        className="col-start-1 row-start-1 px-1"
                    />
                )}
            </div>
            <InputError
                message={errors.body ?? errors.link_preview_url}
                className="px-1"
            />
            {!preview.isPreviewing && (
                <ImagePickerPreviews
                    images={attachments.images}
                    descriptions={attachments.descriptions}
                    onDescribe={attachments.describe}
                    onRemove={attachments.remove}
                />
            )}
            <CommitRulesDialog rules={commitRules} textarea={textareaRef} />
            <InputError
                message={attachments.error ?? imageErrorFrom(errors)}
                className="mt-1 px-1"
            />
            <div className="mt-1.5 flex flex-wrap items-center justify-end gap-x-2 gap-y-1">
                <div className="mr-auto flex min-w-0 items-center gap-2">
                    <ImagePickerButton
                        count={images.length}
                        disabled={processing}
                        reading={attachments.reading}
                        onPick={attachments.pick}
                        className="size-8"
                    />
                    <PreviewToggle preview={preview} className="-ml-2 size-8" />
                    <LinkCardPicker
                        linkCard={linkCard}
                        disabled={processing}
                        className="-ml-1"
                        labelClassName="max-sm:sr-only"
                    />
                    {isRestored && (
                        <StashLabel
                            onDrop={() => {
                                attachments.clear();
                                drop();
                                document
                                    .getElementById(`reply-${parent.id}`)
                                    ?.focus();
                            }}
                            className="px-1 text-xs"
                        />
                    )}
                </div>
                <BodyCounter body={body} className="mr-1 text-xs" />
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
                    disabled={processing || attachments.reading || !isReplyable}
                    className="min-w-19"
                    data-test="submit-reply-button"
                >
                    {processing ? 'Replying…' : 'Reply'}
                </Button>
            </div>
        </form>
    );
}
