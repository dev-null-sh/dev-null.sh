import { useState } from 'react';
import { EventCalendarMenu } from '@/components/event-calendar-menu';
import { EventDetails } from '@/components/event-details';
import { EventEditDialog } from '@/components/event-edit-dialog';
import { EventExpandable } from '@/components/event-expandable';
import { EventGoingButton } from '@/components/event-going-button';
import { EventTicket } from '@/components/event-ticket';
import { Button } from '@/components/ui/button';
import type { EventGoing } from '@/hooks/use-event-going';
import type { Commit, CommitEvent as Event } from '@/types';

/**
 * A commit's event: the full block on its own page, otherwise a ticket that opens in place.
 */
export function CommitEvent({
    commit,
    event,
    link,
    going,
    isOwn,
    isFocused,
    expanded,
    onExpandedChange,
}: {
    commit: Commit;
    event: Event;
    link: string;
    going?: EventGoing;
    isOwn: boolean;
    isFocused: boolean;
    expanded: boolean;
    onExpandedChange: (expanded: boolean) => void;
}) {
    const [isEditing, setIsEditing] = useState(false);

    const details = (
        <EventDetails
            event={event}
            link={link}
            isApproved={commit.status === 'approved'}
            going={going}
            titleHref={isFocused ? undefined : link}
            onCollapse={isFocused ? undefined : () => onExpandedChange(false)}
            hostActions={
                isOwn && (
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => setIsEditing(true)}
                    >
                        Edit event
                    </Button>
                )
            }
        />
    );

    return (
        <>
            {isFocused ? (
                details
            ) : (
                <EventExpandable
                    expanded={expanded}
                    onExpand={() => onExpandedChange(true)}
                    title={event.title}
                    ticket={<EventTicket event={event} />}
                    details={details}
                />
            )}
            {!isFocused && !expanded && commit.status === 'pending' && (
                <p className="mt-1.5 text-[13px] text-muted-foreground">
                    Opens once approved
                </p>
            )}
            {isOwn && (
                <EventEditDialog
                    key={`${event.id}-${event.starts_at}-${event.state}`}
                    commit={commit}
                    event={event}
                    open={isEditing}
                    onOpenChange={setIsEditing}
                />
            )}
        </>
    );
}

/**
 * Going and Add to calendar in the commit's action row, while the event is collapsed and open.
 */
export function CommitEventActions({
    event,
    going,
}: {
    event: Event;
    going?: EventGoing;
}) {
    return (
        <>
            {going && <EventGoingButton going={going} />}
            {event.calendar && (
                <EventCalendarMenu
                    variant="icon"
                    startsAt={event.starts_at}
                    endsAt={event.ends_at}
                    calendar={event.calendar}
                />
            )}
        </>
    );
}
