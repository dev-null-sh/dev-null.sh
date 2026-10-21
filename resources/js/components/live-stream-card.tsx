import { Link } from '@inertiajs/react';
import * as DialogPrimitive from '@radix-ui/react-dialog';
import { ArrowUpRight } from 'lucide-react';
import type { ReactNode } from 'react';
import { useEffect, useRef, useState } from 'react';
import { LiveBadge } from '@/components/live-badge';
import { Button } from '@/components/ui/button';
import { DialogOverlay, DialogPortal } from '@/components/ui/dialog';
import {
    Popover,
    PopoverAnchor,
    PopoverContent,
} from '@/components/ui/popover';
import { UserAvatar } from '@/components/user-avatar';
import { hostname } from '@/lib/urls';
import { cn } from '@/lib/utils';
import { show } from '@/routes/user';
import type { LiveStream, User } from '@/types';

const openDelay = 300;
const closeDelay = 200;

/**
 * The stream itself plays on the provider: the card shows what is on and sends the viewer there in a new tab.
 */
export function LiveStreamCard({
    user,
    stream,
}: {
    user: Pick<User, 'name'>;
    stream: LiveStream;
}) {
    return (
        <div data-test="live-stream-card">
            <a
                href={stream.url}
                target="_blank"
                rel="noopener noreferrer"
                tabIndex={-1}
                className="relative block aspect-video overflow-hidden rounded-lg bg-muted"
            >
                <img
                    src={stream.thumbnail_url}
                    alt=""
                    loading="lazy"
                    className="size-full object-cover"
                />
                <LiveBadge className="absolute top-2 left-2" />
            </a>
            <p className="mt-3 line-clamp-2 font-semibold text-pretty">
                {user.name} is live
            </p>
            <p className="font-mono text-[13px] text-muted-foreground">
                {hostname(stream.url)}
            </p>
            <Button asChild className="mt-3 w-full">
                <a
                    href={stream.url}
                    target="_blank"
                    rel="noopener noreferrer"
                    data-test="live-stream-watch"
                >
                    Watch now
                    <ArrowUpRight />
                </a>
            </Button>
        </div>
    );
}

/**
 * The avatar's ring turns red and a LIVE label sits on its edge, sized to the avatar it marks.
 */
export function LiveAvatarMarker({ className }: { className?: string }) {
    return (
        <LiveBadge
            className={cn(
                'absolute -bottom-1.5 left-1/2 -translate-x-1/2 ring-2 ring-card',
                className,
            )}
        />
    );
}

/**
 * Wraps whatever marks a user as live. With a mouse, hovering it shows the stream's card, unless `hoverCard` is off
 * because a user preview already shows it there. On touch, where nothing hovers, tapping it opens the card as a
 * sheet instead of following the link it wraps.
 */
export function LiveStreamTrigger({
    user,
    hoverCard = true,
    showProfile = true,
    className,
    children,
}: {
    user: Pick<User, 'name' | 'username' | 'avatar_url' | 'live'>;
    hoverCard?: boolean;
    showProfile?: boolean;
    className?: string;
    children: ReactNode;
}) {
    const [isHoverOpen, setIsHoverOpen] = useState(false);
    const [isSheetOpen, setIsSheetOpen] = useState(false);
    const timer = useRef<ReturnType<typeof setTimeout>>(undefined);

    useEffect(() => () => clearTimeout(timer.current), []);

    const stream = user.live;

    if (!stream) {
        return <span className={className}>{children}</span>;
    }

    const hover = (isHovering: boolean) => {
        clearTimeout(timer.current);
        timer.current = setTimeout(
            () => setIsHoverOpen(isHovering),
            isHovering ? openDelay : closeDelay,
        );
    };

    return (
        <Popover open={hoverCard && isHoverOpen} onOpenChange={setIsHoverOpen}>
            <PopoverAnchor asChild>
                <span
                    className={className}
                    onPointerEnter={(event) =>
                        event.pointerType === 'mouse' && hover(true)
                    }
                    onPointerLeave={(event) =>
                        event.pointerType === 'mouse' && hover(false)
                    }
                    onClickCapture={(event) => {
                        if (window.matchMedia('(hover: hover)').matches) {
                            return;
                        }

                        event.preventDefault();
                        event.stopPropagation();
                        setIsSheetOpen(true);
                    }}
                >
                    {children}
                </span>
            </PopoverAnchor>

            <PopoverContent
                align="start"
                sideOffset={8}
                className="w-76 rounded-lg p-3"
                onOpenAutoFocus={(event) => event.preventDefault()}
                onPointerEnter={(event) =>
                    event.pointerType === 'mouse' && hover(true)
                }
                onPointerLeave={(event) =>
                    event.pointerType === 'mouse' && hover(false)
                }
            >
                <LiveStreamCard user={user} stream={stream} />
            </PopoverContent>

            <LiveStreamSheet
                user={user}
                stream={stream}
                showProfile={showProfile}
                open={isSheetOpen}
                onOpenChange={setIsSheetOpen}
            />
        </Popover>
    );
}

