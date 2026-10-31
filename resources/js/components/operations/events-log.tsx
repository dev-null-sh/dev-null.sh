import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { useEffect, useLayoutEffect, useRef, useState } from 'react';
import UserController from '@/actions/App/Http/Controllers/UserController';
import { useDateTime } from '@/hooks/use-date-time';
import { useNow } from '@/hooks/use-now';
import { cn, localDayKey } from '@/lib/utils';
import type { OperationsEvent, OperationsEventLevel } from '@/types';

export const LEVEL_DOTS: Record<OperationsEventLevel, string> = {
    info: 'bg-muted-foreground/40',
    notice: 'bg-muted-foreground',
    warning: 'bg-warning',
    error: 'bg-destructive',
};

const FRESH_MILLISECONDS = 2500;

type Anchor = { id: string; top: number };

/**
 * The first row whose top edge is on screen, and where it sits, so the log can be put back under the reader after
 * rows arrive above it. Nothing is anchored while the log is still below the fold.
 */
function firstVisibleRow(list: HTMLElement | null): Anchor | null {
    for (const row of list?.querySelectorAll<HTMLElement>('[data-event-id]') ??
        []) {
        const top = row.getBoundingClientRect().top;

        if (top >= window.innerHeight) {
            return null;
        }

        if (top >= 0) {
            return { id: row.dataset.eventId ?? '', top };
        }
    }

    return null;
}

export function LevelBadge({ level }: { level: OperationsEventLevel }) {
    return (
        <span
            className={cn(
                'inline-flex h-5 items-center gap-1 justify-self-start rounded-sm border px-1.5 font-mono text-xs',
                level === 'info' || level === 'notice'
                    ? 'text-muted-foreground'
                    : 'text-foreground',
            )}
        >
            <span
                aria-hidden
                className={cn('size-1.5 rounded-full', LEVEL_DOTS[level])}
            />
            {level}
        </span>
    );
}

function EventRow({
    event,
    isFresh,
    isExpanded,
    onToggle,
}: {
    event: OperationsEvent;
    isFresh: boolean;
    isExpanded: boolean;
    onToggle: () => void;
}) {
    const { formatDate, formatTime } = useDateTime();
    const profile =
        event.actor === null ? null : UserController.show(event.actor).url;
    const isProfile =
        profile !== null &&
        event.href?.replace(/^https?:\/\/[^/]+/, '') === profile;

    return (
        <li
            data-event-id={event.id}
            className={cn(
                // Reserve 11ch for seconds and AM/PM so event columns stay aligned.
                'grid grid-cols-[auto_auto_minmax(0,1fr)_auto] items-start gap-x-3 gap-y-0.5 px-4 py-2 font-mono text-[13px] leading-5 transition-colors duration-1000 motion-reduce:transition-none sm:grid-cols-[minmax(11ch,max-content)_10ch_10ch_minmax(0,1fr)_auto]',
                isFresh && 'bg-accent duration-0',
            )}
        >
            <time
                dateTime={event.at}
                title={`${formatTime(event.at, undefined, { second: '2-digit', timeZoneName: 'short' })} · ${formatDate(event.at, { weekday: true, year: true })}`}
                className="whitespace-nowrap text-muted-foreground tabular-nums"
            >
                {formatTime(event.at, undefined, { second: '2-digit' })}
            </time>
            <LevelBadge level={event.level} />
            <span
                className="truncate text-muted-foreground"
                title={event.source}
            >
                {event.source}
            </span>
            <div className="col-span-full row-start-2 min-w-0 sm:col-span-1 sm:col-start-4 sm:row-start-1">
                <p className="font-sans text-sm leading-5 [overflow-wrap:anywhere]">
                    {event.message}
                    {event.actor !== null && (
                        <>
                            {' '}
                            {isProfile ? (
                                <Link
                                    href={profile}
                                    className="font-mono text-[13px] underline-offset-4 hover:underline"
                                >
                                    @{event.actor}
                                </Link>
                            ) : (
                                <span className="font-mono text-[13px] text-muted-foreground">
                                    @{event.actor}
                                </span>
                            )}
                        </>
                    )}
                </p>
                {event.detail !== null && (
                    <button
                        type="button"
                        aria-expanded={isExpanded}
                        onClick={onToggle}
                        title={isExpanded ? undefined : event.detail}
                        className="-mt-0.5 -mb-1 flex w-full items-start gap-1 rounded-sm py-1 text-left text-xs leading-5 text-muted-foreground outline-none hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    >
                        <ChevronRight
                            aria-hidden
                            className={cn(
                                'mt-1 size-3 shrink-0 transition-transform motion-reduce:transition-none',
                                isExpanded && 'rotate-90',
                            )}
                        />
                        <span
                            className={cn(
                                'min-w-0 flex-1',
                                isExpanded
                                    ? '[overflow-wrap:anywhere] whitespace-pre-wrap'
                                    : 'truncate',
                            )}
                        >
                            {event.detail}
                        </span>
                    </button>
                )}
            </div>
            {event.href !== null && !isProfile && (
                <Link
                    href={event.href}
                    className="col-start-4 row-start-1 -mx-1 -my-1 rounded-sm px-1 py-1 text-primary underline-offset-4 hover:underline sm:col-start-5"
                    aria-label={`Open: ${event.message}`}
                >
                    open
                </Link>
            )}
        </li>
    );
}

