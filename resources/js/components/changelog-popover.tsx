import { usePage } from '@inertiajs/react';
import { ExternalLink } from 'lucide-react';
import { useState } from 'react';
import { ChangelogDialog } from '@/components/changelog-dialog';
import {
    ChangelogAvatar,
    ChangelogLane,
    changelogLaneColor,
} from '@/components/changelog-lane';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { Skeleton } from '@/components/ui/skeleton';
import { useChangelog } from '@/hooks/use-changelog';
import { useNow } from '@/hooks/use-now';
import type { Changelog } from '@/lib/changelog';
import { changelogDayHeading, changelogDays } from '@/lib/changelog';
import { browserTimeZone } from '@/lib/utils';

const latestCount = 5;

function Summary({
    changelog,
    timeZone,
}: {
    changelog: Changelog;
    timeZone: string;
}) {
    const [day] = changelogDays(changelog.pullRequests ?? [], timeZone);
    const preferences = usePage().props.auth.dateTime;
    const now = useNow();

    if (day === undefined) {
        return (
            <p className="px-4 py-6 text-center text-sm text-muted-foreground">
                Nothing has been merged yet.
            </p>
        );
    }

    const heading = changelogDayHeading(day.date, timeZone, now, preferences);
    const latest = (changelog.pullRequests ?? []).slice(0, latestCount);

    return (
        <>
            <div className="flex items-end justify-between gap-3 border-b px-4 pt-3.5 pb-3">
                <div>
                    <p className="text-xs text-muted-foreground">
                        {heading.title} · {heading.date}
                    </p>
                    <p className="mt-0.5 flex items-baseline gap-1.5 tabular-nums">
                        <span className="text-2xl leading-none font-semibold">
                            {day.pullRequests.length}
                        </span>
                        <span className="text-xs text-muted-foreground">
                            merged by {day.authors.length}
                        </span>
                    </p>
                </div>
                <ul className="flex pb-0.5">
                    {day.authors.slice(0, 6).map((author, index) => (
                        <li
                            key={author.login}
                            className="-ml-1.5 flex rounded-full first:ml-0"
                            style={{
                                zIndex: 6 - index,
                                boxShadow: `0 0 0 2px var(--popover), 0 0 0 3.5px ${changelogLaneColor(author)}`,
                            }}
                        >
                            <ChangelogAvatar
                                avatar={author.avatar}
                                size={18}
                                title={`@${author.login}`}
                            />
                        </li>
                    ))}
                </ul>
            </div>
            <div className="px-4 pt-3 pb-0.5">
                <ChangelogLane pullRequests={latest} isCompact />
            </div>
        </>
    );
}

function SummarySkeleton() {
    return (
        <div
            aria-hidden
            className="motion-safe:animate-in motion-safe:delay-300 motion-safe:fill-mode-backwards motion-safe:fade-in"
        >
            <div className="flex items-end justify-between border-b px-4 pt-3.5 pb-3">
                <div>
                    <Skeleton className="h-3 w-24" />
                    <Skeleton className="mt-1.5 h-6 w-28" />
                </div>
                <Skeleton className="h-[18px] w-20 rounded-full" />
            </div>
            <div className="space-y-2.5 px-4 py-3">
                {[0, 1, 2, 3, 4].map((index) => (
                    <Skeleton key={index} className="h-5 w-full" />
                ))}
            </div>
        </div>
    );
}

export function ChangelogPopover() {
    const { auth } = usePage().props;
    const timeZone = auth.user?.timezone ?? browserTimeZone();
    const {
        changelog,
        hasFailed,
        isLoadingOlder,
        hasOlderFailed,
        load,
        loadOlder,
    } = useChangelog(timeZone);
    const [isOpen, setIsOpen] = useState(false);
    const [isDialogOpen, setIsDialogOpen] = useState(false);

    return (
        <>
            <Popover open={isOpen} onOpenChange={setIsOpen}>
                <PopoverTrigger
                    onPointerEnter={load}
                    onFocus={load}
                    onClick={load}
                    className="rounded-sm transition-colors outline-none hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring data-[state=open]:text-foreground"
                >
                    Changelog
                </PopoverTrigger>
                <PopoverContent
                    side="top"
                    align="start"
                    sideOffset={10}
                    collisionPadding={16}
                    aria-label="Changelog"
                    className="max-h-(--radix-popover-content-available-height) w-90 max-w-[calc(100vw-2rem)] overflow-y-auto rounded-lg p-0 shadow-[0_12px_32px_-12px_rgb(0_0_0/0.35)]"
                >
                    {hasFailed ? (
                        <p className="px-4 py-6 text-center text-sm text-muted-foreground">
                            GitHub couldn't be reached. Try again in a minute.
                        </p>
                    ) : changelog === null ? (
                        <SummarySkeleton />
                    ) : (
                        <Summary changelog={changelog} timeZone={timeZone} />
                    )}
                    <div className="flex items-center justify-between gap-4 border-t px-4 py-2.5 text-sm text-muted-foreground">
                        <button
                            type="button"
                            disabled={changelog === null}
                            onClick={() => {
                                setIsOpen(false);
                                setIsDialogOpen(true);
                            }}
                            className="rounded-sm transition-colors hover:text-foreground disabled:pointer-events-none"
                        >
                            View full changelog
                        </button>
                        {changelog !== null && (
                            <a
                                href={changelog.commitsUrl}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="inline-flex items-center gap-1 rounded-sm transition-colors hover:text-foreground"
                            >
                                GitHub
                                <ExternalLink
                                    aria-hidden
                                    className="size-3.5"
                                />
                            </a>
                        )}
                    </div>
                </PopoverContent>
            </Popover>
            <ChangelogDialog
                open={isDialogOpen}
                onOpenChange={setIsDialogOpen}
                changelog={changelog}
                hasFailed={hasFailed}
                isLoadingOlder={isLoadingOlder}
                hasOlderFailed={hasOlderFailed}
                onLoadOlder={loadOlder}
                timeZone={timeZone}
            />
        </>
    );
}
