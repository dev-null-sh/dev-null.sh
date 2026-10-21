import { Link } from '@inertiajs/react';
import { BellOff } from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import ConversationController from '@/actions/App/Http/Controllers/ConversationController';
import OrgController from '@/actions/App/Http/Controllers/OrgController';
import { ConversationActions } from '@/components/chat/conversation-actions';
import { MessageStatusGlyph } from '@/components/chat/message-status';
import { eventText, MemberStack, orgTitle } from '@/components/chat/org';
import { Skeleton } from '@/components/ui/skeleton';
import { UserAvatar } from '@/components/user-avatar';
import { useNow } from '@/hooks/use-now';
import { summaryKey, targetOf } from '@/lib/chat';
import type { ChatTarget } from '@/lib/chat';
import { cn, formatRelativeTime, laneColor } from '@/lib/utils';
import type { ConversationSummary, User } from '@/types';

export function ConversationList({
    conversations,
    user,
    activeKey,
    onOpen,
    onLeaveActive,
    onBlocked,
    isCompact = false,
    empty,
}: {
    conversations: ConversationSummary[] | null;
    user: User;
    activeKey?: string | null;
    onOpen?: (target: ChatTarget) => void;
    /**
     * Called when the open conversation is archived or blocked from its row.
     */
    onLeaveActive?: () => void;
    onBlocked?: () => void;
    isCompact?: boolean;
    empty: ReactNode;
}) {
    if (conversations === null) {
        return <ConversationListSkeleton isCompact={isCompact} />;
    }

    if (conversations.length === 0) {
        return empty;
    }

    return (
        <ul aria-label="Conversations">
            {conversations.map((conversation) => (
                <ConversationRow
                    key={summaryKey(conversation)}
                    conversation={conversation}
                    user={user}
                    isActive={summaryKey(conversation) === activeKey}
                    onOpen={onOpen}
                    onLeaveActive={onLeaveActive}
                    onBlocked={onBlocked}
                    isCompact={isCompact}
                />
            ))}
        </ul>
    );
}

function ConversationRow({
    conversation,
    user,
    isActive,
    onOpen,
    onLeaveActive,
    onBlocked,
    isCompact,
}: {
    conversation: ConversationSummary;
    user: User;
    isActive: boolean;
    onOpen?: (target: ChatTarget) => void;
    onLeaveActive?: () => void;
    onBlocked?: () => void;
    isCompact: boolean;
}) {
    const now = useNow();
    const [isMenuOpen, setIsMenuOpen] = useState(false);
    const {
        partner,
        last_message: lastMessage,
        ahead,
        is_muted: isMuted,
    } = conversation;
    const target = targetOf(conversation);
    const others = (conversation.members ?? [])
        .map((member) => member.user)
        .filter((member) => member.id !== user.id);
    const title =
        partner === null
            ? orgTitle(conversation, conversation.members, user)
            : `@${partner.username}`;
    const isMine = lastMessage?.user_id === user.id && !lastMessage.event;
    const lastBody = lastMessage?.body || 'sent an image';
    const preview =
        lastMessage === null
            ? ''
            : lastMessage.event
              ? eventText(lastMessage, user)
              : isMine
                ? `you: ${lastBody}`
                : partner === null
                  ? `${lastMessage.user?.name.split(' ')[0] ?? 'someone'}: ${lastBody}`
                  : lastBody;
    const className = cn(
        'relative grid w-full grid-cols-[auto_minmax(0,1fr)_auto] items-center gap-3 text-left transition-colors hover:bg-accent focus-visible:-outline-offset-2',
        isCompact ? 'px-3.5 py-2.5' : 'px-4 py-3',
        {
            'bg-primary-soft hover:bg-primary-soft': isActive,
        },
    );
    const content = (
        <>
            {isActive && (
                <span
                    aria-hidden
                    className="absolute inset-y-2 left-0 w-[3px] rounded-r-full bg-primary"
                />
            )}
            {partner === null ? (
                <OrgAvatar users={others} isCompact={isCompact} />
            ) : (
                <UserAvatar
                    user={partner}
                    className={cn(
                        'ring-2 ring-(--lane)',
                        isCompact ? 'size-8' : 'size-10',
                    )}
                    style={
                        {
                            '--lane': laneColor(partner.lane),
                        } as React.CSSProperties
                    }
                />
            )}
            <span className="min-w-0">
                {partner === null ? (
                    <span className="flex min-w-0 items-baseline gap-1.5">
                        <span
                            className={cn(
                                'truncate font-semibold',
                                isCompact ? 'text-sm' : 'text-[15px]',
                            )}
                            title={title}
                        >
                            {title}
                        </span>
                        <span className="shrink-0 font-mono text-xs text-muted-foreground tabular-nums">
                            org · {others.length + 1}
                        </span>
                        {isMuted && <MutedGlyph />}
                    </span>
                ) : (
                    <span className="flex min-w-0 items-baseline gap-1.5">
                        <span
                            className={cn(
                                'truncate font-semibold',
                                isCompact ? 'text-sm' : 'text-[15px]',
                            )}
                            title={partner.name}
                        >
                            {partner.name}
                        </span>
                        <span className="flex min-w-0 shrink-4 items-center gap-1 font-mono text-xs text-muted-foreground">
                            <span className="truncate">
                                @{partner.username}
                            </span>
                        </span>
                        {isMuted && <MutedGlyph />}
                    </span>
                )}
                <span
                    className={cn(
                        'flex min-w-0 items-center gap-1.5 text-muted-foreground',
                        isCompact ? 'text-[13px]' : 'text-sm',
                        { 'text-foreground': ahead > 0 && !isMuted },
                    )}
                >
                    {isMine && lastMessage && partner !== null && (
                        <MessageStatusGlyph
                            status={
                                conversation.partner_read_through_id >=
                                lastMessage.id
                                    ? 'pulled'
                                    : 'pushed'
                            }
                            lane={user.lane}
                            partnerLane={partner.lane}
                        />
                    )}
                    <span
                        className={cn('truncate', {
                            'font-mono text-xs': lastMessage?.event,
                        })}
                        title={preview}
                    >
                        {preview}
                    </span>
                </span>
            </span>
            <span className="flex min-w-14 flex-col items-end transition-opacity group-focus-within:opacity-0 group-data-[menu=open]:opacity-0 [@media(hover:hover)]:group-hover:opacity-0">
                {ahead > 0 ? (
                    <span
                        title={`${ahead} unread messages`}
                        className={cn(
                            'rounded-sm px-2 font-mono text-[11px] leading-5 font-medium whitespace-nowrap tabular-nums',
                            isMuted
                                ? 'bg-muted text-muted-foreground'
                                : 'bg-primary text-primary-foreground',
                        )}
                    >
                        <span aria-hidden>
                            {ahead > 99 ? '99+' : ahead} ahead
                        </span>
                        <span className="sr-only">{ahead} unread messages</span>
                    </span>
                ) : (
                    conversation.last_message_at && (
                        <time
                            dateTime={conversation.last_message_at}
                            className="font-mono text-xs text-muted-foreground tabular-nums"
                        >
                            {formatRelativeTime(
                                conversation.last_message_at,
                                now,
                            )}
                        </time>
                    )
                )}
            </span>
        </>
    );

    return (
        <li
            className="group relative"
            data-menu={isMenuOpen ? 'open' : undefined}
        >
            {onOpen ? (
                <button
                    type="button"
                    onClick={() => onOpen(target)}
                    className={className}
                >
                    {content}
                </button>
            ) : (
                <Link
                    href={
                        partner === null
                            ? OrgController.show({ org: conversation.id })
                            : ConversationController.show(partner)
                    }
                    preserveScroll
                    aria-current={isActive ? 'page' : undefined}
                    className={className}
                >
                    {content}
                </Link>
            )}
            <div
                className={cn(
                    'absolute top-1/2 -translate-y-1/2 opacity-0 transition-opacity group-focus-within:opacity-100 group-data-[menu=open]:opacity-100 [@media(hover:hover)]:group-hover:opacity-100 [@media(hover:none)]:hidden',
                    isCompact ? 'right-2.5' : 'right-3',
                )}
            >
                <ConversationActions
                    target={target}
                    title={title}
                    user={user}
                    isArchived={conversation.is_archived}
                    isMuted={conversation.is_muted}
                    onLeave={isActive ? onLeaveActive : undefined}
                    onBlocked={isActive ? onLeaveActive : onBlocked}
                    onOpenChange={setIsMenuOpen}
                />
            </div>
        </li>
    );
}

