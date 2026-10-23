import { usePage } from '@inertiajs/react';
import { ExternalLink } from 'lucide-react';
import { useRef, useState } from 'react';
import { flushSync } from 'react-dom';
import {
    ChangelogAuthorLink,
    ChangelogAvatar,
    ChangelogLane,
    changelogLaneColor,
} from '@/components/changelog-lane';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { Skeleton } from '@/components/ui/skeleton';
import { useNow } from '@/hooks/use-now';
import type {
    Changelog,
    ChangelogDay,
    ChangelogPullRequest,
} from '@/lib/changelog';
import { changelogDayHeading, changelogDays } from '@/lib/changelog';
import { formatCount } from '@/lib/numbers';
import { cn } from '@/lib/utils';

const shownPerDay = 5;

function DaySection({
    day,
    timeZone,
    isRevealed,
    onNavigate,
}: {
    day: ChangelogDay;
    timeZone: string;
    isRevealed: boolean;
    onNavigate?: () => void;
}) {
    const [isExpanded, setIsExpanded] = useState(false);
    const now = useNow();
    const firstRevealedLink = useRef<HTMLAnchorElement>(null);
    const heading = changelogDayHeading(
        day.date,
        timeZone,
        now,
        usePage().props.auth.dateTime,
    );
    const shown = day.pullRequests.slice(0, shownPerDay);
    const hidden = day.pullRequests.slice(shownPerDay);
    return (
        <section
            data-day={day.date}
            aria-label={`${heading.title}, ${heading.date}`}
            className={cn(
                'grid gap-4 border-b py-5 last:border-b-0 sm:grid-cols-[8.5rem_minmax(0,1fr)] sm:gap-5',
                isRevealed &&
                    'motion-safe:transition-[opacity,translate] motion-safe:duration-300 motion-safe:ease-out starting:translate-y-1 starting:opacity-0',
            )}
        >
            <div className="flex flex-wrap items-end justify-between gap-x-4 gap-y-3 sm:sticky sm:top-5 sm:block sm:self-start">
                <div>
                    <h3 className="text-sm font-semibold">{heading.title}</h3>
                    <p className="text-xs text-muted-foreground">
                        {heading.date}
                    </p>
                </div>
                <p className="flex items-baseline gap-1.5 tabular-nums sm:mt-2.5 sm:block">
                    <span className="text-2xl leading-none font-semibold sm:text-[28px]">
                        {day.pullRequests.length}
                    </span>
                    <span className="text-xs text-muted-foreground sm:mt-0.5 sm:block">
                        merged by {day.authors.length}
                    </span>
                </p>
                <ul className="flex w-full flex-wrap gap-1 sm:mt-2.5">
                    {day.authors.map((author) => (
                        <li
                            key={author.login}
                            className="flex rounded-full"
                            style={{
                                boxShadow: `0 0 0 1.5px ${changelogLaneColor(author)}`,
                            }}
                        >
                            <ChangelogAuthorLink
                                author={author}
                                onClick={onNavigate}
                                className="flex rounded-full"
                            >
                                <ChangelogAvatar
                                    avatar={author.avatar}
                                    size={20}
                                />
                            </ChangelogAuthorLink>
                        </li>
                    ))}
                </ul>
            </div>
            <div className="min-w-0">
                <ChangelogLane
                    pullRequests={shown}
                    continues={hidden.length > 0}
                    timeZone={timeZone}
                    onNavigate={onNavigate}
                />
                {hidden.length > 0 &&
                    (isExpanded ? (
                        <div className="grid grid-rows-[1fr] opacity-100 motion-safe:transition-[grid-template-rows,opacity] motion-safe:duration-300 motion-safe:ease-out starting:grid-rows-[0fr] starting:opacity-0">
                            <div className="min-h-0 overflow-hidden">
                                <ChangelogLane
                                    pullRequests={hidden}
                                    previousAuthor={shown.at(-1)?.author}
                                    firstLinkRef={firstRevealedLink}
                                    timeZone={timeZone}
                                    onNavigate={onNavigate}
                                />
                            </div>
                        </div>
                    ) : (
                        <div className="flex gap-2.5">
                            <span aria-hidden className="relative w-6 shrink-0">
                                <span className="absolute top-0 left-1/2 h-3.5 w-0.5 -translate-x-1/2 bg-rail" />
                                <span className="absolute top-3.5 left-1/2 size-1.5 -translate-1/2 rounded-full bg-rail" />
                            </span>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                className="h-7 min-w-28 text-xs tabular-nums"
                                onClick={() => {
                                    flushSync(() => setIsExpanded(true));
                                    firstRevealedLink.current?.focus({
                                        preventScroll: true,
                                    });
                                }}
                            >
                                Show {hidden.length} more
                            </Button>
                        </div>
                    ))}
            </div>
        </section>
    );
}

function DaysSkeleton() {
    return (
        <div
            aria-hidden
            className="grid gap-4 py-5 motion-safe:animate-in motion-safe:delay-300 motion-safe:fill-mode-backwards motion-safe:fade-in sm:grid-cols-[8.5rem_minmax(0,1fr)] sm:gap-5"
        >
            <div className="space-y-2">
                <Skeleton className="h-4 w-14" />
                <Skeleton className="h-3 w-10" />
                <Skeleton className="mt-3 h-7 w-9" />
            </div>
            <div className="space-y-4">
                {[0, 1, 2, 3, 4].map((index) => (
                    <div key={index} className="space-y-1.5 pl-8.5">
                        <Skeleton className="h-4 w-3/4" />
                        <Skeleton className="h-3 w-1/2" />
                    </div>
                ))}
            </div>
        </div>
    );
}

