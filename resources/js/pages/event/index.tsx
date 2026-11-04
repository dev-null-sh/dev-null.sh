import { Head, Link, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import EventController from '@/actions/App/Http/Controllers/EventController';
import { EmptyFeed } from '@/components/commit-feed';
import { EventList } from '@/components/event-list';
import { PageTabs } from '@/components/page-tabs';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { login } from '@/routes';
import type { CursorPaginated, EventWithHost } from '@/types';

export default function Index({
    tab,
    events,
}: {
    tab: 'upcoming' | 'past';
    events: CursorPaginated<EventWithHost>;
}) {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Events" />

            <div className="sticky top-0 z-10 flex h-14 items-center justify-between gap-3 border-b bg-card px-4 sm:px-6">
                <h1 className="font-semibold">Events</h1>
                <Button size="sm" asChild>
                    <Link
                        href={
                            auth.user === null
                                ? login()
                                : EventController.create()
                        }
                        data-test="new-event"
                    >
                        <Plus />
                        New event
                    </Link>
                </Button>
            </div>

            <div className="min-h-dvh">
                <PageTabs
                    label="Events"
                    preserveScroll
                    only={['tab', 'events']}
                    reset={['events']}
                    tabs={[
                        {
                            title: 'Upcoming',
                            href: EventController.index(),
                            isActive: tab === 'upcoming',
                        },
                        {
                            title: 'Past',
                            href: EventController.index({
                                query: { tab: 'past' },
                            }),
                            isActive: tab === 'past',
                        },
                    ]}
                />

                <EventList
                    key={tab}
                    events={events}
                    data="events"
                    empty={
                        tab === 'upcoming' ? (
                            <EmptyFeed command="fatal: no upcoming events">
                                Nothing scheduled yet. Host a meetup, a stream
                                or a hackathon, and it shows up here.
                            </EmptyFeed>
                        ) : (
                            <EmptyFeed command="fatal: no past events">
                                Events that ended or were cancelled show up
                                here.
                            </EmptyFeed>
                        )
                    }
                />
            </div>
        </>
    );
}

Index.layout = AppLayout;
