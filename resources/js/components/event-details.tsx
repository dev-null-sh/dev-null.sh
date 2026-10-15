import { Link } from '@inertiajs/react';
import { ChevronUp, MapPin, Share, Video } from 'lucide-react';
import type { ReactNode } from 'react';
import { EventCalendarMenu } from '@/components/event-calendar-menu';
import { EventGoingButton } from '@/components/event-going-button';
import { Button } from '@/components/ui/button';
import { useCanShare } from '@/hooks/use-can-share';
import type { EventGoing } from '@/hooks/use-event-going';
import {
    eventDateParts,
    formatEventDate,
    formatEventTimeRange,
    mapUrl,
    relativeEventLabel,
    shareLink,
} from '@/lib/event';
import { cn } from '@/lib/utils';
import type { CommitEvent } from '@/types';

/**
 * The full event. Viewers who can go get a Going button through `going`; the host gets their actions through `hostActions`.
 */
export function EventDetails({
    event,
    link,
    isApproved,
    going,
    hostActions,
    titleHref,
    onCollapse,
}: {
    event: CommitEvent;
    link: string;
    isApproved: boolean;
    going?: EventGoing;
    hostActions?: ReactNode;
    titleHref?: string;
    onCollapse?: () => void;
}) {
    const date = eventDateParts(event.starts_at);
    const isOpen = event.state === 'upcoming' || event.state === 'live';
    const canShare = useCanShare();

    return (
        <section
            aria-label="Event"
            className="mt-3.5 overflow-hidden rounded-lg border"
            data-test="event-details"
        >
            {event.cover && (
                <img
                    src={event.cover.url}
                    alt=""
                    width={event.cover.width}
                    height={event.cover.height}
                    className="aspect-video w-full bg-muted object-cover"
                />
            )}

            <div className="relative px-4 pt-4">
                {onCollapse && (
                    <button
                        type="button"
                        aria-label="Hide event details"
                        onClick={onCollapse}
                        className="absolute top-3 right-3 rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                        data-test="event-collapse-button"
                    >
                        <ChevronUp className="size-4" />
                    </button>
                )}
                <p className="font-mono text-xs text-muted-foreground">
                    event ·{' '}
                    {event.state === 'live'
                        ? 'now'
                        : event.state === 'upcoming'
                          ? relativeEventLabel(event.starts_at)
                          : event.state}
                    {' · '}
                    <span className="font-medium text-foreground">
                        {event.attendees_count}
                    </span>{' '}
                    {event.state === 'ended' ? 'went' : 'going'}
                </p>
                <h2
                    className={cn(
                        'mt-0.5 text-[22px] leading-snug font-semibold tracking-tight',
                        event.state === 'cancelled' &&
                            'text-muted-foreground line-through',
                    )}
                >
                    {titleHref ? (
                        <Link href={titleHref} className="hover:underline">
                            {event.title}
                        </Link>
                    ) : (
                        event.title
                    )}
                </h2>
            </div>

            <dl className="flex flex-col gap-2.5 px-4 py-3">
                <div className="flex items-center gap-3">
                    <dt className="sr-only">When</dt>
                    <div className="w-11 shrink-0 overflow-hidden rounded-lg border text-center">
                        <div className="border-b font-mono text-[11px] leading-[18px] text-muted-foreground">
                            {date.month}
                        </div>
                        <div className="text-lg leading-[26px] font-semibold">
                            {date.day}
                        </div>
                    </div>
                    <dd>
                        <div className="font-medium">
                            {formatEventDate(event.starts_at)}
                        </div>
                        <div className="font-mono text-[13px] text-muted-foreground">
                            {formatEventTimeRange(
                                event.starts_at,
                                event.ends_at,
                            )}
                            {event.updated && (
                                <span title="The time or place changed">
                                    {' '}
                                    · updated
                                </span>
                            )}
                        </div>
                    </dd>
                </div>

                {event.format !== 'online' && (
                    <div className="flex items-center gap-3">
                        <dt className="sr-only">Where</dt>
                        <div className="flex size-11 shrink-0 items-center justify-center rounded-lg border text-muted-foreground">
                            <MapPin className="size-[18px]" />
                        </div>
                        <dd className="min-w-0">
                            <div className="font-medium">{event.venue}</div>
                            <div className="text-sm text-muted-foreground">
                                {event.address} ·{' '}
                                <a
                                    href={mapUrl(event.venue, event.address)}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="text-primary"
                                >
                                    Open in maps
                                </a>
                            </div>
                        </dd>
                    </div>
                )}

                {event.format !== 'onsite' && (
                    <div className="flex items-center gap-3">
                        <dt className="sr-only">Stream</dt>
                        <div className="flex size-11 shrink-0 items-center justify-center rounded-lg border text-muted-foreground">
                            <Video className="size-[18px]" />
                        </div>
                        <dd className="min-w-0">
                            <div className="font-medium">
                                {event.format === 'hybrid' ? 'Also on ' : ''}
                                {event.platform_label}
                            </div>
                            {event.join_url && (
                                <a
                                    href={event.join_url}
                                    target="_blank"
                                    rel="noopener noreferrer nofollow ugc"
                                    className="block truncate font-mono text-[13px] text-primary"
                                >
                                    {event.join_url.replace(/^https:\/\//, '')}
                                </a>
                            )}
                        </dd>
                    </div>
                )}
            </dl>

            {isOpen && !isApproved && (
                <div className="flex flex-wrap items-center gap-2 border-t px-4 py-3.5">
                    <span className="mr-auto text-[13px] text-muted-foreground">
                        Opens once approved
                    </span>
                    {hostActions}
                </div>
            )}

            {isOpen && isApproved && (
                <div className="flex flex-wrap items-center gap-2 border-t px-4 py-3.5">
                    {going && (
                        <EventGoingButton
                            going={going}
                            className="px-3 py-1.5 text-sm"
                        />
                    )}
                    {event.calendar && (
                        <EventCalendarMenu
                            startsAt={event.starts_at}
                            endsAt={event.ends_at}
                            calendar={event.calendar}
                        />
                    )}
                    {canShare && (
                        <Button
                            variant="outline"
                            size="icon"
                            aria-label="Share"
                            title="Share"
                            onClick={() => shareLink(link)}
                        >
                            <Share className="size-4" />
                        </Button>
                    )}
                    {hostActions}
                </div>
            )}
        </section>
    );
}