export function ChangelogDialog({
    open,
    onOpenChange,
    changelog,
    hasFailed,
    isLoadingOlder,
    hasOlderFailed,
    onLoadOlder,
    timeZone,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    changelog: Changelog | null;
    hasFailed: boolean;
    isLoadingOlder: boolean;
    hasOlderFailed: boolean;
    onLoadOlder: (onLoaded: (added: ChangelogPullRequest[]) => void) => void;
    timeZone: string;
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="flex max-h-[calc(var(--visual-viewport-height,100dvh)-2rem)] flex-col gap-0 overflow-hidden bg-popover p-0 sm:max-w-2xl">
                <ChangelogContent
                    changelog={changelog}
                    hasFailed={hasFailed}
                    isLoadingOlder={isLoadingOlder}
                    hasOlderFailed={hasOlderFailed}
                    onLoadOlder={onLoadOlder}
                    timeZone={timeZone}
                    isDialog
                    onNavigate={() => onOpenChange(false)}
                />
            </DialogContent>
        </Dialog>
    );
}

export function ChangelogContent({
    changelog,
    hasFailed,
    isLoadingOlder,
    hasOlderFailed,
    onLoadOlder,
    timeZone,
    isDialog = false,
    onNavigate,
}: {
    changelog: Changelog | null;
    hasFailed: boolean;
    isLoadingOlder: boolean;
    hasOlderFailed: boolean;
    onLoadOlder: (onLoaded: (added: ChangelogPullRequest[]) => void) => void;
    timeZone: string;
    isDialog?: boolean;
    onNavigate?: () => void;
}) {
    const [revealedDays, setRevealedDays] = useState<Set<string>>(new Set());
    const scroller = useRef<HTMLDivElement>(null);
    const pullRequests = changelog?.pullRequests ?? [];
    const contributors = new Set(
        pullRequests.map((pullRequest) => pullRequest.author.login),
    ).size;
    const days = changelogDays(pullRequests, timeZone);

    const Description = isDialog ? DialogDescription : 'p';

    return (
        <>
            <div className="border-b px-5 py-4 pr-12">
                {isDialog ? (
                    <DialogTitle className="text-base">Changelog</DialogTitle>
                ) : (
                    <h1 className="text-base font-semibold">Changelog</h1>
                )}
                <Description className="mt-1 text-sm text-muted-foreground">
                    Every pull request merged into{' '}
                    <span className="font-mono">production</span>.
                </Description>
            </div>
            <div
                ref={scroller}
                className={cn(
                    'px-5 [overflow-anchor:none]',
                    isDialog &&
                        'min-h-0 flex-1 overflow-y-auto overscroll-contain',
                )}
            >
                {hasFailed ? (
                    <p className="py-10 text-center text-sm text-muted-foreground">
                        GitHub couldn't be reached. Try again in a minute.
                    </p>
                ) : changelog === null ? (
                    <DaysSkeleton />
                ) : pullRequests.length === 0 ? (
                    <p className="py-10 text-center text-sm text-muted-foreground">
                        Nothing has been merged yet.
                    </p>
                ) : (
                    <>
                        {days.map((day) => (
                            <DaySection
                                key={day.date}
                                day={day}
                                timeZone={timeZone}
                                isRevealed={revealedDays.has(day.date)}
                                onNavigate={onNavigate}
                            />
                        ))}
                        {changelog.olderBefore !== null && (
                            <div className="flex justify-center border-t py-5">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    disabled={isLoadingOlder}
                                    className="h-8 min-w-40 text-xs"
                                    onClick={() =>
                                        onLoadOlder((added) => {
                                            const addedDays = changelogDays(
                                                added,
                                                timeZone,
                                            ).map((day) => day.date);

                                            setRevealedDays(new Set(addedDays));
                                            scroller.current
                                                ?.querySelector<HTMLAnchorElement>(
                                                    `[data-day="${addedDays[0]}"] a[href*="/pull/"]`,
                                                )
                                                ?.focus({
                                                    preventScroll: true,
                                                });
                                        })
                                    }
                                >
                                    {isLoadingOlder
                                        ? 'Loading…'
                                        : hasOlderFailed
                                          ? "Couldn't load, try again"
                                          : 'Show older days'}
                                </Button>
                            </div>
                        )}
                    </>
                )}
            </div>
            <div className="flex items-center justify-between gap-4 border-t px-5 py-2.5 text-xs text-muted-foreground">
                <span className="tabular-nums">
                    {changelog !== null &&
                        `${formatCount(pullRequests.length)} merges · ${formatCount(contributors)} contributors`}
                </span>
                {changelog !== null && (
                    <a
                        href={changelog.commitsUrl}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="inline-flex items-center gap-1 rounded-sm text-sm transition-colors hover:text-foreground"
                    >
                        All commits on GitHub
                        <ExternalLink aria-hidden className="size-3.5" />
                    </a>
                )}
            </div>
        </>
    );
}
