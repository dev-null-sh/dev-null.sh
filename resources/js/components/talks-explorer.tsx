import { Deferred, Link, usePage } from '@inertiajs/react';
import { Presentation } from 'lucide-react';
import type { CSSProperties } from 'react';
import TalkController from '@/actions/App/Http/Controllers/TalkController';
import { Skeleton } from '@/components/ui/skeleton';
import { UserAvatar } from '@/components/user-avatar';
import { laneColor } from '@/lib/utils';
import type { TalkExplorerItem } from '@/types';

function TalksExplorerSkeleton() {
    return (
        <ul className="space-y-4">
            {Array.from({ length: 3 }, (_, index) => (
                <li key={index} className="flex gap-3">
                    <Skeleton className="size-9 shrink-0 rounded-full" />
                    <div className="min-w-0 flex-1 space-y-2">
                        <Skeleton className="h-4 w-3/4" />
                        <Skeleton className="h-3 w-1/2" />
                    </div>
                </li>
            ))}
        </ul>
    );
}

function TalkRow({ talk }: { talk: TalkExplorerItem }) {
    return (
        <li>
            <Link
                href={TalkController.show([talk.user, talk])}
                className="group flex gap-3"
            >
                <UserAvatar
                    user={talk.user}
                    className="size-9 shrink-0 ring-2 ring-(--lane) ring-offset-2 ring-offset-background"
                    style={
                        {
                            '--lane': laneColor(talk.user.lane),
                        } as CSSProperties
                    }
                />
                <span className="min-w-0">
                    <span className="block truncate text-sm font-medium group-hover:underline">
                        {talk.title}
                    </span>
                    <span className="mt-0.5 block truncate font-mono text-xs text-muted-foreground">
                        @{talk.user.username}
                        {talk.event_name ? ` · ${talk.event_name}` : ''}
                    </span>
                </span>
            </Link>
        </li>
    );
}

export function TalksExplorer() {
    const { latestTalks } = usePage().props;

    if (latestTalks !== undefined && latestTalks.length === 0) {
        return null;
    }

    return (
        <section aria-labelledby="aside-talks" className="border-t pt-6">
            <div className="mb-3 flex items-center justify-between gap-2">
                <h2 id="aside-talks" className="text-sm font-semibold">
                    Talks
                </h2>
                <Presentation
                    className="size-3.5 text-muted-foreground"
                    aria-hidden
                />
            </div>
            <Deferred data="latestTalks" fallback={<TalksExplorerSkeleton />}>
                <ul className="space-y-4">
                    {latestTalks?.map((talk) => (
                        <TalkRow key={talk.id} talk={talk} />
                    ))}
                </ul>
            </Deferred>
            {latestTalks === undefined || latestTalks.length > 0 ? (
                <p className="mt-4 font-mono text-[11px] text-muted-foreground">
                    $ find /talks -type presentation
                </p>
            ) : null}
        </section>
    );
}
