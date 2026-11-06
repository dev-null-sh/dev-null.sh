import { Bell } from 'lucide-react';
import { useId, useState } from 'react';
import { toast } from 'sonner';
import { replyOf, snippetOf } from '@/components/chat/message-parts';
import { Button } from '@/components/ui/button';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { UserAvatar } from '@/components/user-avatar';
import { clearMentions } from '@/lib/chat';
import { formatExactCount } from '@/lib/numbers';
import { cn, formatClockTime, formatDayLabel } from '@/lib/utils';
import type { Message, OrgRef } from '@/types';

/**
 * Sits after Call in an org's composer and lists the messages that mentioned the user until they open or clear them.
 * Opening one jumps to it in the thread, and clears it once it is shown.
 */
export function MentionsBell({
    org,
    mentions,
    count,
    isCompact,
    onJump,
}: {
    org: OrgRef;
    mentions: Message[];
    count: number;
    isCompact: boolean;
    onJump: (messageId: number) => Promise<boolean>;
}) {
    const [isOpen, setIsOpen] = useState(false);
    const headingId = useId();
    const clear = (messageId?: number) =>
        void clearMentions(org, messageId).catch(() =>
            toast.error('Could not clear mentions. Try again.'),
        );

    return (
        <Popover open={isOpen} onOpenChange={setIsOpen}>
            <Tooltip>
                <TooltipTrigger asChild>
                    <PopoverTrigger asChild>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            aria-label={
                                count === 0
                                    ? 'Mentions'
                                    : `Mentions, ${formatExactCount(count)} unread`
                            }
                            className={cn(
                                'relative shrink-0 text-muted-foreground data-[state=open]:bg-accent data-[state=open]:text-foreground',
                                isCompact ? 'size-9' : 'size-10',
                            )}
                            data-test="mentions"
                        >
                            <Bell className="size-5" />
                            {count > 0 && (
                                <span
                                    aria-hidden
                                    className="absolute top-1 right-0 min-w-4 rounded-sm bg-primary px-1 font-mono text-[10px] leading-4 font-medium text-primary-foreground tabular-nums"
                                >
                                    {count > 99 ? '99+' : count}
                                </span>
                            )}
                        </Button>
                    </PopoverTrigger>
                </TooltipTrigger>
                <TooltipContent>
                    {count > 99
                        ? `${formatExactCount(count)} mentions`
                        : 'Mentions'}
                </TooltipContent>
            </Tooltip>
            <PopoverContent
                side="top"
                align="start"
                collisionPadding={8}
                aria-labelledby={headingId}
                className="flex max-h-[min(var(--radix-popover-content-available-height),26rem)] w-80 max-w-[calc(100vw-2rem)] flex-col overflow-hidden p-0"
                data-test="mentions-panel"
            >
                <div className="flex h-10 shrink-0 items-center gap-2 border-b px-3">
                    <h2 id={headingId} className="text-sm font-semibold">
                        Mentions
                    </h2>
                    <span className="font-mono text-[11px] text-muted-foreground tabular-nums">
                        {formatExactCount(count)}
                    </span>
                    {mentions.length > 0 && (
                        <button
                            type="button"
                            onClick={() => clear()}
                            className="ml-auto rounded-sm px-1.5 py-1 font-mono text-[11px] text-primary transition-colors hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        >
                            mark all read
                        </button>
                    )}
                </div>
                {mentions.length === 0 ? (
                    <p className="px-3 py-8 text-center font-mono text-[13px] text-muted-foreground">
                        no new mentions
                    </p>
                ) : (
                    <ol className="min-h-0 flex-1 scrollbar-thin overflow-y-auto overscroll-contain">
                        {mentions.map((message) => (
                            <li
                                key={message.id}
                                className="border-b last:border-b-0"
                            >
                                <button
                                    type="button"
                                    onClick={() => {
                                        setIsOpen(false);
                                        void onJump(message.id).then(
                                            (isShown) => {
                                                if (isShown) {
                                                    clear(message.id);
                                                }
                                            },
                                        );
                                    }}
                                    className="flex w-full gap-2.5 px-3 py-2.5 text-left transition-colors hover:bg-accent focus-visible:bg-accent focus-visible:outline-none"
                                    data-test="mentions-item"
                                >
                                    {message.user !== undefined && (
                                        <UserAvatar
                                            user={message.user}
                                            className="size-7 shrink-0"
                                            pixels={56}
                                        />
                                    )}
                                    <span className="min-w-0 flex-1">
                                        <span className="flex items-baseline gap-1.5">
                                            <span className="min-w-0 truncate text-[13px] font-semibold">
                                                {message.user?.name}
                                            </span>
                                            <time
                                                dateTime={message.created_at}
                                                className="ml-auto shrink-0 font-mono text-[11px] text-muted-foreground tabular-nums"
                                            >
                                                {formatDayLabel(
                                                    message.created_at,
                                                )}{' '}
                                                ·{' '}
                                                {formatClockTime(
                                                    message.created_at,
                                                )}
                                            </time>
                                        </span>
                                        <span className="line-clamp-2 text-[13px] break-words text-muted-foreground">
                                            {snippetOf(replyOf(message))}
                                        </span>
                                    </span>
                                </button>
                            </li>
                        ))}
                    </ol>
                )}
            </PopoverContent>
        </Popover>
    );
}
