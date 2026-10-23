<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateEvent;
use App\Enums\CommitStatus;
use App\Enums\EventResponse;
use App\Http\Requests\CreateEventRequest;
use App\Http\Requests\ListEventsRequest;
use App\Http\Requests\ShowEventRequest;
use App\Models\Commit;
use App\Models\Event;
use App\Models\User;
use App\Queries\CommitPageQuery;
use App\Queries\CommitRepliesQuery;
use App\Queries\EventAttendeesQuery;
use App\Queries\EventsQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final readonly class EventController
{
    /**
     * Events are public, so guests read the same list as everyone.
     */
    public function index(ListEventsRequest $request, #[CurrentUser] ?User $currentUser, EventsQuery $events): Response
    {
        return Inertia::render('event/index', [
            'tab' => $request->tab(...),
            'events' => Inertia::scroll(fn (): CursorPaginator => $events->paginate($currentUser, past: $request->tab() === 'past')),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('event/create', [
            'limits' => [
                'title' => Event::MAX_TITLE_LENGTH,
                'body' => Commit::MAX_BODY_LENGTH,
                'location' => Event::MAX_LOCATION_LENGTH,
                'url' => Event::MAX_URL_LENGTH,
                'agendaItems' => Event::MAX_AGENDA_ITEMS,
                'agendaLabel' => Event::MAX_AGENDA_LABEL_LENGTH,
                'durationDays' => Event::MAX_DURATION_DAYS,
            ],
        ]);
    }

    public function store(CreateEventRequest $request, #[CurrentUser] User $user, CreateEvent $action): RedirectResponse
    {
        $event = $action->handle($user, $request->string('body')->value(), $request->newEvent());

        if ($event->commit?->status === CommitStatus::Pending) {
            Inertia::flash('toast', [
                'type' => 'info',
                'message' => 'Your event is pending review. Everyone will see it once it is approved.',
            ]);
        }

        return to_route('event.show', $event);
    }

    /**
     * The event reads like an issue: its details on top, and the commit announcing it, with its replies, as the discussion.
     */
    public function show(ShowEventRequest $request, #[CurrentUser] ?User $currentUser, Event $event, CommitPageQuery $page, CommitRepliesQuery $replies, EventAttendeesQuery $attendees): Response
    {
        $commit = $event->commit;
        assert($commit instanceof Commit);

        return Inertia::render('event/show', [
            'event' => fn (): Event => $event->load('agendaItems')->loadCount(['attendees as going_count' => fn (Builder $query): Builder => $query->where('response', EventResponse::Going)]),
            'host' => fn (): ?User => $commit->user,
            'response' => fn (): ?string => $event->responseOf($currentUser)?->value,
            'attendees' => fn (): Collection => $attendees->get($event, $currentUser),
            'attendeesLimit' => EventAttendeesQuery::LIMIT,
            'commit' => fn (): Commit => $page->get($commit, $currentUser),
            'replies' => fn (): CursorPaginator => $replies->paginate($commit, $currentUser, limit: $request->repliesPerPage()),
        ]);
    }
}
