import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    CalendarDays,
    Circle,
    CircleCheck,
    CircleDot,
    Globe,
    Link2,
    MapPin,
    Tag,
    Users,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import EventCancellationController from '@/actions/App/Http/Controllers/EventCancellationController';
import EventController from '@/actions/App/Http/Controllers/EventController';
import { show as userShow } from '@/actions/App/Http/Controllers/UserController';
import { CommitCard } from '@/components/commit-card';
import { EventResponseButton } from '@/components/event-response-button';
import { EventStateBadge } from '@/components/event-state-badge';
import { GraphCanvas } from '@/components/graph-canvas';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { UserAvatar } from '@/components/user-avatar';
import { useNow } from '@/hooks/use-now';
import AppLayout from '@/layouts/app-layout';
import {
    agendaProgress,
    eventFormat,
    eventPlace,
    eventStateAt,
    timeZoneLabel,
} from '@/lib/event';
import { formatEventTime, formatEventWhen } from '@/lib/event-dates';
import { cn, formatRelativeTime, shortHash } from '@/lib/utils';
import type {
    Commit,
    CursorPaginated,
    Event,
    EventAgendaItem,
    EventResponse,
    ThreadCommit,
    User,
} from '@/types';

export default function Show({
    event,
    host,
    response,
    attendees,
    attendeesLimit,
    commit,
    replies,
}: {
    event: Event & { going_count: number; agenda_items: EventAgendaItem[] };
    host: User | null;
    response: EventResponse | null;
    attendees: User[];
    attendeesLimit: number;
    commit: Commit;
    replies: CursorPaginated<ThreadCommit>;
}) {
    const { auth } = usePage().props;
    const now = useNow();
    const timeZone = auth.user?.timezone ?? undefined;
    const place = eventPlace(event);
    const state = eventStateAt(event, now);
    const isHost = auth.user !== null && host?.id === auth.user.id;
    const isOpen = state === 'upcoming' || state === 'live';

    const goBack = () => {
        if (window.history.length > 1) {
            window.history.back();

            return;
        }

        router.visit(EventController.index());
    };

    return (
        <>
            <Head title={event.title} />

            <div className="sticky top-0 z-10 flex h-14 items-center gap-3 border-b bg-card px-4">
                <button
                    type="button"
                    onClick={goBack}
                    aria-label="Back"
                    className="text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft className="size-4" />
                </button>
                <p className="font-semibold">Event</p>
                <p className="ml-auto shrink-0 font-mono text-[13px] text-muted-foreground">
                    {shortHash(event.id)}
                </p>
            </div>

            <header className="border-b px-4 pt-5 pb-4 sm:px-6">
                <h1 className="text-2xl font-semibold tracking-tight text-balance wrap-break-word">
                    {event.title}{' '}
                    <span className="font-mono text-xl font-normal text-muted-foreground">
                        #{shortHash(event.id)}
                    </span>
                </h1>
                <div className="mt-3 flex flex-wrap items-center gap-x-2 gap-y-1.5 text-sm text-muted-foreground">
                    <EventStateBadge state={state} />
                    {host && (
                        <span className="min-w-0">
                            <Link
                                href={userShow(host)}
                                className="font-semibold text-foreground hover:underline"
                            >
                                @{host.username}
                            </Link>{' '}
                            {state === 'cancelled'
                                ? 'cancelled this event'
                                : 'is hosting'}{' '}
                            ·{' '}
                            <time
                                dateTime={
                                    event.cancelled_at ?? commit.created_at
                                }
                                className="font-mono text-[13px]"
                            >
                                {formatRelativeTime(
                                    event.cancelled_at ?? commit.created_at,
                                    now,
                                )}
                            </time>{' '}
                            · {event.going_count} going
                        </span>
                    )}
                </div>

                <div className="mt-4 flex flex-wrap items-center gap-2">
                    <EventResponseButton
                        eventId={event.id}
                        response={response}
                        goingCount={event.going_count}
                        isHost={isHost}
                        isOpen={isOpen}
                    />
                    {isHost && isOpen && <CancelEventButton event={event} />}
                </div>
            </header>

            <dl className="divide-y border-b text-sm">
                <Detail icon={CalendarDays} term="When">
                    <span className="font-mono text-[13px]">
                        {formatEventWhen(event, timeZone)} ·{' '}
                        {timeZoneLabel(new Date(event.starts_at), timeZone)}
                    </span>
                </Detail>
                {place !== null && (
                    <Detail icon={MapPin} term="Where">
                        <a
                            href={`https://www.openstreetmap.org/search?query=${encodeURIComponent(place)}`}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="wrap-break-word text-primary hover:underline"
                        >
                            {place}
                        </a>
                    </Detail>
                )}
                {event.url !== null && (
                    <Detail icon={Link2} term="Link">
                        <a
                            href={event.url}
                            target="_blank"
                            rel="noopener noreferrer nofollow ugc"
                            className="block truncate text-primary hover:underline"
                            title={event.url}
                        >
                            {event.url.replace(/^https?:\/\//u, '')}
                        </a>
                    </Detail>
                )}
                <Detail icon={Tag} term="Labels">
                    <span className="flex flex-wrap gap-1.5">
                        <span className="inline-flex items-center gap-1 rounded-md border px-2 py-0.5 text-xs font-medium">
                            {eventFormat(event) === 'Online' ? (
                                <Globe className="size-3" />
                            ) : (
                                <MapPin className="size-3" />
                            )}
                            {eventFormat(event)}
                        </span>
                        {event.agenda_items.length > 0 && (
                            <span className="inline-flex items-center rounded-md border px-2 py-0.5 text-xs font-medium">
                                {event.agenda_items.length}{' '}
                                {event.agenda_items.length === 1
                                    ? 'talk'
                                    : 'talks'}
                            </span>
                        )}
                    </span>
                </Detail>
                <Detail icon={Users} term="Going" id="going">
                    {attendees.length === 0 ? (
                        <span className="text-muted-foreground">
                            No one yet
                        </span>
                    ) : (
                        <ul className="flex flex-wrap items-center gap-1">
                            {attendees.map((attendee) => (
                                <AttendeeAvatar
                                    key={attendee.id}
                                    user={attendee}
                                />
                            ))}
                            {/* Only once the avatars are full, so it never stands for someone left out across a block. */}
                            {attendees.length >= attendeesLimit &&
                                event.going_count > attendees.length && (
                                    <li className="ml-1 font-mono text-[13px] text-muted-foreground">
                                        +{event.going_count - attendees.length}
                                    </li>
                                )}
                        </ul>
                    )}
                </Detail>
            </dl>

            {event.agenda_items.length > 0 && (
                <Agenda event={event} items={event.agenda_items} now={now} />
            )}

            <h2 className="border-b px-4 py-3 text-sm font-semibold sm:px-6">
                Discussion
            </h2>
            <GraphCanvas>
                <CommitCard
                    key={commit.id}
                    commit={commit}
                    initialReplies={replies}
                    endsWithDot
                />
            </GraphCanvas>
        </>
    );
}

function Detail({
    icon: Icon,
    term,
    id,
    children,
}: {
    icon: typeof CalendarDays;
    term: string;
    id?: string;
    children: ReactNode;
}) {
    return (
        <div id={id} className="flex gap-3 px-4 py-3 sm:px-6">
            <dt className="flex w-20 shrink-0 items-center gap-1.5 self-start text-muted-foreground">
                <Icon className="size-4" />
                {term}
            </dt>
            <dd className="min-w-0 flex-1">{children}</dd>
        </div>
    );
}

function AttendeeAvatar({ user }: { user: User }) {
    return (
        <li>
            <Link
                href={userShow(user)}
                title={`@${user.username}`}
                className="block rounded-full"
            >
                <UserAvatar user={user} className="size-7" pixels={56} />
            </Link>
        </li>
    );
}

/**
 * The agenda reads like a milestone: a progress bar over its talks, ticked off as the next one starts.
 */
function Agenda({
    event,
    items,
    now,
}: {
    event: Event;
    items: EventAgendaItem[];
    now: Date;
}) {
    const { auth } = usePage().props;
    const timeZone = auth.user?.timezone ?? undefined;
    const isCancelled = event.cancelled_at !== null;
    const { done, current } = isCancelled
        ? { done: 0, current: null }
        : agendaProgress(items, event, now);
    const percent = Math.round((done / items.length) * 100);

    return (
        <section className="border-b px-4 py-4 sm:px-6" aria-label="Agenda">
            <div className="flex items-baseline justify-between gap-3">
                <h2 className="text-sm font-semibold">Agenda</h2>
                <p className="font-mono text-[13px] text-muted-foreground tabular-nums">
                    {done} of {items.length} done · {percent}%
                </p>
            </div>
            <div
                className="mt-2 h-2 overflow-hidden rounded-full bg-muted"
                role="progressbar"
                aria-valuemin={0}
                aria-valuemax={items.length}
                aria-valuenow={done}
                aria-label="Agenda progress"
            >
                <div
                    className="h-full rounded-full bg-emerald-600 transition-[width] dark:bg-emerald-500"
                    style={{ width: `${percent}%` }}
                />
            </div>
            <ol className="mt-3 space-y-2">
                {items.map((item, index) => {
                    const isDone = !isCancelled && index < done;
                    const isCurrent = item.id === current;
                    const Icon = isDone
                        ? CircleCheck
                        : isCurrent
                          ? CircleDot
                          : Circle;

                    return (
                        <li
                            key={item.id}
                            className="flex items-start gap-2.5 text-[15px]"
                            data-test="agenda-item"
                        >
                            <Icon
                                className={cn(
                                    'mt-0.5 size-4 shrink-0',
                                    isDone
                                        ? 'text-violet-600 dark:text-violet-400'
                                        : isCurrent
                                          ? 'text-emerald-600 dark:text-emerald-400'
                                          : 'text-muted-foreground',
                                )}
                            />
                            <span className="w-20 shrink-0 font-mono text-[13px] leading-6 text-muted-foreground tabular-nums">
                                {formatEventTime(item.starts_at, timeZone)}
                            </span>
                            <span
                                className={cn(
                                    'min-w-0 wrap-break-word',
                                    isDone && 'text-muted-foreground',
                                )}
                            >
                                {item.label}
                                {isCurrent && (
                                    <span className="ml-2 rounded-md bg-emerald-600/10 px-1.5 py-0.5 text-xs font-medium text-emerald-700 dark:text-emerald-400">
                                        Now
                                    </span>
                                )}
                            </span>
                        </li>
                    );
                })}
            </ol>
        </section>
    );
}

/**
 * Cancelling is final, like closing an issue as not planned, so it asks first.
 */
function CancelEventButton({ event }: { event: Event }) {
    const [isOpen, setIsOpen] = useState(false);
    const [isSaving, setIsSaving] = useState(false);

    const cancel = () => {
        router.post(
            EventCancellationController.store(event.id),
            {},
            {
                preserveScroll: true,
                onStart: () => setIsSaving(true),
                onFinish: () => {
                    setIsSaving(false);
                    setIsOpen(false);
                },
            },
        );
    };

    return (
        <>
            <Button
                variant="outline"
                onClick={() => setIsOpen(true)}
                data-test="cancel-event"
            >
                Cancel event
            </Button>
            <Dialog open={isOpen} onOpenChange={setIsOpen}>
                <DialogContent>
                    <DialogTitle>Cancel “{event.title}”?</DialogTitle>
                    <DialogDescription>
                        The event stays here, marked as cancelled, with its
                        discussion. It can't be reopened.
                    </DialogDescription>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button variant="outline">Keep it</Button>
                        </DialogClose>
                        <Button
                            variant="destructive"
                            onClick={cancel}
                            disabled={isSaving}
                            data-test="confirm-cancel-event"
                        >
                            Cancel event
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

Show.layout = AppLayout;