/**
 * A sheet from the bottom on phones, and a small dialog on wider touch screens such as tablets.
 */
function LiveStreamSheet({
    user,
    stream,
    showProfile,
    open,
    onOpenChange,
}: {
    user: Pick<User, 'name' | 'username' | 'avatar_url'>;
    stream: LiveStream;
    showProfile: boolean;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    return (
        <DialogPrimitive.Root open={open} onOpenChange={onOpenChange}>
            <DialogPortal>
                <DialogOverlay className="bg-black/50" />
                <DialogPrimitive.Content
                    aria-describedby={undefined}
                    onOpenAutoFocus={(event) => event.preventDefault()}
                    className="fixed inset-x-0 bottom-0 z-50 rounded-t-lg border-t bg-popover p-4 pb-[max(1rem,env(safe-area-inset-bottom))] text-popover-foreground shadow-lg duration-200 data-[state=closed]:animate-out data-[state=closed]:slide-out-to-bottom data-[state=open]:animate-in data-[state=open]:slide-in-from-bottom sm:inset-x-auto sm:top-1/2 sm:bottom-auto sm:left-1/2 sm:w-96 sm:-translate-1/2 sm:rounded-lg sm:border sm:data-[state=closed]:fade-out-0 sm:data-[state=closed]:slide-out-to-bottom-0 sm:data-[state=open]:fade-in-0 sm:data-[state=open]:slide-in-from-bottom-0"
                    data-test="live-stream-sheet"
                >
                    <span
                        aria-hidden
                        className="mx-auto mb-3 block h-1 w-10 rounded-full bg-muted-foreground/30 sm:hidden"
                    />
                    <DialogPrimitive.Title className="sr-only">
                        {user.name} is live
                    </DialogPrimitive.Title>

                    <LiveStreamCard user={user} stream={stream} />

                    {showProfile && (
                        <div className="mt-4 flex items-center gap-3 border-t pt-4">
                            <span className="relative flex shrink-0">
                                <UserAvatar
                                    user={user}
                                    className="size-11 ring-2 ring-red-600 ring-offset-2 ring-offset-popover"
                                />
                                <LiveAvatarMarker className="-bottom-2 px-1 text-[9px] leading-3.5 ring-popover" />
                            </span>
                            <div className="min-w-0 flex-1">
                                <p className="truncate font-semibold">
                                    {user.name}
                                </p>
                                <p className="truncate font-mono text-[13px] text-muted-foreground">
                                    @{user.username}
                                </p>
                            </div>
                            <Button variant="secondary" asChild>
                                <Link
                                    href={show(user)}
                                    onClick={() => onOpenChange(false)}
                                >
                                    Open profile
                                </Link>
                            </Button>
                        </div>
                    )}
                </DialogPrimitive.Content>
            </DialogPortal>
        </DialogPrimitive.Root>
    );
}
