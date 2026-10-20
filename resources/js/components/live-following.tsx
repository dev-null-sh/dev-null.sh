import { usePage } from '@inertiajs/react';
import { ArrowUpRight } from 'lucide-react';
import { LiveBadge } from '@/components/live-badge';
import { UserAvatar } from '@/components/user-avatar';

export function LiveFollowing() {
    const { liveFollowing = [] } = usePage().props;
    const users = liveFollowing.filter((user) => user.live);

    if (users.length === 0) return null;

    return (
        <section
            aria-labelledby="aside-live-following"
            className="-mt-4 rounded-xl border bg-card p-4"
            data-test="live-following"
        >
            <h2
                id="aside-live-following"
                className="mb-4 flex items-center justify-between text-sm font-semibold"
            >
                Live now
                <LiveBadge />
            </h2>
            <ul className="space-y-2">
                {users.map((user) => (
                    <li key={user.id}>
                        <a
                            href={user.live!.url}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="group -mx-2 flex items-center gap-3 rounded-lg px-2 py-3 transition-colors hover:bg-accent focus-visible:outline-2 focus-visible:outline-primary"
                            aria-label={`Watch ${user.name} live`}
                        >
                            <UserAvatar
                                user={user}
                                className="size-10 shrink-0 ring-2 ring-red-600 ring-offset-2 ring-offset-card"
                            />
                            <span className="min-w-0 flex-1">
                                <span className="block truncate text-sm font-semibold">
                                    {user.name}
                                </span>
                                <span className="block truncate font-mono text-xs text-muted-foreground">
                                    @{user.username}
                                </span>
                                <span className="mt-1 block text-xs text-muted-foreground">
                                    Streaming on YouTube
                                </span>
                            </span>
                            <ArrowUpRight
                                aria-hidden
                                className="size-4 shrink-0 text-muted-foreground transition-colors group-hover:text-primary"
                            />
                        </a>
                    </li>
                ))}
            </ul>
        </section>
    );
}
