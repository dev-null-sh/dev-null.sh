import { Link, router, usePage } from '@inertiajs/react';
import type { CSSProperties, ReactNode } from 'react';
import { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import UserFollowController from '@/actions/App/Http/Controllers/UserFollowController';
import UserFollowersController from '@/actions/App/Http/Controllers/UserFollowersController';
import UserFollowingController from '@/actions/App/Http/Controllers/UserFollowingController';
import UserPreviewController from '@/actions/App/Http/Controllers/UserPreviewController';
import { Button } from '@/components/ui/button';
import { UserAvatar } from '@/components/user-avatar';
import { cn, laneColor } from '@/lib/utils';
import { show } from '@/routes/user';
import type { Profile, User } from '@/types';

type PreviewData = {
    user: Profile;
    isFollowing: boolean;
    followsYou: boolean;
};

type Position =
    | { left: number; top: number; bottom?: never }
    | { left: number; bottom: number; top?: never };

const openDelay = 450;
const closeDelay = 200;
const previewWidth = 304;
const previewHeight = 260;
const cache = new Map<string, PreviewData>();

async function fetchPreview(user: User): Promise<PreviewData> {
    const response = await fetch(UserPreviewController.show(user).url, {
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    });

    if (!response.ok) {
        throw new Error(`Unable to load @${user.username}`);
    }

    const data = (await response.json()) as PreviewData;
    cache.set(user.username, data);

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

export function UserPreview({
    user,
    children,
    className,
    onHoverChange,
}: {
    user: User;
    children: ReactNode;
    className?: string;
    onHoverChange?: (isHovering: boolean) => void;
}) {
    const triggerRef = useRef<HTMLSpanElement>(null);
    const timer = useRef<ReturnType<typeof setTimeout>>(undefined);
    const [position, setPosition] = useState<Position | null>(null);
    const [data, setData] = useState<PreviewData | null>(
        () => cache.get(user.username) ?? null,
    );

    const close = () => setPosition(null);

    const scheduleOpen = () => {
        clearTimeout(timer.current);

        if (!window.matchMedia('(hover: hover)').matches) {
            return;
        }

        timer.current = setTimeout(() => {
            if (!triggerRef.current) {
                return;
            }

            setPosition(positionFor(triggerRef.current));
            setData(cache.get(user.username) ?? null);
            fetchPreview(user).then(setData, () => undefined);
        }, openDelay);
    };

    const scheduleClose = () => {
        clearTimeout(timer.current);
        timer.current = setTimeout(close, closeDelay);
    };

    const keepOpen = () => clearTimeout(timer.current);

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

    useEffect(() => () => clearTimeout(timer.current), []);

    return (
        <>
            <span
                ref={triggerRef}
                onMouseEnter={() => {
                    onHoverChange?.(true);
                    scheduleOpen();
                }}
                onMouseLeave={() => {
                    onHoverChange?.(false);
                    scheduleClose();
                }}
                className={className}
            >
                {children}
            </span>

            {position &&
                createPortal(
                    <div
                        role="dialog"
                        aria-label={`About @${user.username}`}
                        onMouseEnter={keepOpen}
                        onMouseLeave={scheduleClose}
                        className="fixed z-50 w-76 rounded-xl border bg-popover p-4 text-popover-foreground shadow-[0_12px_32px_-12px_rgb(0_0_0/0.35)] motion-safe:animate-in motion-safe:fade-in-0 motion-safe:zoom-in-95"
                        style={position}
                        data-test="user-preview"
                    >
                        <UserPreviewContent
                            user={data?.user ?? user}
                            data={data}
                            onChange={(next) => {
                                cache.set(user.username, next);
                                setData(next);
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
    onChange,
}: {
    user: User | Profile;
    data: PreviewData | null;
    onChange: (data: PreviewData) => void;
}) {
    const { auth } = usePage().props;
    const isCurrentUser = auth.user.id === user.id;
    const lane = laneColor(user.lane);

    const toggleFollow = () => {
        if (!data) {
            return;
        }

        const previous = data;
        const isFollowing = !data.isFollowing;

        onChange({
            ...data,
            isFollowing,
            user: {
                ...data.user,
                followers_count:
                    data.user.followers_count + (isFollowing ? 1 : -1),
            },
        });

        router.visit(
            isFollowing
                ? UserFollowController.store(user)
                : UserFollowController.destroy(user),
            {
                preserveScroll: true,
                preserveState: true,
                onError: () => onChange(previous),
            },
        );
    };

    return (
        <div style={{ '--lane': lane } as CSSProperties}>
            <div className="flex items-start justify-between gap-3">
                <Link href={show(user)} className="rounded-full">
                    <UserAvatar
                        user={user}
                        className="size-14 text-lg ring-2 ring-(--lane) ring-offset-2 ring-offset-popover"
                    />
                </Link>

                {!isCurrentUser &&
                    (data ? (
                        <Button
                            size="sm"
                            variant={data.isFollowing ? 'outline' : 'default'}
                            className="group/follow min-w-24"
                            onClick={toggleFollow}
                            aria-label={
                                data.isFollowing
                                    ? `Unfollow @${user.username}`
                                    : `Follow @${user.username}`
                            }
                            data-test="user-preview-follow-button"
                        >
                            {data.isFollowing ? (
                                <>
                                    <span className="group-hover/follow:hidden">
                                        Following
                                    </span>
                                    <span className="hidden text-destructive group-hover/follow:inline">
                                        Unfollow
                                    </span>
                                </>
                            ) : (
                                'Follow'
                            )}
                        </Button>
                    ) : (
                        <span className="h-8 w-24 rounded-md bg-muted motion-safe:animate-pulse" />
                    ))}
            </div>

            <Link
                href={show(user)}
                className="mt-3 block truncate font-semibold hover:underline"
            >
                {user.name}
            </Link>
            <p className="flex items-center gap-1.5 font-mono text-[13px] text-muted-foreground">
                <span
                    aria-hidden
                    className="size-2 shrink-0 rounded-full bg-(--lane)"
                />
                <span className="truncate">@{user.username}</span>
                {data?.followsYou && (
                    <span className="ml-1 shrink-0 rounded bg-muted px-1.5 py-0.5 font-sans text-xs">
                        Follows you
                    </span>
                )}
            </p>

            {user.bio && (
                <p className="mt-2 line-clamp-3 text-sm text-pretty">
                    {user.bio}
                </p>
            )}

            <div className="mt-3 flex gap-4 text-sm">
                {data ? (
                    <>
                        <span>
                            <span className="font-mono font-medium">
                                {data.user.commits_count}
                            </span>{' '}
                            <span className="text-muted-foreground">
                                {data.user.commits_count === 1
                                    ? 'commit'
                                    : 'commits'}
                            </span>
                        </span>
                        <Link
                            href={UserFollowingController.index(user)}
                            className="hover:underline"
                        >
                            <span className="font-mono font-medium">
                                {data.user.following_count}
                            </span>{' '}
                            <span className="text-muted-foreground">
                                following
                            </span>
                        </Link>
                        <Link
                            href={UserFollowersController.index(user)}
                            className="hover:underline"
                        >
                            <span className="font-mono font-medium">
                                {data.user.followers_count}
                            </span>{' '}
                            <span className="text-muted-foreground">
                                {data.user.followers_count === 1
                                    ? 'follower'
                                    : 'followers'}
                            </span>
                        </Link>
                    </>
                ) : (
                    <span
                        className={cn(
                            'h-4 w-48 rounded bg-muted motion-safe:animate-pulse',
                        )}
                    />
                )}
            </div>
        </div>
    );
}
