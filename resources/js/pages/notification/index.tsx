import { Head, InfiniteScroll, Link } from '@inertiajs/react';
import { Check, MessageSquareReply, UserPlus, X } from 'lucide-react';
import type { CSSProperties } from 'react';
import CommitController from '@/actions/App/Http/Controllers/CommitController';
import { EmptyFeed } from '@/components/commit-feed';
import { Spinner } from '@/components/ui/spinner';
import { UserAvatar } from '@/components/user-avatar';
import { useNow } from '@/hooks/use-now';
import AppLayout from '@/layouts/app-layout';
import { cn, formatRelativeTime, laneColor } from '@/lib/utils';
import { show } from '@/routes/user';
import type { CursorPaginated, Notification } from '@/types';

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
                        Nothing here yet. Follows, replies, and moderation
                        updates for your commits will show up here.
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

function NotificationItem({ notification }: { notification: Notification }) {
    const now = useNow();
    const { actor } = notification;
    const isUnread = notification.read_at === null;
    const isModeration =
        notification.type === 'approved' || notification.type === 'rejected';
    const Icon = {
        followed: UserPlus,
        replied: MessageSquareReply,
        approved: Check,
        rejected: X,
    }[notification.type];

    return (
        <Link
            href={
                notification.type === 'followed'
                    ? show(actor)
                    : CommitController.show({
                          user: actor,
                          commit: notification.commit,
                      })
            }
            className={cn(
                'flex gap-3 px-4 py-3 transition-colors hover:bg-muted/50 sm:px-6',
                { 'bg-primary-soft/50': isUnread },
            )}
        >
            <Icon
                className={cn(
                    'mt-2 size-5 shrink-0',
                    isModeration ? 'text-muted-foreground' : 'text-(--lane)',
                )}
                style={
                    isModeration
                        ? undefined
                        : ({ '--lane': laneColor(actor.lane) } as CSSProperties)
                }
                aria-hidden
            />

            <div className="min-w-0 flex-1">
                <div className="flex items-center gap-2">
                    {isModeration ? (
                        <span className="text-sm font-medium">
                            Moderation update
                        </span>
                    ) : (
                        <UserAvatar user={actor} className="size-8" />
                    )}
                    {isUnread && (
                        <span className="ml-auto size-2 shrink-0 rounded-full bg-primary">
                            <span className="sr-only">Unread</span>
                        </span>
                    )}
                </div>

                <p className="mt-2 text-[15px]">
                    {isModeration ? (
                        `Your commit was ${notification.type}`
                    ) : (
                        <>
                            <span className="font-medium">{actor.name}</span>{' '}
                            {notification.type === 'followed'
                                ? 'followed you'
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
                            {formatRelativeTime(notification.created_at, now)}
                        </time>
                    </span>
                </p>

                {notification.type !== 'followed' && (
                    <p className="mt-1 truncate text-[15px]">
                        {notification.commit.body}
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
