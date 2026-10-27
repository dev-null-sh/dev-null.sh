<?php

declare(strict_types=1);

use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\Event;
use App\Models\EventAgendaItem;
use App\Models\EventAttendee;
use App\Models\User;
use App\Models\UserTimeout;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @return array<string, mixed>
 */
function newEvent(array $overrides = []): array
{
    $startsAt = CarbonImmutable::now()->addWeek()->startOfHour();

    return [
        'title' => 'Laravel Lisbon #12',
        'body' => 'Talks, pastéis and a lot of PHP.',
        'starts_at' => $startsAt->toIso8601String(),
        'ends_at' => $startsAt->addHours(3)->toIso8601String(),
        'location' => 'Lisbon',
        'url' => null,
        'agenda' => [
            ['label' => 'Doors open', 'starts_at' => $startsAt->toIso8601String()],
            ['label' => 'Octane in production', 'starts_at' => $startsAt->addHour()->toIso8601String()],
        ],
        ...$overrides,
    ];
}

it('lists upcoming events soonest first to guests', function (): void {
    $later = Event::factory()->create(['starts_at' => now()->addWeeks(2), 'ends_at' => now()->addWeeks(2)->addHour()]);
    $sooner = Event::factory()->create();
    $live = Event::factory()->live()->create();
    Event::factory()->ended()->create();
    Event::factory()->cancelled()->create();
    Event::factory()->for(Commit::factory()->pending())->create();
    EventAttendee::factory()->for($sooner)->count(2)->create();
    EventAttendee::factory()->for($sooner)->interested()->create();

    $this->get(route('event.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('event/index')
            ->where('tab', 'upcoming')
            ->has('events.data', 3)
            ->where('events.data.0.id', $live->id)
            ->where('events.data.0.state', 'live')
            ->where('events.data.1.id', $sooner->id)
            ->where('events.data.1.going_count', 2)
            ->where('events.data.1.host.handle', $sooner->commit->user->handle)
            ->missing('events.data.1.commit')
            ->where('events.data.2.id', $later->id));
});

it('lists past and cancelled events latest first', function (): void {
    $ended = Event::factory()->ended()->create();
    $cancelled = Event::factory()->cancelled()->create();
    Event::factory()->create();

    $this->get(route('event.index', ['tab' => 'past']))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('tab', 'past')
            ->has('events.data', 2)
            ->where('events.data.0.id', $cancelled->id)
            ->where('events.data.0.state', 'cancelled')
            ->where('events.data.1.id', $ended->id));
});

it('leaves muted and blocked hosts out of the list', function (): void {
    $user = User::factory()->create();
    $muted = Event::factory()->create();
    $blocked = Event::factory()->create();
    $kept = Event::factory()->create();
    $user->muting()->attach($muted->commit->user_id);
    $user->blocking()->attach($blocked->commit->user_id);

    $this->actingAs($user)
        ->get(route('event.index'))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->has('events.data', 1)
            ->where('events.data.0.id', $kept->id));
});

it('shows an event like an issue, with its agenda, people going and discussion', function (): void {
    $viewer = User::factory()->create();
    $event = Event::factory()->create();
    EventAgendaItem::factory()->for($event)->create(['label' => 'Second', 'starts_at' => $event->starts_at->addHour()]);
    EventAgendaItem::factory()->for($event)->create(['label' => 'First', 'starts_at' => $event->starts_at]);
    $first = EventAttendee::factory()->for($event)->create();
    $latest = EventAttendee::factory()->for($event)->create();
    $blocked = EventAttendee::factory()->for($event)->create();
    EventAttendee::factory()->for($event)->interested()->create();
    EventAttendee::factory()->for($event)->for($viewer)->interested()->create();
    $viewer->blocking()->attach($blocked->user_id);
    $reply = Commit::factory()->create(['parent_id' => $event->commit_id]);

    $this->actingAs($viewer)
        ->get(route('event.show', $event))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('event/show')
            ->where('event.id', $event->id)
            ->where('event.title', $event->title)
            ->where('event.state', 'upcoming')
            ->where('event.going_count', 3)
            ->where('event.agenda_items.0.label', 'First')
            ->where('event.agenda_items.1.label', 'Second')
            ->missing('event.created_at')
            ->where('host.id', $event->commit->user_id)
            ->where('response', 'interested')
            ->has('attendees', 2)
            ->where('attendees.0.id', $latest->user_id)
            ->where('attendees.1.id', $first->user_id)
            ->where('commit.id', $event->commit_id)
            ->where('commit.event.id', $event->id)
            ->where('replies.data.0.id', $reply->id));
});

it('shows events to guests', function (): void {
    $event = Event::factory()->create();

    $this->get(route('event.show', $event))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('response', null)
            ->has('attendees', 0));
});

it('shows a pending event only to its host', function (): void {
    $event = Event::factory()->for(Commit::factory()->pending())->create();

    $this->get(route('event.show', $event))->assertNotFound();
    $this->actingAs(User::factory()->create())->get(route('event.show', $event))->assertNotFound();
    $this->actingAs($event->commit->user)->get(route('event.show', $event))->assertOk();
});