/**
 * The log, newest first, split by local day. Rows that arrive while it is open are washed for a moment, and when they
 * land above the reader the page scrolls by their height so the rows being read stay where they are. Key it by the
 * filters, so other results replace the rows instead of moving them.
 */
export function EventsLog({ events }: { events: OperationsEvent[] }) {
    const { formatDayLabel } = useDateTime();
    const now = useNow();
    const listRef = useRef<HTMLDivElement>(null);
    const anchor = useRef<Anchor | null>(null);
    const [expanded, setExpanded] = useState<ReadonlySet<string>>(new Set());
    const [shown, setShown] = useState<{
        events: OperationsEvent[];
        fresh: ReadonlySet<string>;
    }>({ events, fresh: new Set() });

    if (shown.events !== events) {
        const known = new Set(shown.events.map((event) => event.id));
        const newest = shown.events[0]?.at;

        setShown({
            events,
            fresh: new Set(
                events
                    .filter(
                        (event) =>
                            !known.has(event.id) &&
                            (newest === undefined ||
                                Date.parse(event.at) >= Date.parse(newest)),
                    )
                    .map((event) => event.id),
            ),
        });
    }

    useEffect(() => {
        if (shown.fresh.size === 0) {
            return;
        }

        const timeout = window.setTimeout(
            () => setShown((current) => ({ ...current, fresh: new Set() })),
            FRESH_MILLISECONDS,
        );

        return () => window.clearTimeout(timeout);
    }, [shown.fresh]);

    useEffect(() => {
        let frame = 0;
        const capture = () => {
            window.cancelAnimationFrame(frame);
            frame = window.requestAnimationFrame(() => {
                anchor.current = firstVisibleRow(listRef.current);
            });
        };

        capture();
        window.addEventListener('scroll', capture, { passive: true });
        window.addEventListener('resize', capture);

        return () => {
            window.cancelAnimationFrame(frame);
            window.removeEventListener('scroll', capture);
            window.removeEventListener('resize', capture);
        };
    }, []);

    useLayoutEffect(() => {
        const previous = anchor.current;
        const row =
            previous === null
                ? null
                : listRef.current?.querySelector<HTMLElement>(
                      `[data-event-id="${CSS.escape(previous.id)}"]`,
                  );

        if (previous !== null && row) {
            const shift = row.getBoundingClientRect().top - previous.top;

            if (shift !== 0) {
                window.scrollBy(0, shift);
            }
        }

        anchor.current = firstVisibleRow(listRef.current);
    }, [events]);

    useLayoutEffect(() => {
        anchor.current = firstVisibleRow(listRef.current);
    }, [expanded]);

    const days = events.reduce<
        { key: string; label: string; events: OperationsEvent[] }[]
    >((groups, event) => {
        const key = localDayKey(event.at);
        const last = groups.at(-1);

        if (last?.key === key) {
            last.events.push(event);
        } else {
            groups.push({
                key,
                label: formatDayLabel(event.at, now),
                events: [event],
            });
        }

        return groups;
    }, []);

    const toggle = (id: string) => {
        const next = new Set(expanded);

        if (!next.delete(id)) {
            next.add(id);
        }

        setExpanded(next);
    };

    return (
        <div ref={listRef}>
            {days.map((day) => (
                <section
                    key={day.key}
                    aria-label={day.label}
                    className="border-b last:border-b-0"
                >
                    <h3 className="border-b bg-background/60 px-4 py-1.5 font-mono text-xs text-muted-foreground">
                        {day.label}
                    </h3>
                    <ol className="divide-y">
                        {day.events.map((event) => (
                            <EventRow
                                key={event.id}
                                event={event}
                                isFresh={shown.fresh.has(event.id)}
                                isExpanded={expanded.has(event.id)}
                                onToggle={() => toggle(event.id)}
                            />
                        ))}
                    </ol>
                </section>
            ))}
        </div>
    );
}
