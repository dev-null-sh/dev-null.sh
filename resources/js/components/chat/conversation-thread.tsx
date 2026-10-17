import { ArrowDown } from 'lucide-react';
import type {
    ClipboardEvent,
    FormEvent,
    KeyboardEvent,
    ReactNode,
} from 'react';
import {
    memo,
    useEffect,
    useLayoutEffect,
    useMemo,
    useRef,
    useState,
} from 'react';
import { toast } from 'sonner';
import { MessageStatusGlyph } from '@/components/chat/message-status';
import { EventLine, typingText } from '@/components/chat/org';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';
import { UserAvatar } from '@/components/user-avatar';
import { useThread } from '@/hooks/use-chat';
import {
    settleRequest,
    loadOlderMessages,
    markThreadRead,
    pingTyping,
    retryMessage,
    sendMessage,
    targetKey,
} from '@/lib/chat';
import type { ChatTarget, PendingMessage } from '@/lib/chat';
import {
    characterCount,
    cn,
    formatDayLabel,
    laneColor,
    limitCharacters,
    localDayKey,
} from '@/lib/utils';
import type { Message, OrgMember, PushAccess, User } from '@/types';

const maxLength = 2000;

const noMessages: Message[] = [];

const noPending: PendingMessage[] = [];

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
    const isOrg = target.kind === 'org';
    // Renaming an org updates the thread's target, not the one this was opened with.
    const current = thread?.target ?? target;
    const scroller = useRef<HTMLDivElement>(null);
    const headLine = useRef<HTMLDivElement>(null);
    const list = useRef<HTMLOListElement>(null);
    const isPinned = useRef(true);
    const hasScrolledIn = useRef(false);
    const previousHeight = useRef<number | null>(null);
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
    const lastMine = useMemo(
        () => lastMineOf(messages, pending, user),
        [messages, pending, user],
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

            if (headLine.current === null) {
                element.scrollTop = element.scrollHeight;
            }

            isPinned.current =
                element.scrollHeight -
                    element.scrollTop -
                    element.clientHeight <
                48;

            return;
        }

        if (previousHeight.current !== null) {
            element.scrollTop += element.scrollHeight - previousHeight.current;
            previousHeight.current = null;

            return;
        }

        if (isPinned.current) {
            element.scrollTop = element.scrollHeight;
            seenLatest.current = latestId;
        } else {
            setUnseen(
                messages.filter(
                    (message) =>
                        message.id > seenLatest.current &&
                        message.user_id !== user.id &&
                        !message.event,
                ).length,
            );
        }
        // Scrolling follows new content only, not every poll that changes nothing.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [contentKey, thread?.isLoaded, messages[0]?.id]);

    // Rows settle their height after the first paint, so a pinned thread follows them down.
    useEffect(() => {
        const element = scroller.current;
        const content = list.current;

        if (element === null || content === null) {
            return;
        }

        const observer = new ResizeObserver(() => {
            if (isPinned.current) {
                element.scrollTop = element.scrollHeight;
            }
        });

        observer.observe(content);

        return () => observer.disconnect();
    }, [thread?.isLoaded]);

    const onScroll = () => {
        const element = scroller.current;

        if (element === null) {
            return;
        }

        isPinned.current =
            element.scrollHeight - element.scrollTop - element.clientHeight <
            48;

        if (isPinned.current) {
            seenLatest.current = latestId;
            setUnseen(0);

            if (isVisible) {
                markThreadRead(target);
            }
        }

        if (
            element.scrollTop < 120 &&
            thread?.has_older &&
            !thread.isLoadingOlder
        ) {
            previousHeight.current = element.scrollHeight;
            void loadOlderMessages(target);
        }
    };

    const jumpToLatest = () => {
        const element = scroller.current;

        element?.scrollTo({ top: element.scrollHeight, behavior: 'smooth' });
    };

    return (
        <section
            aria-label={
                current.kind === 'direct'
                    ? `Conversation with ${current.partner.name}`
                    : `Org ${current.org.name ?? ''}`.trim()
            }
            className="relative flex min-h-0 min-w-0 flex-1 flex-col"
        >
            {header}

            <div
                ref={scroller}
                onScroll={onScroll}
                className="flex min-h-0 flex-1 [scrollbar-width:thin] flex-col overflow-y-auto overscroll-contain"
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
                            'mt-auto flex flex-col pb-1',
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
                                lastMine={lastMine}
                                user={user}
                                target={target}
                                headLine={headLine}
                                isCompact={isCompact}
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

            {(thread?.access ?? 'open') === 'open' ? (
                <Composer
                    target={current}
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
 * Only re-renders when its own messages, or the read position, change. Rows off screen skip layout and paint.
 */
const ThreadItem = memo(function ThreadItem({
    item,
    pulls,
    lastMine,
    user,
    target,
    headLine,
    isCompact,
}: {
    item: Item;
    pulls: Pulls;
    lastMine: string | null;
    user: User;
    target: ChatTarget;
    headLine: React.RefObject<HTMLDivElement | null>;
    isCompact: boolean;
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
            className={cn(
                'flex flex-col gap-0.5 [contain-intrinsic-size:auto_3rem] [content-visibility:auto] [&+&]:mt-2.5',
                {
                    'items-end': item.isMine,
                    'items-start': !item.isMine,
                },
            )}
        >
            {item.author !== null && (
                <span
                    className="flex max-w-full min-w-0 items-center gap-1.5 px-1.5 pb-0.5 text-xs text-muted-foreground"
                    title={item.author.name}
                >
                    <UserAvatar user={item.author} className="size-4" />
                    <span className="truncate font-medium text-foreground">
                        {item.author.name}
                    </span>
                    <span
                        aria-hidden
                        className="size-1.5 shrink-0 rounded-full"
                        style={{ background: laneColor(item.author.lane) }}
                    />
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
                        isFirst={index === 0}
                        isLast={isLast}
                        isLatestMine={key === lastMine}
                        pulls={pulls}
                        user={user}
                        target={target}
                        isCompact={isCompact}
                    />
                );
            })}
        </li>
    );
});

function lastMineOf(
    messages: Message[],
    pendingMessages: PendingMessage[],
    user: User,
): string | null {
    const pending = pendingMessages.at(-1);

    if (pending) {
        return pending.client_id;
    }

    return (
        messages.findLast(
            (message) => message.user_id === user.id && !message.event,
        )?.client_id ?? null
    );
}

function BubbleWithMeta({
    bubble,
    isMine,
    isFirst,
    isLast,
    isLatestMine,
    pulls,
    user,
    target,
    isCompact,
}: {
    bubble: Bubble;
    isMine: boolean;
    isFirst: boolean;
    isLast: boolean;
    isLatestMine: boolean;
    pulls: Pulls;
    user: User;
    target: ChatTarget;
    isCompact: boolean;
}) {
    const body =
        bubble.kind === 'message' ? bubble.message.body : bubble.pending.body;
    const pulledBy =
        bubble.kind === 'message' && target.kind === 'org'
            ? pulls.members
                  .filter(
                      (member) => member.read_through_id >= bubble.message.id,
                  )
                  .map((member) => member.user)
            : [];
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

    return (
        <>
            <div
                className={cn(
                    'w-fit max-w-[85%] rounded-[18px] px-3.5 py-2 wrap-anywhere whitespace-pre-wrap transition-[background-color,color,outline-color] duration-200',
                    isCompact ? 'max-w-[88%] px-3 py-1.5' : 'sm:max-w-[78%]',
                    isMine
                        ? 'bg-primary text-primary-foreground'
                        : 'bg-muted text-foreground',
                    {
                        'rounded-tr-md': isMine && !isFirst,
                        'rounded-br-md': isMine && !isLast,
                        'rounded-tl-md': !isMine && !isFirst,
                        'rounded-bl-md': !isMine && !isLast,
                        'bg-transparent text-foreground outline-[1.5px] -outline-offset-[1.5px] outline-primary outline-dashed':
                            status === 'staged',
                        'bg-transparent text-foreground outline-[1.5px] -outline-offset-[1.5px] outline-destructive outline-solid':
                            status === 'rejected',
                    },
                )}
            >
                {body}
            </div>

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
            ) : isMine && isLatestMine ? (
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
                        <PulledBy
                            users={pulledBy}
                            total={pulls.members.length}
                        />
                    )}
                </MetaLine>
            ) : (
                isLast &&
                bubble.kind === 'message' && (
                    <MetaLine>
                        <time dateTime={bubble.message.created_at}>
                            {timeOf(bubble.message.created_at)}
                        </time>
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
 * Counts characters once the message nears the limit, and refuses a paste that would cross it rather than
 * quietly cutting it short.
 */
function Composer({
    target,
    isCompact,
    autoFocus,
    typing,
    onSend,
}: {
    target: ChatTarget;
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
    const [body, setBody] = useState('');
    const [error, setError] = useState<string | null>(null);
    const input = useRef<HTMLTextAreaElement>(null);
    const errorTimer = useRef<number | undefined>(undefined);
    const trimmed = body.trim();
    const length = characterCount(body);
    const isCounting = length >= countFrom;

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

        if (trimmed === '') {
            return;
        }

        onSend();
        sendMessage(target, trimmed);
        setBody('');
        showError(null);
        input.current?.focus();
    };

    const onKeyDown = (event: KeyboardEvent<HTMLTextAreaElement>) => {
        if (
            event.key === 'Enter' &&
            !event.shiftKey &&
            !event.nativeEvent.isComposing
        ) {
            submit(event);
        }
    };

    const onPaste = (event: ClipboardEvent<HTMLTextAreaElement>) => {
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
                    {error !== null ? (
                        <span
                            role="alert"
                            className="truncate text-destructive"
                            title={error}
                            data-test="message-error"
                        >
                            {error}
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
                    'flex shrink-0 items-end gap-2 border-t',
                    isCompact ? 'p-2.5' : 'px-4 py-3',
                )}
            >
                <label htmlFor={id} className="sr-only">
                    {placeholder}
                </label>
                <textarea
                    id={id}
                    ref={input}
                    rows={1}
                    autoFocus={autoFocus}
                    value={body}
                    onChange={(event) => {
                        const value = limitCharacters(
                            event.target.value,
                            maxLength,
                        );

                        setBody(value);

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
                    onPaste={onPaste}
                    onKeyDown={onKeyDown}
                    placeholder={placeholder}
                    aria-invalid={error !== null || undefined}
                    className={cn(
                        'field-sizing-content max-h-40 min-h-10 min-w-0 flex-1 resize-none overflow-x-hidden rounded-lg border border-input bg-card px-4 py-2 text-base leading-snug outline-none placeholder:text-muted-foreground focus-visible:border-primary focus-visible:outline-none aria-invalid:border-destructive md:text-[15px]',
                        { 'min-h-9 py-1.5 md:text-sm': isCompact },
                    )}
                    data-test="message-input"
                />
                <Button
                    type="submit"
                    disabled={trimmed === ''}
                    className={cn(
                        'shrink-0',
                        isCompact ? 'h-9 min-w-16' : 'h-10 min-w-20',
                    )}
                >
                    Push
                </Button>
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
            <Skeleton className="h-9 w-48 rounded-[18px]" />
            <Skeleton className="h-9 w-64 self-end rounded-[18px]" />
            <Skeleton className="h-9 w-40 self-end rounded-[18px]" />
            <Skeleton className="h-9 w-56 rounded-[18px]" />
        </div>
    );
}
