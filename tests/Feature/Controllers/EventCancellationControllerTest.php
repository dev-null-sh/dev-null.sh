<?php

declare(strict_types=1);

use App\Enums\EventState;
use App\Enums\NotificationType;
use App\Enums\OrganizationRole;
use App\Models\Commit;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\Notification;
use App\Models\Organization;
use App\Models\User;

it('lets the host cancel an event', function (Closure $event): void {
    $event = $event();

    $this->actingAs($event->commit->user)
        ->from(route('event.show', $event))
        ->post(route('event-cancellation.store', $event))
        ->assertRedirect(route('event.show', $event));

    expect($event->refresh()->state())->toBe(EventState::Cancelled);
})->with([
    'upcoming' => fn (): Event => Event::factory()->create(),
    'live' => fn (): Event => Event::factory()->live()->create(),
]);

it('lets a member cancel their organization\'s event, which tells the people going it was the organization', function (): void {
    $organization = Organization::factory()->create();
    $member = User::factory()->create();
    $organization->members()->attach($member->id, ['role' => OrganizationRole::Member]);
    $event = Event::factory()->for(Commit::factory()->state(['author_id' => $organization->id]))->create();
    $attendee = EventAttendee::factory()->for($event)->create();

    $this->actingAs($member)
        ->post(route('event-cancellation.store', $event))
        ->assertRedirect();

    expect($event->refresh()->state())->toBe(EventState::Cancelled)
        ->and(Notification::query()->where('recipient_id', $attendee->user_id)->sole())
        ->actor_id->toBe($organization->id)
        ->type->toBe(NotificationType::EventCancelled);
});

it('lets nobody else cancel an event', function (): void {
    $event = Event::factory()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('event-cancellation.store', $event))
        ->assertNotFound();

    expect($event->refresh()->cancelled_at)->toBeNull();
});

it('keeps an event that is over as it was', function (string $state): void {
    $event = Event::factory()->{$state}()->create();
    $cancelledAt = $event->cancelled_at;

    $this->actingAs($event->commit->user)
        ->post(route('event-cancellation.store', $event))
        ->assertForbidden();

    expect($event->refresh()->cancelled_at?->toIso8601String())->toBe($cancelledAt?->toIso8601String());
})->with(['ended', 'cancelled']);

it('tells the people going, and only them, that the event is cancelled', function (): void {
    $event = Event::factory()->create();
    $going = EventAttendee::factory()->for($event)->create();
    EventAttendee::factory()->for($event)->interested()->create();

    $this->actingAs($event->commit->user)->post(route('event-cancellation.store', $event));

    expect(Notification::query()->sole())
        ->recipient_id->toBe($going->user_id)
        ->actor_id->toBe($event->commit->user_id)
        ->type->toBe(NotificationType::EventCancelled)
        ->commit_id->toBe($event->commit_id)
        ->key->toBe('event_cancelled:'.$event->id);
});

it('tells everyone going at a large event, past one chunk of notifications', function (): void {
    $event = Event::factory()->create();
    EventAttendee::factory()->for($event)->count(501)->create();

    $this->actingAs($event->commit->user)->post(route('event-cancellation.store', $event));

    expect(Notification::query()->where('type', NotificationType::EventCancelled)->count())->toBe(501);
});

it('tells no one when an event is over', function (): void {
    $event = Event::factory()->ended()->create();
    EventAttendee::factory()->for($event)->create();

    $this->actingAs($event->commit->user)->post(route('event-cancellation.store', $event));

    expect(Notification::query()->count())->toBe(0);
});
