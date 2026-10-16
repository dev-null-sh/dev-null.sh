import type { CancelToken } from '@inertiajs/core';
import { Head, InfiniteScroll, Link, router, usePoll } from '@inertiajs/react';
import {
    AtSign,
    Check,
    ChevronDown,
    MessageSquareReply,
    Quote,
    Star,
    UserPlus,
    X,
} from 'lucide-react';
import { useId, useRef, useState } from 'react';
import CommitController from '@/actions/App/Http/Controllers/CommitController';
import { index } from '@/actions/App/Http/Controllers/NotificationController';
import { show as groupShow } from '@/actions/App/Http/Controllers/NotificationGroupController';
import { CommitBodyExcerpt } from '@/components/commit-body';
import { EmptyFeed } from '@/components/commit-feed';
import { FollowButton } from '@/components/follow-button';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { UserAvatar } from '@/components/user-avatar';
import { useNow } from '@/hooks/use-now';
import { useUser } from '@/hooks/use-user';
import AppLayout from '@/layouts/app-layout';
import { cn, formatRelativeTime } from '@/lib/utils';
import { show } from '@/routes/user';
import type { CursorPaginated, Notification, NotificationGroup } from '@/types';

type Filters = {
    type:
        | 'all'
        | 'replies'
        | 'quotes'
        | 'stars'
        | 'mentions'
        | 'follows'
        | 'moderation';
};

export default function Index({
    notifications,
    filters,
}: {
    notifications: CursorPaginated<NotificationGroup>;
    filters: Filters;
}) {
    const pollRequest = useRef<CancelToken | null>(null);
    const filterVisit = useRef(0);
    const { start, stop } = usePoll(
        30_000,
        () => ({
            only: ['notifications', 'auth.unreadNotificationsCount'],
            reset: ['notifications'],
            data: {
                cursor: '',
                through: notifications.data.at(-1)?.created_at,
            },
            preserveUrl: true,
            preserveErrors: true,
            onCancelToken: (token) => {
                pollRequest.current = token;
            },
            onFinish: () => {
                pollRequest.current = null;
            },
        }),
        { mode: 'rest', background: 'pause' },
    );
    const changeFilters = (next: Filters) => {
        const visit = ++filterVisit.current;
        stop();
        pollRequest.current?.cancel();
        router.visit(index({ query: { type: next.type } }), {
            only: ['notifications', 'filters', 'auth'],
            reset: ['notifications'],
            preserveState: true,
            onFinish: () => {
                if (visit === filterVisit.current) start();
            },
        });
    };

    return (
        <>
            <Head title="Notifications" />
            <div className="sticky top-0 z-10 border-b bg-card">
                <div className="flex h-14 items-center justify-between px-4 sm:px-6">
                    <h1 className="font-semibold">Notifications</h1>
                </div>
                <div className="flex flex-wrap items-center justify-between gap-3 px-4 pb-3 sm:px-6">
                    <label className="relative text-sm">
                        <span className="sr-only">Notification type</span>
                        <select
                            aria-label="Notification type"
                            value={filters.type}
                            onChange={(event) =>
                                changeFilters({
                                    ...filters,
                                    type: event.target.value as Filters['type'],
                                })
                            }
                            className="h-9 appearance-none rounded-md border bg-background pr-10 pl-3 text-sm focus-visible:outline-2 focus-visible:outline-ring"
                        >
                            <option value="all">All activity</option>
                            <option value="replies">Replies</option>
                            <option value="quotes">Quotes</option>
                            <option value="stars">Stars</option>
                            <option value="mentions">Mentions</option>
                            <option value="follows">Follows</option>
                            <option value="moderation">Moderation</option>
                        </select>
                        <ChevronDown
                            aria-hidden="true"
                            className="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-muted-foreground"
                        />
                    </label>
                </div>
            </div>
            {notifications.data.length === 0 ? (
                <div className="px-6 py-16 text-center sm:px-12">
                    <EmptyFeed command="$ cat /dev/notifications">
                        {filters.type !== 'all'
                            ? 'No activity of this type yet.'
                            : 'Nothing here yet. Follows, stars, replies, quotes, mentions, and moderation updates for your commits will show up here.'}
                    </EmptyFeed>
                </div>
            ) : (
                <InfiniteScroll
                    data="notifications"
                    onlyNext
                    preserveUrl
                    loading={
                        <div className="flex justify-center py-6">
                            <Spinner className="text-muted-foreground" />
                        </div>
                    }
                >
                    <ul className="divide-y">
                        {notifications.data.map((notification) => (
                            <li key={notification.group_id}>
                                <NotificationGroupItem
                                    key={
                                        filters.type +
                                        '-' +
                                        notification.id +
                                        '-' +
                                        notification.event_count
                                    }
                                    notification={notification}
                                    type={filters.type}
                                />
                            </li>
                        ))}
                    </ul>
                </InfiniteScroll>
            )}
        </>
    );
}

