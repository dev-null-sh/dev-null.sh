import { usePage } from '@inertiajs/react';
import { ArrowUpRight } from 'lucide-react';
import { LiveAvatarMarker } from '@/components/live-stream-card';
import { UserAvatar } from '@/components/user-avatar';

export function LiveFollowing() {
    const { liveFollowing = [] } = usePage().props;
    const users = liveFollowing.filter((user) => user.live);

    if (users.length === 0) return null;

    return (
        <section
            aria-labelledby="aside-live-following"
            className="-mt-4 rounded-lg border bg-card p-3"
            data-test="live-following"
        >
            <h2
                id="aside-live-following"
                className="mb-1.5 flex items-center justify-between text-sm font-semibold"
            >
                Live now
            </h2>
            <ul className="space-y-0.5">
                {users.map((user) => (
                    <li key={user.id}>
                        <a
                            href={user.live!.url}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="group -mx-2 flex items-center gap-2.5 rounded-lg px-2 py-2 transition-colors hover:bg-accent focus-visible:outline-2 focus-visible:outline-primary"
                            aria-label={`Watch ${user.name} live`}
                        >
                            <span className="relative mb-1.5 flex shrink-0">
                                <UserAvatar
                                    user={user}
                                    className="size-7 ring-2 ring-red-600 ring-offset-1 ring-offset-card"
                                />
                                <LiveAvatarMarker className="px-1 text-[8px] leading-3" />
                            </span>
                            <span className="min-w-0 flex-1">
                                <span className="block truncate text-sm font-semibold">
                                    {user.name}
                                </span>
                                <span className="block truncate font-mono text-xs text-muted-foreground">
                                    @{user.username}
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
