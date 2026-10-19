import { ArrowDown, X } from 'lucide-react';
import type {
    ClipboardEvent,
    FormEvent,
    KeyboardEvent,
    ReactNode,
} from 'react';
import {
    memo,
    useCallback,
    useEffect,
    useId,
    useLayoutEffect,
    useMemo,
    useRef,
    useState,
} from 'react';
import { toast } from 'sonner';
import {
    MessageImages,
    MessageLinkCard,
    MessageText,
    BubbleActions,
    Repliable,
    ReplyGhost,
} from '@/components/chat/message-parts';
import type { Author } from '@/components/chat/message-parts';
import {
    ReactionChips,
    ReactionSheet,
    ReactionTray,
    useTouchReactions,
} from '@/components/chat/message-reactions';
import { MessageStatusGlyph } from '@/components/chat/message-status';
import { EventLine, typingText } from '@/components/chat/org';
import {
    ImageDropOverlay,
    ImagePickerButton,
    ImagePickerPreviews,
} from '@/components/commit-image-picker';
import { LinkCardPicker } from '@/components/link-card-picker';
import { MentionTextarea } from '@/components/mention-textarea';
import { SecretWarning } from '@/components/secret-warning';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';
import { UserAvatar } from '@/components/user-avatar';
import { useChat, useThread } from '@/hooks/use-chat';
import { useImageAttachments } from '@/hooks/use-image-attachments';
import { useLinkCard } from '@/hooks/use-link-card';
import { useSecretWarning } from '@/hooks/use-secret-warning';
import { moveCaretToEnd, useStash } from '@/hooks/use-stash';
import {
    settleRequest,
    loadOlderMessages,
    markThreadRead,
    pingTyping,
    retryMessage,
    sendMessage,
    setReplyingTo,
    targetKey,
    toggleReaction,
} from '@/lib/chat';
import type { ChatTarget, PendingMessage } from '@/lib/chat';
import { pullsOf, statusMessageOf } from '@/lib/pulls';
import { doubleTapReaction } from '@/lib/reactions';
import type { ReactionUsage } from '@/lib/reactions';
import { restoreScrollAnchor, scrollAnchorOf } from '@/lib/scroll-anchor';
import type { ScrollAnchor } from '@/lib/scroll-anchor';
import {
    characterCount,
    cn,
    formatDayLabel,
    laneColor,
    localDayKey,
} from '@/lib/utils';
import type {
    Message,
    MessageReply,
    OrgMember,
    PushAccess,
    User,
} from '@/types';

const maxLength = 2000;

/**
 * The thread scrolls from its bottom, so its scroll position is 0 at the latest message and negative above it.
 */
const isAtLatest = (scroller: HTMLElement): boolean => scroller.scrollTop > -48;

const noMessages: Message[] = [];

const noPending: PendingMessage[] = [];

const noUsage: ReactionUsage[] = [];

const countFrom = maxLength * 0.8;

const warnFrom = maxLength * 0.95;

type Bubble =
    | { kind: 'message'; message: Message }
    | { kind: 'pending'; pending: PendingMessage };

type Item =
    | { kind: 'day'; key: string; label: string }
    | { kind: 'head'; key: string; count: number }
    | { kind: 'event'; key: string; message: Message }
    | {
          kind: 'group';
          key: string;
          isMine: boolean;
          authorId: string;
          author: User | null;
          bubbles: Bubble[];
      };

/**
 * Who has pulled up to where: the partner in a conversation, or each member in an org.
 */
type Pulls = {
    readThrough: number;
    readAt: string | null;
    members: OrgMember[];
};

