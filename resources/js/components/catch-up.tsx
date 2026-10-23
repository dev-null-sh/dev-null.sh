import { Link, useRemember } from '@inertiajs/react';
import { Check, Star, X } from 'lucide-react';
import { useState } from 'react';
import CommitController from '@/actions/App/Http/Controllers/CommitController';
import { ActionTooltip } from '@/components/action-tooltip';
import { RailSegment } from '@/components/graph-rail';
import { CommitResult } from '@/components/search-dialog';
import { Button } from '@/components/ui/button';
import { useNow } from '@/hooks/use-now';
import { formatCount, formatExactCount } from '@/lib/numbers';
import { cn, formatDayLabel } from '@/lib/utils';
import type { CatchUp as CatchUpData } from '@/types';

// CatchUpQuery only catches up when more commits came in than these.
const previewCount = 3;

const stagedRailTransition =
    '[view-transition-class:staged-rail] [view-transition-name:staged-rail-catch-up]';

/**
 * Moves focus to the control that takes the place of the one just pressed, once it is rendered.
 */
const focusOnceShown = (id: string) =>
    requestAnimationFrame(() => document.getElementById(id)?.focus());

/**
 * Opens the Following log after some time away with the most starred of what came in meanwhile. Once caught up, it
 * folds into a line that can bring it back for the rest of the visit, or be hidden too.
 */
export function CatchUp({
    catchUp,
    stagedLane,
    isCaughtUp,
    onCaughtUp,
}: {
    catchUp: CatchUpData;
    stagedLane?: string;
    isCaughtUp: boolean;
    onCaughtUp: (isCaughtUp: boolean) => void;
}) {
    const now = useNow();
    const [isExpanded, setIsExpanded] = useState(false);
    const [isHidden, setIsHidden] = useRemember(false, 'catch-up-hidden');
    const moreCount = catchUp.commits.length - previewCount;
    const since = formatDayLabel(catchUp.since, now);

    if (isCaughtUp && isHidden) {
        return null;
    }

    if (isCaughtUp) {
        return (
            <section aria-label="Caught up" className="flex">
                <RailSegment
                    lane={stagedLane}
                    isDashed
                    className={cn(stagedLane && stagedRailTransition)}
                />
                <div className="flex min-w-0 flex-1 items-center gap-1 border-b py-1.5 pr-4">
                    <p
                        className="min-w-0 flex-1 truncate font-mono text-xs text-muted-foreground"
                        title={`caught up · ${formatExactCount(catchUp.commits_count)} commits since ${since}`}
                    >
                        caught up · {formatExactCount(catchUp.commits_count)}{' '}
                        commits
                        <span className="max-sm:hidden"> since {since}</span>
                    </p>
                    <Button
                        id="catch-up-show-again"
                        variant="ghost"
                        size="sm"
                        onClick={() => {
                            onCaughtUp(false);
                            focusOnceShown('catch-up-done');
                        }}
                        className="text-primary hover:text-primary"
                        data-test="catch-up-show-again"
                    >
                        Show again
                    </Button>
                    <ActionTooltip label="Hide">
                        <Button
                            variant="ghost"
                            size="icon"
                            onClick={() => setIsHidden(true)}
                            aria-label="Hide caught up"
                            className="-mr-2 size-8 text-muted-foreground"
                            data-test="catch-up-hide"
                        >
                            <X className="size-4" />
                        </Button>
                    </ActionTooltip>
                </div>
            </section>
        );
    }

    return (
        <section aria-labelledby="catch-up" className="flex">
            <RailSegment
                lane={stagedLane}
                isDashed
                className={cn(stagedLane && stagedRailTransition)}
            />
            <div className="min-w-0 flex-1 border-b pt-4 pr-4 pb-3">
                <h2
                    id="catch-up"
                    className="truncate font-mono text-[13px] text-muted-foreground"
                >
                    {`$ git log --since="${since}"`}
                </h2>
                <p className="mt-1 text-[15px] text-pretty">
                    While you were away,{' '}
                    <span className="font-mono tabular-nums">
                        {formatExactCount(catchUp.commits_count)}
                    </span>{' '}
                    commits came in from{' '}
                    <span className="font-mono tabular-nums">
                        {formatExactCount(catchUp.authors_count)}
                    </span>{' '}
                    {catchUp.authors_count === 1 ? 'person' : 'people'} you
                    follow. The most starred:
                </p>
                <ol
                    id="catch-up-commits"
                    className="mt-3 divide-y overflow-hidden rounded-lg border"
                >
                    {catchUp.commits
                        .slice(0, isExpanded ? undefined : previewCount)
                        .map((commit) => (
                            <li key={commit.id}>
                                <Link
                                    href={CommitController.show({
                                        user: commit.user,
                                        commit,
                                    })}
                                    className="flex items-start gap-3 p-3 transition-colors hover:bg-accent focus-visible:bg-accent focus-visible:outline-none"
                                    data-test="catch-up-commit"
                                >
                                    <CommitResult commit={commit} />
                                    <span
                                        className="flex shrink-0 items-center gap-1 font-mono text-[13px] font-medium text-muted-foreground tabular-nums"
                                        title={`${formatExactCount(commit.stars_count)} ${commit.stars_count === 1 ? 'star' : 'stars'}`}
                                    >
                                        <Star aria-hidden className="size-4" />
                                        {formatCount(commit.stars_count)}
                                        <span className="sr-only">
                                            {commit.stars_count === 1
                                                ? ' star'
                                                : ' stars'}
                                        </span>
                                    </span>
                                </Link>
                            </li>
                        ))}
                </ol>
                <div className="mt-3 flex items-center gap-3">
                    {moreCount > 0 && (
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => setIsExpanded(!isExpanded)}
                            aria-expanded={isExpanded}
                            aria-controls="catch-up-commits"
                            className="-ml-3 text-primary hover:text-primary"
                            data-test="catch-up-more"
                        >
                            {isExpanded
                                ? 'Show fewer'
                                : `Show ${moreCount} more`}
                        </Button>
                    )}
                    <Button
                        id="catch-up-done"
                        variant="outline"
                        size="sm"
                        onClick={() => {
                            onCaughtUp(true);
                            focusOnceShown('catch-up-show-again');
                        }}
                        className="ml-auto"
                        data-test="catch-up-done"
                    >
                        <Check />
                        I'm caught up
                    </Button>
                </div>
            </div>
        </section>
    );
}