function NotificationGroupItem({
    notification,
    type,
}: {
    notification: NotificationGroup;
    type: Filters['type'];
}) {
    const [open, setOpen] = useState(false);
    const [items, setItems] = useState<Notification[]>([]);
    const [cursor, setCursor] = useState<string | null>(null);
    const [loading, setLoading] = useState(false);
    const [failed, setFailed] = useState(false);
    const id = useId();
    const grouped = notification.event_count > 1;

    const load = async (next: string | null = null) => {
        setLoading(true);
        setFailed(false);
        try {
            const response = await fetch(
                groupShow(notification, { query: { type, cursor: next } }).url,
                { headers: { Accept: 'application/json' } },
            );
            if (!response.ok) throw new Error('Unable to load activity');
            const data =
                (await response.json()) as CursorPaginated<Notification>;
            setItems((previous) =>
                next ? [...previous, ...data.data] : data.data,
            );
            setCursor(data.next_cursor);
        } catch {
            setFailed(true);
        } finally {
            setLoading(false);
        }
    };

    return (
        <article
            data-notification-group={notification.group_id}
            className="px-4 py-3 sm:px-6"
        >
            {grouped && (
                <div className="mb-2 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
                    <span className="font-medium">
                        {notification.event_count}{' '}
                        {notification.type === 'followed'
                            ? 'new followers'
                            : notification.type === 'starred'
                              ? 'stars on your commit'
                              : 'updates in this conversation'}
                    </span>
                </div>
            )}
            <NotificationContent notification={notification} summary />
            {grouped && (
                <div className="mt-2">
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        className="-ml-2 text-xs"
                        aria-expanded={open}
                        aria-controls={id}
                        onClick={() => {
                            setOpen(!open);
                            if (!open && items.length === 0) void load();
                        }}
                    >
                        <ChevronDown
                            className={cn(
                                'size-4 transition-transform motion-reduce:transition-none',
                                { 'rotate-180': open },
                            )}
                        />
                        {open ? 'Hide activity' : 'Show activity'}
                    </Button>
                </div>
            )}
            {open && (
                <div id={id} className="mt-2 border-t">
                    <ul className="divide-y">
                        {items.map((item) => (
                            <li key={item.id} className="py-3">
                                <NotificationContent notification={item} />
                            </li>
                        ))}
                    </ul>
                    {loading && (
                        <p
                            role="status"
                            className="py-3 text-sm text-muted-foreground"
                        >
                            Loading activity…
                        </p>
                    )}
                    {failed && (
                        <div role="alert" className="py-3 text-sm">
                            <p>Could not load activity.</p>
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => void load(cursor)}
                            >
                                Try again
                            </Button>
                        </div>
                    )}
                    {!loading && !failed && cursor && (
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => void load(cursor)}
                        >
                            Show older activity
                        </Button>
                    )}
                </div>
            )}
        </article>
    );
}

function FollowBackButton({ actor }: { actor: Notification['actor'] }) {
    const [isFollowing, setIsFollowing] = useState(actor.is_followed);

    return (
        <FollowButton
            user={actor}
            isFollowing={isFollowing}
            size="sm"
            className="min-w-24 shrink-0"
            only={['auth']}
            preserveState
            onSuccess={() => {
                setIsFollowing(!isFollowing);
                router.flushAll();
            }}
        />
    );
}

