import { Link, router, usePage } from '@inertiajs/react';
import type { CSSProperties, ReactNode, RefObject } from 'react';
import { useEffect, useLayoutEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import UserFollowController from '@/actions/App/Http/Controllers/UserFollowController';
import UserFollowersController from '@/actions/App/Http/Controllers/UserFollowersController';
import UserFollowingController from '@/actions/App/Http/Controllers/UserFollowingController';
import { BioText } from '@/components/bio-text';
import {
    FollowLabel,
    followVisitOptions,
    useFollow,
    useFollowHover,
} from '@/components/follow-button';
import {
    LiveAvatarMarker,
    LiveStreamCard,
} from '@/components/live-stream-card';
import { LocalTime } from '@/components/local-time';
import { MembersLink } from '@/components/members-link';
import { Button } from '@/components/ui/button';
import { UserAvatar } from '@/components/user-avatar';
import { useIsMuting, UserSafetyMenu } from '@/components/user-safety-menu';
import { signIn } from '@/lib/auth';
import { exactCountTitle, formatCount } from '@/lib/numbers';
import { toastWhenThrottled } from '@/lib/throttle';
import { cn, laneColor } from '@/lib/utils';
import { preview } from '@/routes/mention';
import { show } from '@/routes/user';
import { show as userPreview } from '@/routes/user-preview';
import type {
    Commit,
    CommitAuthor,
    CursorPaginated,
    Profile,
    User,
} from '@/types';

type PreviewData = {
    bioProfiles: Record<string, string>;
    isFollowing: boolean;
    followsYou: boolean;
    isMuting: boolean;
    isBlocking: boolean;
} & (
    | { isUnavailable: false; user: Profile }
    | { isUnavailable: true; user: User }
);

type Position =
    | { left: number; top: number; bottom?: never }
    | { left: number; bottom: number; top?: never };

const openDelay = 450;
const closeDelay = 200;
const previewWidth = 304;
const previewHeight = 260;
const viewportMargin = 8;
// Where the centre of the preview's avatar sits: border, padding and half of the size-14 avatar.
const avatarCentre = 45;
const cache = new Map<string, PreviewData>();

async function fetchPreview(url: string): Promise<PreviewData> {
    const response = await fetch(url, {
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    });

    if (!response.ok) {
        toastWhenThrottled(response);

        throw new Error(`Unable to load ${url}`);
    }

    const data = (await response.json()) as PreviewData;
    cache.set(url, data);

    return data;
}

function positionFor(trigger: HTMLElement): Position {
    const rect = trigger.getBoundingClientRect();
    const left = Math.min(
        Math.max(rect.left, 8),
        window.innerWidth - previewWidth - 8,
    );

    return rect.bottom + 8 + previewHeight > window.innerHeight &&
        rect.top > previewHeight
        ? { left, bottom: window.innerHeight - rect.top + 8 }
        : { left, top: rect.bottom + 8 };
}

function positionAround(anchor: HTMLElement): Position {
    const rect = anchor.getBoundingClientRect();
    const left = rect.left + rect.width / 2 - avatarCentre;
    const top = rect.top + rect.height / 2 - avatarCentre;

    return {
        left: Math.min(
            Math.max(left, viewportMargin),
            window.innerWidth - previewWidth - viewportMargin,
        ),
        top: Math.max(top, viewportMargin),
    };
}

export function UserPreview({
    user,
    userId,
    children,
    className,
    anchor,
    onHoverChange,
}: {
    /**
     * An organization's commits have no hover card, so they only report hovering.
     */
    user: User | Pick<User, 'handle'> | CommitAuthor;
    userId?: string;
    children: ReactNode;
    className?: string;
    anchor?: RefObject<HTMLElement | null>;
    onHoverChange?: (isHovering: boolean) => void;
}) {
    const previewUrl = userId ? preview(userId).url : userPreview(user).url;
    const triggerRef = useRef<HTMLSpanElement>(null);
    const previewRef = useRef<HTMLDivElement>(null);
    const timer = useRef<ReturnType<typeof setTimeout>>(undefined);
    const isMenuOpen = useRef(false);
    const isPointerOver = useRef(false);
    const [position, setPosition] = useState<Position | null>(null);
    const [isOpen, setIsOpen] = useState(false);
    const [data, setData] = useState<PreviewData | null>(
        () => cache.get(previewUrl) ?? null,
    );
    const previewUser =
        data?.user ??
        ('id' in user && !('type' in user && user.type === 'organization')
            ? user
            : null);

    const close = () => {
        setIsOpen(false);

        if (!previewRef.current) {
            setPosition(null);
        }
    };

    const scheduleOpen = () => {
        clearTimeout(timer.current);

        if (!window.matchMedia('(hover: hover)').matches) {
            return;
        }

        timer.current = setTimeout(() => {
            if (!triggerRef.current) {
                return;
            }

            setPosition(
                anchor?.current
                    ? positionAround(anchor.current)
                    : positionFor(triggerRef.current),
            );
            setIsOpen(true);
            setData(cache.get(previewUrl) ?? null);
            fetchPreview(previewUrl).then(setData, () => undefined);
        }, openDelay);
    };

    const scheduleClose = () => {
        clearTimeout(timer.current);

        if (isMenuOpen.current) {
            return;
        }

        timer.current = setTimeout(close, closeDelay);
    };

    const changeHover = (isHovering: boolean) => {
        isPointerOver.current = isHovering;
        onHoverChange?.(isHovering);
    };

    const keepOpen = () => {
        clearTimeout(timer.current);
        setIsOpen(true);
    };

    useEffect(() => {
        if (!position) {
            return;
        }

        const onKeyDown = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                close();
            }
        };

        window.addEventListener('scroll', close, { passive: true });
        window.addEventListener('keydown', onKeyDown);

        return () => {
            window.removeEventListener('scroll', close);
            window.removeEventListener('keydown', onKeyDown);
        };
    }, [position]);

    useEffect(() => {
        const preview = previewRef.current;

        if (isOpen || !preview) {
            return;
        }

        let isClosing = true;

        Promise.all(
            preview.getAnimations().map((animation) => animation.finished),
        ).then(
            () => isClosing && setPosition(null),
            () => undefined,
        );

        return () => {
            isClosing = false;
        };
    }, [isOpen]);

    useLayoutEffect(() => {
        if (!previewRef.current || position?.top === undefined) {
            return;
        }

        const overflow =
            position.top +
            previewRef.current.offsetHeight -
            (window.innerHeight - viewportMargin);
        const top = Math.max(position.top - overflow, viewportMargin);

        if (overflow > 0 && top !== position.top) {
            setPosition({ left: position.left, top });
        }
    }, [position, data]);

    useEffect(() => () => clearTimeout(timer.current), []);

    return (
        <>
            <span
                ref={triggerRef}
                onPointerEnter={(event) => {
                    if (event.pointerType !== 'mouse') {
                        return;
                    }

                    changeHover(true);

                    if (!('type' in user && user.type === 'organization')) {
                        scheduleOpen();
                    }
                }}
                onPointerLeave={(event) => {
                    if (event.pointerType !== 'mouse') {
                        return;
                    }

                    changeHover(false);
                    scheduleClose();
                }}
                className={className}
            >
                {children}
            </span>

            {position &&
                previewUser &&
                createPortal(
                    <div
                        ref={previewRef}
                        role="dialog"
                        aria-label={`About @${previewUser.handle}`}
                        onPointerEnter={(event) => {
                            if (event.pointerType !== 'mouse') {
                                return;
                            }

                            changeHover(true);
                            keepOpen();
                        }}
                        onPointerLeave={(event) => {
                            if (event.pointerType !== 'mouse') {
                                return;
                            }

                            changeHover(false);
                            scheduleClose();
                        }}
                        data-state={isOpen ? 'open' : 'closed'}
                        className="pointer-events-auto fixed z-50 w-76 rounded-lg border bg-popover p-4 text-popover-foreground shadow-[0_12px_32px_-12px_rgb(0_0_0/0.35)] ease-spring data-[state=closed]:scale-70 data-[state=closed]:opacity-0 data-[state=closed]:ease-in motion-safe:transition-[opacity,scale] motion-safe:duration-560 motion-safe:data-[state=closed]:duration-160 starting:scale-70 starting:opacity-0"
                        style={{
                            ...position,
                            transformOrigin: anchor
                                ? `${avatarCentre}px ${avatarCentre}px`
                                : `left ${position.top === undefined ? 'bottom' : 'top'}`,
                        }}
                        data-test="user-preview"
                    >
                        <UserPreviewContent
                            user={previewUser}
                            data={data}
                            onMenuOpenChange={(isOpen) => {
                                isMenuOpen.current = isOpen;
                                clearTimeout(timer.current);

                                if (!isOpen && !isPointerOver.current) {
                                    scheduleClose();
                                }
                            }}
                        />
                    </div>,
                    document.body,
                )}
        </>
    );
}