it('hides events across a block and with their deleted commit', function (): void {
    $user = User::factory()->create();
    $blocked = Event::factory()->create();
    $user->blockers()->attach($blocked->commit->user_id);
    $deleted = Event::factory()->create();
    $deleted->commit->delete();

    $this->actingAs($user)->get(route('event.show', $blocked))->assertNotFound();
    $this->actingAs($user)->get(route('event.show', $deleted))->assertNotFound();
});

it('shows the form for a new event', function (): void {
    $this->actingAs(User::factory()->create())
        ->get(route('event.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('event/create')
            ->where('limits.title', Event::MAX_TITLE_LENGTH)
            ->where('limits.body', Commit::MAX_BODY_LENGTH)
            ->where('limits.agendaItems', Event::MAX_AGENDA_ITEMS));
});

it('sends guests to sign in before creating an event', function (): void {
    $this->get(route('event.create'))->assertRedirect(route('home'));
    $this->post(route('event.store'), newEvent())->assertRedirect(route('home'));
});

it('creates an event announced by a commit pending review', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('event.store'), newEvent(['title' => 'PHP night :rocket:']));

    $event = Event::query()->sole();

    $response->assertRedirect(route('event.show', $event))
        ->assertInertiaFlash('toast.message', 'Your event is pending review. Everyone will see it once it is approved.');

    expect($event)
        ->title->toBe('PHP night 🚀')
        ->location->toBe('Lisbon')
        ->url->toBeNull()
        ->and($event->commit)
        ->user_id->toBe($user->id)
        ->body->toBe('Talks, pastéis and a lot of PHP.')
        ->status->toBe(CommitStatus::Pending)
        ->and($event->agendaItems->pluck('label')->all())->toBe(['Doors open', 'Octane in production']);
});

it('creates an approved event without a toast for trusted authors', function (): void {
    $user = User::factory()->create(['created_at' => now()->subDay()]);
    Commit::factory()->for($user)->create();
    $user->followers()->attach(User::factory()->count(10)->create());

    $this->actingAs($user)
        ->post(route('event.store'), newEvent(['location' => null, 'url' => 'https://youtube.com/live/abc', 'agenda' => []]))
        ->assertInertiaFlashMissing('toast');

    expect(Event::query()->sole())
        ->url->toBe('https://youtube.com/live/abc')
        ->location->toBeNull()
        ->commit->status->toBe(CommitStatus::Approved);
});

it('validates new events', function (array $overrides, string $field, ?string $message = null): void {
    $response = $this->actingAs(User::factory()->create())
        ->post(route('event.store'), newEvent($overrides));

    $message === null
        ? $response->assertSessionHasErrors($field)
        : $response->assertSessionHasErrors([$field => $message]);

    expect(Event::query()->count())->toBe(0)
        ->and(Commit::query()->count())->toBe(0);
})->with([
    'no title' => [['title' => ''], 'title'],
    'long title' => [['title' => str_repeat('a', Event::MAX_TITLE_LENGTH + 1)], 'title'],
    'no description' => [['body' => ''], 'body'],
    'start in the past' => [['starts_at' => now()->subHour()->toIso8601String()], 'starts_at'],
    'start too far ahead' => [['starts_at' => now()->addYears(2)->toIso8601String(), 'ends_at' => now()->addYears(2)->addHour()->toIso8601String(), 'agenda' => []], 'starts_at'],
    'end before start' => [['ends_at' => now()->addDay()->toIso8601String()], 'ends_at'],
    'too long' => [['ends_at' => now()->addWeeks(3)->toIso8601String()], 'ends_at', 'An event lasts at most 7 days.'],
    'nowhere' => [['location' => null, 'url' => null], 'location', 'Add a place, a link, or both.'],
    'not a web link' => [['url' => 'ftp://example.com'], 'url'],
    'agenda outside the event' => [['agenda' => [['label' => 'Afterparty', 'starts_at' => now()->addMonth()->toIso8601String()]]], 'agenda.0.starts_at', 'Agenda items happen during the event.'],
    'agenda before the event' => [['agenda' => [['label' => 'Warmup', 'starts_at' => now()->addDay()->toIso8601String()]]], 'agenda.0.starts_at', 'Agenda items happen during the event.'],
    'agenda without a label' => [['agenda' => [['label' => '', 'starts_at' => now()->addWeek()->startOfHour()->toIso8601String()]]], 'agenda.0.label'],
    'too many agenda items' => [['agenda' => array_fill(0, Event::MAX_AGENDA_ITEMS + 1, ['label' => 'Talk', 'starts_at' => now()->addWeek()->startOfHour()->toIso8601String()])], 'agenda'],
]);

it('reports an invalid start on its own field only', function (): void {
    $this->actingAs(User::factory()->create())
        ->post(route('event.store'), newEvent(['starts_at' => 'soon', 'agenda' => []]))
        ->assertSessionHasErrors('starts_at')
        ->assertSessionDoesntHaveErrors('ends_at');
});

it('keeps users in a timeout from creating events', function (): void {
    $user = User::factory()->create();
    UserTimeout::factory()->for($user)->create();

    $this->actingAs($user)->post(route('event.store'), newEvent());

    expect(Event::query()->count())->toBe(0);
});