function MutedGlyph() {
    return (
        <span
            title="Muted"
            className="shrink-0 self-center text-muted-foreground"
            data-test="conversation-muted"
        >
            <BellOff aria-hidden className="size-3.5" />
            <span className="sr-only">Muted</span>
        </span>
    );
}

/**
 * Two of the other members, overlapping, in the space of one avatar.
 */
function OrgAvatar({
    users,
    isCompact,
}: {
    users: User[];
    isCompact: boolean;
}) {
    const [first, second] = users;

    return (
        <span
            aria-hidden
            className={cn(
                'relative shrink-0',
                isCompact ? 'size-8' : 'size-10',
            )}
        >
            {second === undefined ? (
                first !== undefined && (
                    <UserAvatar user={first} className="size-full" />
                )
            ) : (
                <MemberStack
                    users={[first, second]}
                    max={2}
                    className="absolute inset-0 *:absolute *:m-0! [&>*:first-child]:top-0 [&>*:first-child]:left-0 [&>*:last-child]:right-0 [&>*:last-child]:bottom-0"
                    avatarClassName={isCompact ? 'size-5.5' : 'size-7'}
                />
            )}
        </span>
    );
}

function ConversationListSkeleton({ isCompact }: { isCompact: boolean }) {
    return (
        <div
            aria-hidden
            className="motion-safe:animate-in motion-safe:delay-300 motion-safe:fade-in-0 motion-safe:fill-mode-backwards"
        >
            {Array.from({ length: 4 }, (_, index) => (
                <div
                    key={index}
                    className={cn(
                        'grid grid-cols-[auto_minmax(0,1fr)] items-center gap-3',
                        isCompact ? 'px-3.5 py-2.5' : 'px-4 py-3',
                    )}
                >
                    <Skeleton
                        className={cn(
                            'rounded-full',
                            isCompact ? 'size-8' : 'size-10',
                        )}
                    />
                    <span className="space-y-1.5">
                        <Skeleton className="h-3.5 w-32" />
                        <Skeleton className="h-3 w-48" />
                    </span>
                </div>
            ))}
        </div>
    );
}