function UserPreviewContent({
    user,
    data,
    onMenuOpenChange,
}: {
    user: User | Profile;
    data: PreviewData | null;
    onMenuOpenChange: (isOpen: boolean) => void;
}) {
    const {
        component,
        props: { auth, log, commits },
    } = usePage<{ commits?: CursorPaginated<Commit> }>();
    const isCurrentUser = auth.user?.id === user.id;
    const lane = laneColor(user.lane);

    const { isJustToggled, markToggled, onMouseLeave } = useFollowHover();
    const { isFollowing, callbacks } = useFollow(
        user,
        data?.isFollowing ?? false,
    );
    const [isMuting] = useIsMuting(user, data?.isMuting ?? false);
    const followsDelta =
        Number(isFollowing) - Number(data?.isFollowing ?? false);

    const toggleFollow = () => {
        if (auth.user === null) {
            signIn();

            return;
        }

        if (!data || data.isUnavailable) {
            return;
        }

        router.visit(
            isFollowing
                ? UserFollowController.destroy(user)
                : UserFollowController.store(user),
            {
                preserveScroll: true,
                preserveState: true,
                async: true,
                ...followVisitOptions(component, log, commits),
                ...callbacks,
                onSuccess: () => router.flushAll(),
            },
        );
    };

    return (
        <div style={{ '--lane': lane } as CSSProperties}>
            <div className="flex items-start justify-between gap-3">
                <Link
                    href={show(user)}
                    prefetch
                    className="relative rounded-full"
                >
                    <UserAvatar
                        user={user}
                        className={cn(
                            'size-14 text-lg ring-2 ring-(--lane) ring-offset-2 ring-offset-popover',
                            { 'ring-red-600': user.live },
                        )}
                    />
                    {user.live && <LiveAvatarMarker className="ring-popover" />}
                </Link>

                {!isCurrentUser &&
                    (data ? (
                        <div className="flex gap-2">
                            {auth.user && (
                                <UserSafetyMenu
                                    user={user}
                                    isMuting={data.isMuting}
                                    isBlocking={data.isBlocking}
                                    className="size-8"
                                    onOpenChange={onMenuOpenChange}
                                />
                            )}
                            {!data.isUnavailable && (
                                <Button
                                    size="sm"
                                    variant={
                                        isFollowing ? 'outline' : 'default'
                                    }
                                    className="group/follow min-w-26"
                                    onClick={() => {
                                        markToggled();
                                        toggleFollow();
                                    }}
                                    onMouseLeave={onMouseLeave}
                                    aria-label={
                                        isFollowing
                                            ? `Unfollow @${user.handle}`
                                            : `${data.followsYou ? 'Follow back' : 'Follow'} @${user.handle}`
                                    }
                                    data-test="user-preview-follow-button"
                                >
                                    <FollowLabel
                                        isFollowing={isFollowing}
                                        isJustToggled={isJustToggled}
                                        followsYou={data.followsYou}
                                    />
                                </Button>
                            )}
                        </div>
                    ) : (
                        <span className="h-8 w-26 rounded-md bg-muted-foreground/15 motion-safe:animate-pulse" />
                    ))}
            </div>

            <Link
                href={show(user)}
                prefetch
                className="mt-3 block truncate font-semibold hover:underline"
            >
                {user.name}
            </Link>
            <p className="flex items-center gap-1.5 font-mono text-[13px] text-muted-foreground">
                <span
                    aria-hidden
                    className="size-2 shrink-0 rounded-full bg-(--lane)"
                />
                <span className="truncate">@{user.handle}</span>
                {data?.followsYou && (
                    <span className="ml-1 shrink-0 rounded-sm bg-muted px-1.5 py-0.5 font-sans text-xs">
                        Follows you
                    </span>
                )}
                {isMuting && (
                    <span className="ml-1 shrink-0 rounded-sm bg-muted px-1.5 py-0.5 font-sans text-xs">
                        Muted
                    </span>
                )}
            </p>

            {user.bio && !data?.isUnavailable && (
                <p className="mt-2 line-clamp-3 text-sm text-pretty">
                    <BioText bio={user.bio} profiles={data?.bioProfiles} />
                </p>
            )}

            {user.show_local_time && user.timezone && !data?.isUnavailable && (
                <LocalTime
                    as="p"
                    timeZone={user.timezone}
                    className="mt-2 text-sm text-muted-foreground"
                />
            )}

            <div className="mt-3 flex gap-4 text-sm">
                {data?.isUnavailable ? (
                    <p className="text-muted-foreground">
                        {data.isBlocking
                            ? `You blocked @${user.handle}.`
                            : 'This profile is unavailable.'}
                    </p>
                ) : data ? (
                    <>
                        <span>
                            <span
                                className="font-mono font-medium"
                                title={exactCountTitle(data.user.commits_count)}
                            >
                                {formatCount(data.user.commits_count)}
                            </span>{' '}
                            <span className="text-muted-foreground">
                                {data.user.commits_count === 1
                                    ? 'commit'
                                    : 'commits'}
                            </span>
                        </span>
                        <MembersLink
                            href={UserFollowingController.index(user)}
                            className="hover:underline"
                        >
                            <span
                                className="font-mono font-medium"
                                title={exactCountTitle(
                                    data.user.following_count,
                                )}
                            >
                                {formatCount(data.user.following_count)}
                            </span>{' '}
                            <span className="text-muted-foreground">
                                following
                            </span>
                        </MembersLink>
                        <MembersLink
                            href={UserFollowersController.index(user)}
                            className="hover:underline"
                        >
                            <span
                                className="font-mono font-medium"
                                title={exactCountTitle(
                                    data.user.followers_count + followsDelta,
                                )}
                            >
                                {formatCount(
                                    data.user.followers_count + followsDelta,
                                )}
                            </span>{' '}
                            <span className="text-muted-foreground">
                                {data.user.followers_count + followsDelta === 1
                                    ? 'follower'
                                    : 'followers'}
                            </span>
                        </MembersLink>
                    </>
                ) : (
                    <span className="my-0.5 h-4 w-48 rounded-sm bg-muted-foreground/15 motion-safe:animate-pulse" />
                )}
            </div>

            {user.live && !data?.isUnavailable && (
                <div className="mt-4 border-t pt-4">
                    <LiveStreamCard user={user} stream={user.live} />
                </div>
            )}
        </div>
    );
}
