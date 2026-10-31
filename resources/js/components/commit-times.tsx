import { usePage } from '@inertiajs/react';
import { ArrowRight, Clock3 } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import {
    convertTimeMentions,
    findTimeMentions,
    type ConvertedTime,
    type TimeMention,
} from '@/lib/commit-times';
import {
    browserTimeZone,
    dateLocale,
    formatZonedTime,
    timeZoneCity,
} from '@/lib/utils';
import type { Commit } from '@/types';

export function CommitTimes({ commit }: { commit: Commit }) {
    const { auth } = usePage().props;
    const mentions = useMemo(
        () => findTimeMentions(commit.body_segments, commit.user.timezone),
        [commit.body_segments, commit.user.timezone],
    );

    if (mentions.length === 0) {
        return null;
    }

    return (
        <Popover>
            <PopoverTrigger asChild>
                <button
                    type="button"
                    aria-label="Show local times"
                    title="Local times"
                    className="rounded-md px-2 py-1 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring data-[state=open]:bg-accent data-[state=open]:text-foreground"
                    data-test="commit-times"
                >
                    <Clock3 className="size-4" aria-hidden />
                </button>
            </PopoverTrigger>
            <PopoverContent
                side="top"
                align="start"
                sideOffset={8}
                collisionPadding={12}
                aria-label="Local times"
                className="max-h-[min(20rem,var(--radix-popover-content-available-height))] w-fit max-w-[calc(100vw-1.5rem)] overflow-y-auto p-3 text-xs"
            >
                <div aria-live="polite">
                    <LocalTimes
                        key={JSON.stringify([
                            mentions,
                            commit.created_at,
                            auth.user?.timezone,
                        ])}
                        mentions={mentions}
                        postedAt={commit.created_at}
                        timeZone={auth.user?.timezone ?? browserTimeZone()}
                    />
                </div>
            </PopoverContent>
        </Popover>
    );
}

function LocalTimes({
    mentions,
    postedAt,
    timeZone,
}: {
    mentions: TimeMention[];
    postedAt: string;
    timeZone: string;
}) {
    const [times, setTimes] = useState<ConvertedTime[] | null>(null);
    const [hasFailed, setHasFailed] = useState(false);

    useEffect(() => {
        let active = true;

        void convertTimeMentions(mentions, postedAt).then(
            (converted) => {
                if (active) {
                    setTimes(converted);
                }
            },
            () => {
                if (active) {
                    setHasFailed(true);
                }
            },
        );

        return () => {
            active = false;
        };
    }, [mentions, postedAt]);

    if (hasFailed) {
        return (
            <p className="pb-1 text-muted-foreground">
                Could not convert these times. Close and reopen to try again.
            </p>
        );
    }

    if (times === null) {
        return <p className="pb-1 text-muted-foreground">Converting times…</p>;
    }

    return (
        <div className="space-y-2">
            <p className="font-medium" title={timeZone}>
                Your time · {timeZoneCity(timeZone)}
            </p>
            <dl className="space-y-1.5">
                {times.map((time, index) => (
                    <div
                        key={index}
                        className="flex flex-wrap items-baseline gap-x-2 gap-y-0.5"
                    >
                        <dt className="min-w-0 wrap-anywhere text-muted-foreground">
                            {time.text}
                            {time.inferred &&
                                ` (${timeZoneCity(time.timeZone)})`}
                        </dt>
                        <dd className="flex items-baseline gap-2">
                            <ArrowRight
                                className="size-3 self-center text-muted-foreground"
                                aria-hidden
                            />
                            {time.instant ? (
                                <time dateTime={time.instant}>
                                    <span className="font-medium tabular-nums">
                                        {formatZonedTime(
                                            time.instant,
                                            timeZone,
                                        )}
                                    </span>
                                    <span className="text-muted-foreground">
                                        {' · '}
                                        {new Date(
                                            time.instant,
                                        ).toLocaleDateString(dateLocale, {
                                            timeZone,
                                            month: 'short',
                                            day: 'numeric',
                                            year: 'numeric',
                                        })}
                                    </span>
                                </time>
                            ) : (
                                <span className="text-muted-foreground">
                                    Ambiguous or invalid on this date
                                </span>
                            )}
                        </dd>
                    </div>
                ))}
            </dl>
        </div>
    );
}
