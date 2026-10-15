import { Head, InfiniteScroll, Link, usePage } from '@inertiajs/react';
import { Settings2, X } from 'lucide-react';
import NotificationClearController from '@/actions/App/Http/Controllers/NotificationClearController';
import NotificationController from '@/actions/App/Http/Controllers/NotificationController';
import NotificationReadController from '@/actions/App/Http/Controllers/NotificationReadController';
import { EmptyFeed } from '@/components/commit-feed';
import { Spinner } from '@/components/ui/spinner';
import { UserAvatar } from '@/components/user-avatar';
import { useNow } from '@/hooks/use-now';
import AppLayout from '@/layouts/app-layout';
import { cn, formatRelativeTime } from '@/lib/utils';
import { edit } from '@/routes/notification-settings';
import type { CommitNotification, CursorPaginated } from '@/types';

export default function Index({
    notifications,
}: {
    notifications: CursorPaginated<CommitNotification>;
}) {
    const { auth } = usePage().props;
    const now = useNow();

    return (
        <>
            <Head title="Notifications" />

            <div className="sticky top-0 z-10 flex h-14 items-center justify-between gap-3 border-b bg-card px-4 sm:px-6">
                <h1 className="font-semibold">Notifications</h1>
                <div className="flex items-center gap-4">
                    {auth.unreadNotificationsCount > 0 && (
                        <Link
                            as="button"
                            href={NotificationReadController.store()}
                            preserveScroll
                            className="text-sm text-muted-foreground hover:text-foreground"
                        >
                            Mark all as read
                        </Link>
                    )}
                    {notifications.data.length > 0 && (
                        <Link
                            as="button"
                            href={NotificationClearController.destroy()}
                            preserveScroll
                            className="text-sm text-muted-foreground hover:text-foreground"
                        >
                            Clear all
                        </Link>
                    )}
                    <Link
                        href={edit()}
                        aria-label="Notification settings"
                        className="text-muted-foreground hover:text-foreground"
                    >
                        <Settings2 className="size-5" />
                    </Link>
                </div>
            </div>

            {notifications.data.length === 0 ? (
                <div className="px-6 py-16 text-center sm:px-12">
                    <EmptyFeed command="fatal: no notifications yet">
                        New commits from people you follow show up here.
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
                            <li key={notification.id} className="relative">
                                <Link
                                    href={NotificationController.show(
                                        notification.id,
                                    )}
                                    className={cn(
                                        'flex items-start gap-3 py-3 pr-12 pl-4 hover:bg-muted/50 sm:pl-6',
                                        {
                                            'bg-primary-soft/40':
                                                notification.read_at === null,
                                        },
                                    )}
                                >
                                    <UserAvatar
                                        user={notification.data.user}
                                        className="size-10"
                                    />
                                    <div className="min-w-0">
                                        <p className="truncate text-sm">
                                            <span className="font-medium">
                                                {notification.data.user.name}
                                            </span>{' '}
                                            <span className="font-mono text-muted-foreground">
                                                committed ·{' '}
                                                {formatRelativeTime(
                                                    notification.created_at,
                                                    now,
                                                )}
                                            </span>
                                        </p>
                                        <p className="mt-1 line-clamp-2 text-sm">
                                            {notification.data.body}
                                        </p>
                                    </div>
                                </Link>
                                <Link
                                    as="button"
                                    href={NotificationController.destroy(
                                        notification.id,
                                    )}
                                    preserveScroll
                                    aria-label="Clear notification"
                                    className="absolute top-3 right-3 rounded-md p-1 text-muted-foreground hover:bg-accent hover:text-foreground sm:right-5"
                                >
                                    <X className="size-4" />
                                </Link>
                            </li>
                        ))}
                    </ul>
                </InfiniteScroll>
            )}
        </>
    );
}

Index.layout = AppLayout;