export function ConversationThread({
    target,
    user,
    header,
    isVisible = true,
    isCompact = false,
    autoFocus = false,
    onDropped,
}: {
    target: ChatTarget;
    user: User;
    header: ReactNode;
    isVisible?: boolean;
    isCompact?: boolean;
    autoFocus?: boolean;
    /**
     * Called after dropping the partner's request, which takes it out of the inbox.
     */
    onDropped?: () => void;
}) {
    const thread = useThread(target);
    const usage = useChat().reactionUsage ?? noUsage;
    const isOrg = target.kind === 'org';
    // Renaming an org updates the thread's target, not the one this was opened with.
    const current = thread?.target ?? target;
    const scroller = useRef<HTMLDivElement>(null);
    const list = useRef<HTMLOListElement>(null);
    const headLine = useRef<HTMLDivElement>(null);
    const isPinned = useRef(true);
    const hasScrolledIn = useRef(false);
    const anchor = useRef<ScrollAnchor | null>(null);
    const seenLatest = useRef(0);
    const [unseen, setUnseen] = useState(0);

    const messages = thread?.messages ?? noMessages;
    const pending = thread?.pending ?? noPending;
    const latestId = messages.at(-1)?.id ?? 0;
    const head = thread?.head ?? 0;
    const headUntil = thread?.headUntil ?? 0;
    const items = useMemo(
        () => itemsOf(messages, pending, head, headUntil, user, isOrg),
        [messages, pending, head, headUntil, user, isOrg],
    );
    const statusMessage = useMemo(
        () => statusMessageOf(messages, pending, user, isOrg),
        [messages, pending, user, isOrg],
    );
    const pulls = useMemo<Pulls>(
        () => ({
            readThrough: thread?.partner_read_through_id ?? 0,
            readAt: thread?.partner_read_at ?? null,
            members: (thread?.members ?? []).filter(
                (member) => member.user.id !== user.id,
            ),
        }),
        [
            thread?.partner_read_through_id,
            thread?.partner_read_at,
            thread?.members,
            user.id,
        ],
    );
    const contentKey = `${latestId}:${pending.length}:${pending.map((item) => item.status).join()}`;
    const isOpen = (thread?.access ?? 'open') === 'open';
    const draft = useMessageDraft(user, current);
    const replyingTo = thread?.replyingTo ?? null;
    const members = thread?.members;

    const authorOf = useCallback(
        (userId: string): Author => {
            if (userId === user.id) {
                return { name: 'You', lane: user.lane, isMe: true };
            }

            const person =
                current.kind === 'direct'
                    ? current.partner
                    : (members?.find((member) => member.user.id === userId)
                          ?.user ??
                      messages.find((message) => message.user_id === userId)
                          ?.user);

            return {
                name: person?.name ?? 'Someone',
                lane: person?.lane ?? 0,
                isMe: false,
            };
        },
        [user, current, members, messages],
    );

    const jumpTo = useCallback((id: number) => {
        const bubble = scroller.current?.querySelector<HTMLElement>(
            `[data-message-id="${id}"]`,
        );

        if (!bubble) {
            return;
        }

        bubble.scrollIntoView({ block: 'center', behavior: 'smooth' });
        bubble.dataset.flash = '';
        window.setTimeout(() => delete bubble.dataset.flash, 1200);
    }, []);

    const replyTo = useCallback(
        (reply: MessageReply) => {
            setReplyingTo(target, reply);
            draft.input.current?.focus();
        },
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [targetKey(target)],
    );

    const react = useCallback(
        (messageId: number, emoji: string) => {
            void toggleReaction(target, messageId, emoji, user.id).then(
                (isSaved) => {
                    if (!isSaved) {
                        toast.error('Could not react. Try again.');
                    }
                },
            );
        },
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [targetKey(target), user.id],
    );

    useEffect(() => {
        if (isVisible && thread?.isLoaded && isPinned.current) {
            markThreadRead(target);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [isVisible, latestId, thread?.isLoaded, targetKey(target)]);

    useLayoutEffect(() => {
        const element = scroller.current;

        if (element === null || !thread?.isLoaded) {
            return;
        }

        if (!hasScrolledIn.current) {
            hasScrolledIn.current = true;
            seenLatest.current = latestId;
            headLine.current?.scrollIntoView({ block: 'center' });
            isPinned.current = isAtLatest(element);
            anchor.current = scrollAnchorOf(element);

            return;
        }

        if (isPinned.current) {
            element.scrollTop = 0;
            seenLatest.current = latestId;
        } else {
            if (anchor.current !== null) {
                restoreScrollAnchor(element, anchor.current);
            }

            setUnseen(
                messages.filter(
                    (message) =>
                        message.id > seenLatest.current &&
                        message.user_id !== user.id &&
                        !message.event,
                ).length,
            );
        }
        // Scrolling follows new messages only, not every poll that changes nothing. Older messages need nothing: the
        // scroller is anchored at its bottom, so what loads above the visible rows never moves them.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [contentKey, thread?.isLoaded]);

    // A row below the visible ones can still change height, as reactions or a link card arrive, and the scroller itself
    // shrinks as the composer grows or the keyboard opens. Anchored at its bottom, either would move what is being read,
    // so the top visible row is kept where it was.
    useEffect(() => {
        const element = scroller.current;
        const content = list.current;

        if (element === null || content === null) {
            return;
        }

        const observer = new ResizeObserver(() => {
            if (!isPinned.current && anchor.current !== null) {
                restoreScrollAnchor(element, anchor.current);
            }
        });

        observer.observe(content);
        observer.observe(element);

        return () => observer.disconnect();
    }, [thread?.isLoaded]);

    const onScroll = () => {
        const element = scroller.current;

        if (element === null) {
            return;
        }

        isPinned.current = isAtLatest(element);
        anchor.current = scrollAnchorOf(element);

        if (isPinned.current) {
            seenLatest.current = latestId;
            setUnseen(0);

            if (isVisible) {
                markThreadRead(target);
            }
        }

        // Older messages load a screen ahead, so scrolling up rarely reaches the top before they arrive.
        if (
            element.scrollHeight - element.clientHeight + element.scrollTop <
                element.clientHeight &&
            thread?.has_older &&
            !thread.isLoadingOlder
        ) {
            void loadOlderMessages(target);
        }
    };

    const jumpToLatest = () => {
        const element = scroller.current;

        element?.scrollTo({ top: 0, behavior: 'smooth' });
    };

    return (
        <section
            aria-label={
                current.kind === 'direct'
                    ? `Conversation with ${current.partner.name}`
                    : `Org ${current.org.name ?? ''}`.trim()
            }
            className="relative flex min-h-0 min-w-0 flex-1 flex-col"
            {...(isOpen ? draft.attachments.dropzone : {})}
        >
            {header}

            <div
                ref={scroller}
                onScroll={onScroll}
                className="flex min-h-0 flex-1 scrollbar-thin flex-col-reverse overflow-y-auto overscroll-contain [overflow-anchor:none]"
                data-test="conversation-messages"
            >
                {thread === null || !thread.isLoaded ? (
                    <ThreadSkeleton isCompact={isCompact} />
                ) : (
                    <ol
                        ref={list}
                        aria-live="polite"
                        aria-relevant="additions"
                        className={cn(
                            'flex flex-col pb-1',
                            isCompact
                                ? 'px-3 pt-3 text-sm'
                                : 'px-4 pt-4 text-[15px]',
                        )}
                    >
                        {thread.isLoadingOlder && (
                            <li className="flex justify-center py-2">
                                <Spinner className="size-4 text-muted-foreground" />
                            </li>
                        )}
                        {thread.messages.length === 0 &&
                            thread.pending.length === 0 &&
                            thread.access === 'open' && (
                                <li className="py-10 text-center font-mono text-[13px] text-muted-foreground">
                                    empty history.{' '}
                                    {target.kind === 'direct'
                                        ? `say hi to @${target.partner.username}`
                                        : 'say hi to the org'}
                                </li>
                            )}
                        {items.map((item) => (
                            <ThreadItem
                                key={item.key}
                                item={item}
                                pulls={pulls}
                                statusMessage={statusMessage}
                                user={user}
                                target={target}
                                headLine={headLine}
                                isCompact={isCompact}
                                authorOf={authorOf}
                                onJump={jumpTo}
                                onReply={isOpen ? replyTo : null}
                                usage={usage}
                                onReact={isOpen ? react : null}
                            />
                        ))}
                    </ol>
                )}
            </div>

            {unseen > 0 && (
                <button
                    type="button"
                    onClick={jumpToLatest}
                    className="absolute bottom-[calc(var(--composer-height,4rem)+2.5rem)] left-1/2 flex -translate-x-1/2 items-center gap-1.5 rounded-full bg-primary px-3 py-1 font-mono text-xs font-medium text-primary-foreground tabular-nums shadow-lg"
                >
                    <ArrowDown aria-hidden className="size-3.5" />
                    {unseen} new
                </button>
            )}

            {isOpen && draft.attachments.isDraggingFiles && (
                <ImageDropOverlay className="inset-2" />
            )}

            {isOpen ? (
                <Composer
                    target={current}
                    draft={draft}
                    replyingTo={replyingTo}
                    replyAuthor={
                        replyingTo === null
                            ? null
                            : authorOf(replyingTo.user_id)
                    }
                    onSend={() => {
                        isPinned.current = true;
                    }}
                    isCompact={isCompact}
                    autoFocus={autoFocus}
                    typing={
                        target.kind === 'org'
                            ? (thread?.typing ?? []).length > 0
                                ? typingText(thread?.typing ?? [])
                                : null
                            : thread?.partner_typing
                              ? `@${target.partner.username} is typing…`
                              : null
                    }
                />
            ) : (
                target.kind === 'direct' && (
                    <AccessBar
                        access={thread?.access ?? 'open'}
                        partner={target.partner}
                        isCompact={isCompact}
                        onDropped={onDropped}
                    />
                )
            )}
        </section>
    );
}

function itemsOf(
    messages: Message[],
    pendingMessages: PendingMessage[],
    head: number,
    headUntil: number,
    user: User,
    isOrg: boolean,
): Item[] {
    const bubbles: {
        bubble: Bubble;
        at: string;
        isMine: boolean;
        author: string;
        id: number;
    }[] = [
        ...messages.map((message) => ({
            bubble: { kind: 'message', message } as Bubble,
            at: message.created_at,
            isMine: message.user_id === user.id,
            author: message.user_id,
            id: message.id,
        })),
        ...pendingMessages.map((pending) => ({
            bubble: { kind: 'pending', pending } as Bubble,
            at: pending.created_at,
            isMine: true,
            author: user.id,
            id: Number.POSITIVE_INFINITY,
        })),
    ];
    const unread = messages.filter(
        (message) =>
            message.id > head &&
            message.id <= headUntil &&
            message.user_id !== user.id &&
            !message.event,
    );
    const firstUnread = messages.findIndex(
        (message) => message.id === unread[0]?.id,
    );
    const items: Item[] = [];
    let day: string | null = null;

    bubbles.forEach(({ bubble, at, isMine, author, id }, index) => {
        const dayKey = localDayKey(at);
        const last = items.at(-1);

        if (dayKey !== day) {
            day = dayKey;
            items.push({
                kind: 'day',
                key: `day-${dayKey}`,
                label: formatDayLabel(at),
            });
        }

        if (firstUnread > 0 && index === firstUnread) {
            items.push({ kind: 'head', key: 'head', count: unread.length });
        }

        if (bubble.kind === 'message' && bubble.message.event) {
            items.push({
                kind: 'event',
                key: `event-${id}`,
                message: bubble.message,
            });

            return;
        }

        const current = items.at(-1);

        if (
            current?.kind === 'group' &&
            current.isMine === isMine &&
            current.authorId === author &&
            current === last
        ) {
            current.bubbles.push(bubble);

            return;
        }

        items.push({
            kind: 'group',
            key: `group-${bubble.kind === 'message' ? id : bubble.pending.client_id}`,
            isMine,
            authorId: author,
            author:
                isOrg && !isMine && bubble.kind === 'message'
                    ? (bubble.message.user ?? null)
                    : null,
            bubbles: [bubble],
        });
    });

    return items;
}

/**
 * Only re-renders when its own messages, or the read position, change.
 */
const ThreadItem = memo(function ThreadItem({
    item,
    pulls,
    statusMessage,
    user,
    target,
    headLine,
    isCompact,
    authorOf,
    onJump,
    onReply,
    usage,
    onReact,
}: {
    item: Item;
    pulls: Pulls;
    statusMessage: string | null;
    user: User;
    target: ChatTarget;
    headLine: React.RefObject<HTMLDivElement | null>;
    isCompact: boolean;
    authorOf: (userId: string) => Author;
    onJump: (id: number) => void;
    onReply: ((reply: MessageReply) => void) | null;
    usage: ReactionUsage[];
    onReact: ((messageId: number, emoji: string) => void) | null;
}) {
    if (item.kind === 'day') {
        return (
            <li className="my-3 flex justify-center first:mt-0">
                <span className="inline-flex items-center gap-1.5 rounded bg-muted py-0.5 pr-2 pl-1.5 font-mono text-[11px] text-muted-foreground">
                    <span
                        aria-hidden
                        className="size-1.5 rounded-full bg-current opacity-60"
                    />
                    {item.label}
                </span>
            </li>
        );
    }

    if (item.kind === 'head') {
        return (
            <li>
                <div
                    ref={headLine}
                    className="my-3 flex items-center gap-2 font-mono text-[11px] font-medium text-primary after:h-px after:flex-1 after:bg-primary/50"
                >
                    HEAD · {item.count} new
                </div>
            </li>
        );
    }

    if (item.kind === 'event') {
        return <EventLine message={item.message} user={user} />;
    }

    return (
        <li
            className={cn('flex flex-col gap-0.5 [&+&]:mt-2.5', {
                'items-end': item.isMine,
                'items-start': !item.isMine,
            })}
        >
            {item.author !== null && (
                <span
                    className="flex max-w-full min-w-0 items-center gap-1.5 px-1.5 pb-0.5 text-xs text-muted-foreground"
                    title={item.author.name}
                >
                    <UserAvatar
                        user={item.author}
                        className="size-4 ring-1 ring-(--lane)"
                        style={
                            {
                                '--lane': laneColor(item.author.lane),
                            } as React.CSSProperties
                        }
                    />
                    <span className="truncate font-medium text-foreground">
                        {item.author.name}
                    </span>
                </span>
            )}
            {item.bubbles.map((bubble, index) => {
                const key =
                    bubble.kind === 'message'
                        ? bubble.message.client_id
                        : bubble.pending.client_id;
                const isLast = index === item.bubbles.length - 1;

                return (
                    <BubbleWithMeta
                        key={key}
                        bubble={bubble}
                        isMine={item.isMine}
                        isLast={isLast}
                        showsStatus={key === statusMessage}
                        pulls={pulls}
                        user={user}
                        target={target}
                        isCompact={isCompact}
                        authorOf={authorOf}
                        onJump={onJump}
                        onReply={onReply}
                        usage={usage}
                        onReact={onReact}
                    />
                );
            })}
        </li>
    );
});

function BubbleWithMeta({
    bubble,
    isMine,
    isLast,
    showsStatus,
    pulls,
    user,
    target,
    isCompact,
    authorOf,
    onJump,
    onReply,
    usage,
    onReact,
}: {
    bubble: Bubble;
    isMine: boolean;
    isLast: boolean;
    showsStatus: boolean;
    pulls: Pulls;
    user: User;
    target: ChatTarget;
    isCompact: boolean;
    authorOf: (userId: string) => Author;
    onJump: (id: number) => void;
    onReply: ((reply: MessageReply) => void) | null;
    usage: ReactionUsage[];
    onReact: ((messageId: number, emoji: string) => void) | null;
}) {
    const [isReacting, setIsReacting] = useState(false);
    const [isTrayOpen, setIsTrayOpen] = useState(false);
    const trayId = useId();
    const reactButton = useRef<HTMLButtonElement>(null);
    const message =
        bubble.kind === 'message'
            ? bubble.message
            : { body: bubble.pending.body, mentions: null };
    const reply =
        bubble.kind === 'message'
            ? (bubble.message.reply_to ?? null)
            : bubble.pending.reply_to;
    const images =
        bubble.kind === 'message'
            ? (bubble.message.images ?? [])
            : bubble.pending.images;
    const { pulledBy, total } =
        bubble.kind === 'message' && target.kind === 'org'
            ? pullsOf(pulls.members, bubble.message)
            : { pulledBy: [], total: 0 };
    const status =
        bubble.kind === 'pending'
            ? bubble.pending.status
            : (
                    target.kind === 'org'
                        ? pulledBy.length > 0
                        : pulls.readThrough >= bubble.message.id
                )
              ? 'pulled'
              : 'pushed';
    const partnerLane =
        target.kind === 'direct'
            ? target.partner.lane
            : (pulledBy[0]?.lane ?? user.lane);

    const isOnPrimary = isMine && status !== 'staged' && status !== 'rejected';
    const side = isMine ? 'mine' : 'theirs';
    const replyToThis =
        onReply !== null && bubble.kind === 'message'
            ? () =>
                  onReply({
                      id: bubble.message.id,
                      user_id: bubble.message.user_id,
                      body: bubble.message.body,
                      images_count: images.length,
                  })
            : null;

    const reactions =
        bubble.kind === 'message' ? bubble.message.reactions : undefined;
    const reacted = useMemo(
        () =>
            new Set(
                (reactions ?? [])
                    .filter((reaction) => reaction.user_id === user.id)
                    .map((reaction) => reaction.emoji),
            ),
        [reactions, user.id],
    );
    const reactToThis =
        onReact !== null && bubble.kind === 'message'
            ? (emoji: string) => onReact(bubble.message.id, emoji)
            : null;
    const toggleTray =
        reactToThis === null
            ? null
            : () => setIsTrayOpen((isTrayOpen) => !isTrayOpen);
    const touch = useTouchReactions(
        reactToThis === null
            ? null
            : {
                  onLongPress: () => setIsReacting(true),
                  onDoubleTap: () => reactToThis(doubleTapReaction),
              },
    );

    return (
        <>
            <Repliable isMine={isMine} onReply={replyToThis}>
                <div
                    data-scroll-anchor={
                        bubble.kind === 'message'
                            ? bubble.message.client_id
                            : bubble.pending.client_id
                    }
                    className={cn(
                        'relative flex max-w-[85%] min-w-0 flex-col gap-1',
                        isCompact
                            ? 'max-w-[88%] pointer-fine:max-w-[calc(100%-3.5rem)]'
                            : 'sm:max-w-[78%]',
                        isMine ? 'items-end' : 'items-start',
                        {
                            '[-webkit-touch-callout:none] pointer-coarse:select-none':
                                reactToThis !== null,
                        },
                    )}
                    {...touch}
                >
                    {reply !== null && (
                        <ReplyGhost
                            reply={reply}
                            author={authorOf(reply.user_id)}
                            side={side}
                            onJump={onJump}
                        />
                    )}
                    {images.length > 0 && (
                        <div className="relative max-w-full">
                            <MessageImages images={images} />
                            {message.body === '' && (
                                <BubbleActions
                                    isMine={isMine}
                                    onReply={replyToThis}
                                    onReact={toggleTray}
                                    isReacting={isTrayOpen}
                                    reactRef={reactButton}
                                    trayId={trayId}
                                />
                            )}
                        </div>
                    )}
                    {message.body !== '' && (
                        <div
                            data-message-id={
                                bubble.kind === 'message'
                                    ? bubble.message.id
                                    : undefined
                            }
                            className={cn(
                                'relative w-fit max-w-full min-w-0 rounded-lg px-3.5 py-2 wrap-anywhere ring-primary ring-offset-2 ring-offset-background transition-[background-color,color,outline-color,box-shadow] duration-200 data-flash:ring-2',
                                isCompact && 'px-3 py-1.5',
                                isMine
                                    ? 'bg-primary text-primary-foreground'
                                    : 'bg-muted text-foreground',
                                {
                                    'bg-transparent text-foreground outline-[1.5px] outline-offset-[-1.5px] outline-primary outline-dashed':
                                        status === 'staged',
                                    'bg-transparent text-foreground outline-[1.5px] outline-offset-[-1.5px] outline-destructive outline-solid':
                                        status === 'rejected',
                                },
                            )}
                        >
                            <MessageText
                                message={message}
                                isMine={isOnPrimary}
                            />
                            <BubbleActions
                                isMine={isMine}
                                onReply={replyToThis}
                                onReact={toggleTray}
                                isReacting={isTrayOpen}
                                reactRef={reactButton}
                                trayId={trayId}
                            />
                        </div>
                    )}
                    {bubble.kind === 'message' && (
                        <MessageLinkCard message={bubble.message} />
                    )}
                    {reactToThis !== null && (
                        <ReactionTray
                            id={trayId}
                            isOpen={isTrayOpen}
                            onOpenChange={setIsTrayOpen}
                            trigger={reactButton}
                            usage={usage}
                            reacted={reacted}
                            isMine={isMine}
                            onReact={reactToThis}
                        />
                    )}
                    <ReactionChips
                        reactions={reactions}
                        userId={user.id}
                        isMine={isMine}
                        nameOf={(userId) => authorOf(userId).name}
                        onToggle={reactToThis}
                    />
                </div>
            </Repliable>

            {reactToThis !== null && (
                <ReactionSheet
                    isOpen={isReacting}
                    onOpenChange={setIsReacting}
                    usage={usage}
                    reacted={reacted}
                    isMine={isMine}
                    body={message.body}
                    preview={
                        message.body === '' ? null : (
                            <div
                                className={cn(
                                    'w-fit max-w-full rounded-lg px-3.5 py-2 text-[15px] wrap-anywhere shadow-[0_12px_30px_-12px_rgb(0_0_0/0.45)]',
                                    isMine
                                        ? 'bg-primary text-primary-foreground'
                                        : 'bg-muted text-foreground',
                                )}
                            >
                                <MessageText
                                    message={message}
                                    isMine={isOnPrimary}
                                />
                            </div>
                        )
                    }
                    onReact={reactToThis}
                />
            )}

            {status === 'rejected' ? (
                <MetaLine className="text-destructive">
                    <MessageStatusGlyph
                        status="rejected"
                        lane={user.lane}
                        partnerLane={partnerLane}
                    />
                    rejected ·
                    <button
                        type="button"
                        onClick={() =>
                            bubble.kind === 'pending' &&
                            retryMessage(target, bubble.pending.client_id)
                        }
                        className="underline underline-offset-2"
                    >
                        retry
                    </button>
                </MetaLine>
            ) : isMine && showsStatus ? (
                <MetaLine>
                    <MessageStatusGlyph
                        status={status}
                        lane={user.lane}
                        partnerLane={partnerLane}
                    />
                    <span>
                        {status}
                        {status === 'pulled' &&
                            target.kind === 'direct' &&
                            pulls.readAt &&
                            ` ${timeOf(pulls.readAt)}`}
                        {pulledBy.length > 0 && ' by'}
                    </span>
                    {pulledBy.length > 0 && (
                        <PulledBy users={pulledBy} total={total} />
                    )}
                </MetaLine>
            ) : (
                isLast &&
                bubble.kind === 'message' && (
                    <MetaLine>
                        <time dateTime={bubble.message.created_at}>
                            {timeOf(bubble.message.created_at)}
                        </time>
                        {showsStatus && pulledBy.length > 0 && (
                            <>
                                <span>· pulled by</span>
                                <PulledBy users={pulledBy} total={total} />
                            </>
                        )}
                    </MetaLine>
                )
            )}
        </>
    );
}

/**
 * The members who pulled a message, as small avatars, or "everyone" once the whole org has.
 */
function PulledBy({ users, total }: { users: User[]; total: number }) {
    const names = users.map((person) => `@${person.username}`).join(', ');

    if (users.length === total && total > 3) {
        return (
            <span title={names} data-test="pulled-by">
                everyone
            </span>
        );
    }

    return (
        <span
            className="flex items-center"
            title={names}
            aria-label={names}
            data-test="pulled-by"
        >
            {users.slice(0, 5).map((person) => (
                <UserAvatar
                    key={person.id}
                    user={person}
                    pixels={32}
                    className="size-3.5 ring-1 ring-background not-first:-ml-1"
                />
            ))}
            {users.length > 5 && (
                <span className="ml-1">+{users.length - 5}</span>
            )}
        </span>
    );
}

function MetaLine({
    className,
    children,
}: {
    className?: string;
    children: ReactNode;
}) {
    return (
        <div
            className={cn(
                'flex h-[18px] items-center gap-1.5 px-1.5 font-mono text-[11px] whitespace-nowrap text-muted-foreground tabular-nums',
                className,
            )}
        >
            {children}
        </div>
    );
}

function timeOf(date: string): string {
    return new Date(date).toLocaleTimeString([], {
        hour: '2-digit',
        minute: '2-digit',
    });
}

/**
 * The draft being written to a conversation: its body and images, kept per conversation across pages and the dock,
 * the link chosen for its card, and any secrets found in it.
 */
function useMessageDraft(user: User, target: ChatTarget) {
    const stash = useStash(user.id, `remote:${targetKey(target)}`);
    const attachments = useImageAttachments(
        stash.images,
        stash.descriptions,
        stash.updateImages,
        stash.key,
    );
    const linkCard = useLinkCard(stash.body);
    const secrets = useSecretWarning(stash.body);
    const input = useRef<HTMLTextAreaElement>(null);

    return { stash, attachments, linkCard, secrets, input };
}

type MessageDraftState = ReturnType<typeof useMessageDraft>;

/**
 * Counts characters once the message nears the limit, and refuses a paste that would cross it rather than
 * quietly cutting it short. Pasted or picked images go along with the message, and Escape lets go of a reply.
 */
function Composer({
    target,
    draft,
    replyingTo,
    replyAuthor,
    isCompact,
    autoFocus,
    typing,
    onSend,
}: {
    target: ChatTarget;
    draft: MessageDraftState;
    replyingTo: MessageReply | null;
    replyAuthor: Author | null;
    isCompact: boolean;
    autoFocus: boolean;
    typing: string | null;
    /**
     * Called as the user pushes, so the thread follows their own message down.
     */
    onSend: () => void;
}) {
    const id = `message-${targetKey(target).replace(':', '-')}`;
    const placeholder =
        target.kind === 'direct'
            ? `Message @${target.partner.username}`
            : `Message ${target.org.name ?? 'the org'}`;
    const lane = target.kind === 'direct' ? target.partner.lane : 0;
    const { stash, attachments, linkCard, secrets, input } = draft;
    const body = stash.body;
    const [error, setError] = useState<string | null>(null);
    const errorTimer = useRef<number | undefined>(undefined);
    const trimmed = body.trim();
    const length = characterCount(body);
    const isCounting = length >= countFrom;
    const hasImages = attachments.images.length > 0;
    const shownError = error ?? attachments.error;

    useEffect(() => () => window.clearTimeout(errorTimer.current), []);

    const showError = (message: string | null) => {
        window.clearTimeout(errorTimer.current);
        setError(message);

        if (message !== null) {
            errorTimer.current = window.setTimeout(() => setError(null), 6000);
        }
    };

    const submit = (event?: FormEvent) => {
        event?.preventDefault();

        if (
            attachments.isReading() ||
            (trimmed === '' && !hasImages) ||
            !secrets.beforeSubmit()
        ) {
            return;
        }

        onSend();
        sendMessage(target, {
            body: trimmed,
            replyTo: replyingTo,
            images: attachments.images,
            descriptions: attachments.descriptions,
            linkPreviewUrl: linkCard.isChosen ? linkCard.link : undefined,
        });
        stash.drop();
        linkCard.reset();
        setReplyingTo(target, null);
        showError(null);
        input.current?.focus();
    };

    const onKeyDown = (event: KeyboardEvent<HTMLTextAreaElement>) => {
        if (event.defaultPrevented) {
            return;
        }

        if (event.key === 'Escape' && replyingTo !== null) {
            event.preventDefault();
            setReplyingTo(target, null);

            return;
        }

        // On touch screens the keyboard's return key adds a line, and Push sends.
        if (
            event.key === 'Enter' &&
            !event.shiftKey &&
            !event.nativeEvent.isComposing &&
            !window.matchMedia('(pointer: coarse)').matches
        ) {
            submit(event);
        }
    };

    const onPaste = (event: ClipboardEvent<HTMLTextAreaElement>) => {
        attachments.onPaste(event);

        if (event.defaultPrevented) {
            return;
        }

        const pasted = event.clipboardData.getData('text');
        const field = event.currentTarget;
        const kept =
            characterCount(field.value.slice(0, field.selectionStart)) +
            characterCount(field.value.slice(field.selectionEnd));
        const total = kept + characterCount(pasted);

        if (total > maxLength) {
            event.preventDefault();
            showError(
                `fatal: paste rejected, message would be ${total.toLocaleString()}/${maxLength.toLocaleString()} chars`,
            );
        }
    };

    return (
        <>
            <div
                aria-live="polite"
                className={cn(
                    'flex h-6 shrink-0 items-center gap-2 font-mono text-xs text-muted-foreground',
                    isCompact ? 'px-3.5' : 'px-5',
                )}
                style={{ '--lane': laneColor(lane) } as never}
            >
                <span className="flex min-w-0 flex-1 items-center gap-2">
                    {shownError !== null ? (
                        <span
                            role="alert"
                            className="truncate text-destructive"
                            title={shownError}
                            data-test="message-error"
                        >
                            {shownError}
                        </span>
                    ) : (
                        typing !== null && (
                            <>
                                <span
                                    aria-hidden
                                    className="size-2.5 shrink-0 rounded-full border-2 border-dashed border-(--lane) motion-safe:animate-[spin_3s_linear_infinite]"
                                />
                                <span
                                    className="truncate font-sans"
                                    data-test="typing"
                                >
                                    {typing}
                                </span>
                            </>
                        )
                    )}
                </span>
                <LinkCardPicker
                    linkCard={linkCard}
                    className="-mr-1.5 h-6 max-w-40 shrink px-1.5 text-xs [&_svg]:size-3.5"
                />
                {isCounting && (
                    <span
                        aria-label={`${maxLength - length} characters left`}
                        className={cn('shrink-0 tabular-nums', {
                            'text-amber-700 dark:text-amber-400':
                                length >= warnFrom,
                            'text-destructive': length >= maxLength,
                        })}
                        data-test="message-counter"
                    >
                        {length.toLocaleString()}/{maxLength.toLocaleString()}
                    </span>
                )}
            </div>
            <form
                onSubmit={submit}
                className={cn(
                    'flex shrink-0 flex-col gap-2 border-t',
                    isCompact ? 'p-2.5' : 'px-4 py-3',
                )}
            >
                <SecretWarning
                    body={body}
                    findings={secrets.findings}
                    textarea={input}
                    onChange={stash.updateBody}
                    confirming={secrets.confirming}
                    onConfirmingChange={secrets.setConfirming}
                    onApprove={secrets.approve}
                />
                {replyingTo !== null && replyAuthor !== null && (
                    <div className="flex min-w-0 items-center gap-1.5">
                        <ReplyGhost
                            reply={replyingTo}
                            author={replyAuthor}
                            side="theirs"
                            isDraft
                        />
                        <button
                            type="button"
                            onClick={() => {
                                setReplyingTo(target, null);
                                input.current?.focus();
                            }}
                            aria-label="Cancel reply"
                            title="Cancel reply (Esc)"
                            className="grid size-7 shrink-0 place-items-center rounded-full text-muted-foreground hover:bg-accent hover:text-foreground"
                            data-test="cancel-reply"
                        >
                            <X aria-hidden className="size-4" />
                        </button>
                    </div>
                )}
                {hasImages && (
                    <div className="[&>div]:mt-0">
                        <ImagePickerPreviews
                            images={attachments.images}
                            descriptions={attachments.descriptions}
                            onDescribe={attachments.describe}
                            onRemove={attachments.remove}
                        />
                    </div>
                )}
                <div className="flex items-end gap-2">
                    <label htmlFor={id} className="sr-only">
                        {placeholder}
                    </label>
                    <ImagePickerButton
                        count={attachments.images.length}
                        reading={attachments.reading}
                        onPick={attachments.pick}
                        className={cn(
                            '-mx-1 shrink-0',
                            isCompact ? 'size-9' : 'size-10',
                        )}
                    />
                    <MentionTextarea
                        id={id}
                        ref={input}
                        org={target.kind === 'org' ? target.org.id : undefined}
                        rows={1}
                        autoFocus={autoFocus}
                        value={body}
                        maxLength={maxLength}
                        onValueChange={(value) => {
                            stash.updateBody(value);

                            if (
                                error !== null &&
                                characterCount(value) < maxLength
                            ) {
                                showError(null);
                            }

                            if (value.trim() !== '') {
                                pingTyping(target);
                            }
                        }}
                        onFocus={moveCaretToEnd}
                        onPaste={onPaste}
                        onKeyDown={onKeyDown}
                        placeholder={placeholder}
                        aria-invalid={shownError !== null || undefined}
                        className={cn(
                            'field-sizing-content max-h-[min(10rem,calc(var(--visual-viewport-height,100dvh)/4))] min-h-10 min-w-0 flex-1 resize-none overflow-x-hidden rounded-lg border border-input bg-card px-4 py-2 text-base leading-snug outline-none placeholder:text-muted-foreground focus-visible:border-primary focus-visible:outline-none aria-invalid:border-destructive md:text-[15px]',
                            { 'min-h-9 py-1.5 md:text-sm': isCompact },
                        )}
                        data-test="message-input"
                    />
                    <Button
                        type="submit"
                        disabled={
                            attachments.reading ||
                            (trimmed === '' && !hasImages)
                        }
                        // Pushing keeps the keyboard open, so the next message can follow right away.
                        onPointerDown={(event) => event.preventDefault()}
                        className={cn(
                            'shrink-0',
                            isCompact ? 'h-9 min-w-16' : 'h-10 min-w-20',
                        )}
                    >
                        Push
                    </Button>
                </div>
            </form>
        </>
    );
}

/**
 * Takes the composer's place when the user can't push: a fetched request to merge or drop, their own request
 * waiting to be merged, or a partner with remotes off. As tall as the composer, so nothing jumps.
 */
function AccessBar({
    access,
    partner,
    isCompact,
    onDropped,
}: {
    access: PushAccess;
    partner: User;
    isCompact: boolean;
    onDropped?: () => void;
}) {
    const [isSettling, setIsSettling] = useState(false);

    const settle = async (isMerging: boolean) => {
        setIsSettling(true);
        const isSettled = await settleRequest(partner, isMerging);
        setIsSettling(false);

        if (!isSettled) {
            toast.error('Could not update the request. Try again.');

            return;
        }

        if (!isMerging) {
            onDropped?.();
        }
    };

    return (
        <>
            <div aria-hidden className="h-6 shrink-0" />
            <div
                className={cn(
                    'flex min-h-16 shrink-0 items-center gap-3 border-t',
                    isCompact ? 'px-3 py-2.5' : 'px-4 py-3',
                )}
                data-test={`access-${access}`}
            >
                {access === 'request' ? (
                    <>
                        <p className="min-w-0 flex-1 text-sm text-muted-foreground">
                            <span className="font-mono text-foreground">
                                @{partner.username}
                            </span>{' '}
                            wants to push to you. Merge to reply, or drop it.
                            They won&apos;t be told.
                        </p>
                        <Button
                            variant="outline"
                            className="min-w-16"
                            disabled={isSettling}
                            onClick={() => void settle(false)}
                            data-test="drop-request"
                        >
                            Drop
                        </Button>
                        <Button
                            className="min-w-20"
                            disabled={isSettling}
                            onClick={() => void settle(true)}
                            data-test="merge-request"
                        >
                            Merge
                        </Button>
                    </>
                ) : (
                    <p className="min-w-0 flex-1 font-mono text-[13px] text-muted-foreground">
                        {
                            {
                                waiting: `pushed. waiting for @${partner.username} to merge your request`,
                                restricted: `@${partner.username} doesn't follow you, so you can't push to them yet`,
                                closed: `@${partner.username} isn't taking remotes`,
                                open: '',
                                request: '',
                            }[access]
                        }
                    </p>
                )}
            </div>
        </>
    );
}

function ThreadSkeleton({ isCompact }: { isCompact: boolean }) {
    return (
        <div
            aria-hidden
            className={cn(
                'mt-auto flex flex-col gap-2 motion-safe:animate-in motion-safe:delay-300 motion-safe:fade-in-0 motion-safe:fill-mode-backwards',
                isCompact ? 'p-3' : 'p-4',
            )}
        >
            <Skeleton className="h-9 w-48 rounded-lg" />
            <Skeleton className="h-9 w-64 self-end rounded-lg" />
            <Skeleton className="h-9 w-40 self-end rounded-lg" />
            <Skeleton className="h-9 w-56 rounded-lg" />
        </div>
    );
}