function NotificationContent({
    notification,
    summary = false,
}: {
    notification: Notification | NotificationGroup;
    summary?: boolean;
}) {
    const now = useNow();
    const { user } = useUser();
    const { actor } = notification;
    const actors =
        summary && 'actors' in notification ? notification.actors : [actor];
    const otherActors =
        summary && 'actors_count' in notification
            ? notification.actors_count - 1
            : 0;
    const moderation =
        notification.type === 'approved' || notification.type === 'rejected';
    const Icon = {
        followed: UserPlus,
        replied: MessageSquareReply,
        quoted: Quote,
        starred: Star,
        mentioned: AtSign,
        approved: Check,
        rejected: X,
    }[notification.type];

    return (
        <div className="flex items-start gap-3">
            <Link
                href={
                    notification.type === 'followed'
                        ? show(actor)
                        : CommitController.show({
                              user:
                                  notification.type === 'starred'
                                      ? user
                                      : actor,
                              commit: notification.commit,
                          })
                }
                className="flex min-w-0 flex-1 gap-3 rounded-md outline-offset-4 hover:opacity-80 focus-visible:outline-2 focus-visible:outline-ring"
            >
                <Icon
                    className={cn(
                        'mt-1.5 size-5 shrink-0 text-muted-foreground',
                        {
                            'fill-current': notification.type === 'starred',
                        },
                    )}
                    aria-hidden
                />
                <div className="min-w-0 flex-1">
                    <div className="flex min-h-8 items-center gap-2">
                        {moderation ? (
                            <span className="text-sm font-medium">
                                Moderation update
                            </span>
                        ) : (
                            <div className="flex -space-x-2" aria-hidden>
                                {actors.map((stacked, index) => (
                                    <UserAvatar
                                        key={stacked.id}
                                        user={stacked}
                                        className="relative size-8 ring-2 ring-card"
                                        style={{
                                            zIndex: actors.length - index,
                                        }}
                                    />
                                ))}
                            </div>
                        )}
                    </div>
                    <p className="mt-2 text-[15px] wrap-anywhere">
                        {moderation ? (
                            `Your commit was ${notification.type}`
                        ) : (
                            <>
                                <span className="font-medium">
                                    {actor.name}
                                </span>{' '}
                                {notification.type === 'starred' &&
                                otherActors > 0
                                    ? `and ${otherActors} ${otherActors === 1 ? 'other' : 'others'} `
                                    : ''}
                                {notification.type === 'followed'
                                    ? 'followed you'
                                    : notification.type === 'starred'
                                      ? 'starred your commit'
                                      : notification.type === 'mentioned'
                                        ? 'mentioned you'
                                        : notification.type === 'quoted'
                                          ? 'quoted your commit'
                                          : 'replied to your commit'}
                            </>
                        )}
                        <span className="text-muted-foreground">
                            <span aria-hidden> · </span>
                            <time
                                dateTime={notification.created_at}
                                title={new Date(
                                    notification.created_at,
                                ).toLocaleString()}
                            >
                                {formatRelativeTime(
                                    notification.created_at,
                                    now,
                                )}
                            </time>
                        </span>
                    </p>
                    {notification.type !== 'followed' && (
                        <CommitBodyExcerpt
                            segments={notification.commit.body_segments}
                            className="mt-1 max-h-40"
                        />
                    )}
                    {notification.type === 'rejected' && (
                        <div className="mt-2 text-sm text-muted-foreground">
                            {notification.reason && (
                                <p className="wrap-anywhere whitespace-pre-wrap text-foreground">
                                    <span className="font-medium">
                                        Reason:{' '}
                                    </span>
                                    {notification.reason}
                                </p>
                            )}
                            <p className="mt-1">
                                Only you can see this update and your rejected
                                commit.
                            </p>
                        </div>
                    )}
                </div>
            </Link>
            {notification.type === 'followed' && (
                <FollowBackButton actor={actor} />
            )}
        </div>
    );
}

Index.layout = AppLayout;
