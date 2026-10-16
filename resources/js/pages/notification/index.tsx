import { Head, InfiniteScroll, Link } from '@inertiajs/react';
import {
    AtSign,
    Check,
    MessageSquareReply,
    Star,
    UserPlus,
    X,
} from 'lucide-react';
import type { CSSProperties } from 'react';
import CommitController from '@/actions/App/Http/Controllers/CommitController';
import { EmptyFeed } from '@/components/commit-feed';
import { Spinner } from '@/components/ui/spinner';
import { UserAvatar } from '@/components/user-avatar';
import { useNow } from '@/hooks/use-now';
import { useUser } from '@/hooks/use-user';
import AppLayout from '@/layouts/app-layout';
import { emojify } from '@/lib/emoji';
import { cn, formatRelativeTime, laneColor } from '@/lib/utils';
import { show } from '@/routes/user';
import type { CursorPaginated, Notification, User } from '@/types';

export default function Index({
    notifications,
}: {
    notifications: CursorPaginated<Notification>;
}) {
    return (
        <>
            <Head title="Notifications" />

            <div className="sticky top-0 z-10 flex h-14 items-center border-b bg-card px-4 sm:px-6">
                <h1 className="font-semibold">Notifications</h1>
            </div>

            {notifications.data.length === 0 ? (
                <div className="px-6 py-16 text-center sm:px-12">
                    <EmptyFeed command="$ cat /dev/notifications">
                        Nothing here yet. Follows, stars, replies, mentions, and
                        moderation updates for your commits will show up here.
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
                            <li key={notification.id}>
                                <NotificationItem notification={notification} />
                            </li>
                        ))}
                    </ul>
                </InfiniteScroll>
            )}
        </>
    );
}

const verbs = {
    followed: 'followed you',
    replied: 'replied to your commit',
    mentioned: 'mentioned you',
    starred: 'starred your commit',
} as const;

function others(count: number): string {
    if (count === 0) {
        return '';
    }

    return ` and ${count} ${count === 1 ? 'other' : 'others'}`;
}

function notificationHref(notification: Notification, recipient: User) {
    switch (notification.type) {
        case 'followed':
            return show(notification.actor);
        case 'starred':
            return CommitController.show({
                user: recipient,
                commit: notification.commit,
            });
        case 'replied':
            return notification.actors_count > 1
                ? CommitController.show({
                      user: recipient,
                      commit: notification.subject_id,
                  })
                : CommitController.show({
                      user: notification.actor,
                      commit: notification.commit,
                  });
        default:
            return CommitController.show({
                user: notification.actor,
                commit: notification.commit,
            });
    }
}

function NotificationItem({ notification }: { notification: Notification }) {
    const now = useNow();
    const { user } = useUser();
    const { actor } = notification;
    const isUnread = notification.unread_count > 0;
    const isModeration =
        notification.type === 'approved' || notification.type === 'rejected';
    const Icon = {
        followed: UserPlus,
        replied: MessageSquareReply,
        starred: Star,
        mentioned: AtSign,
        approved: Check,
        rejected: X,
    }[notification.type];

    return (
        <Link
            href={notificationHref(notification, user)}
            className={cn(
                'flex gap-3 px-4 py-3 transition-colors hover:bg-muted/50 sm:px-6',
                { 'bg-primary-soft/50': isUnread },
            )}
        >
            <Icon
                className={cn(
                    'mt-2 size-5 shrink-0',
                    isModeration ? 'text-muted-foreground' : 'text-(--lane)',
                    { 'fill-current': notification.type === 'starred' },
                )}
                style={
                    isModeration
                        ? undefined
                        : ({ '--lane': laneColor(actor.lane) } as CSSProperties)
                }
                aria-hidden
            />

            <div className="min-w-0 flex-1">
                <div className="flex min-h-8 items-center gap-2">
                    {isModeration ? (
                        <span className="text-sm font-medium">
                            Moderation update
                        </span>
                    ) : (
                        <div className="flex -space-x-2" aria-hidden>
                            {notification.actors.map((stacked, index) => (
                                <UserAvatar
                                    key={stacked.id}
                                    user={stacked}
                                    className="relative size-8 ring-2 ring-card"
                                    style={{
                                        zIndex:
                                            notification.actors.length - index,
                                    }}
                                />
                            ))}
                        </div>
                    )}
                    {isUnread && (
                        <span className="ml-auto size-2 shrink-0 rounded-full bg-primary">
                            <span className="sr-only">Unread</span>
                        </span>
                    )}
                </div>

                <p className="mt-2 text-[15px]">
                    {notification.type === 'approved' ||
                    notification.type === 'rejected' ? (
                        `Your commit was ${notification.type}`
                    ) : (
                        <>
                            <span className="font-medium">{actor.name}</span>
                            {others(notification.actors_count - 1)}{' '}
                            {verbs[notification.type]}
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
                            {formatRelativeTime(notification.created_at, now)}
                        </time>
                    </span>
                </p>

                {notification.type !== 'followed' && (
                    <p className="mt-1 truncate text-[15px]">
                        {emojify(notification.commit.body)}
                    </p>
                )}
                {notification.type === 'rejected' && (
                    <div className="mt-2 text-sm text-muted-foreground">
                        {notification.reason && (
                            <p className="wrap-anywhere whitespace-pre-wrap text-foreground">
                                <span className="font-medium">Reason: </span>
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
    );
}

Index.layout = AppLayout;
