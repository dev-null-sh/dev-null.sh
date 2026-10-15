<?php

declare(strict_types=1);

use App\Enums\EventState;
use App\Enums\NotificationType;
use App\Models\Commit;
use App\Models\Event;
use App\Models\Notification;
use App\Models\User;

it('lets the host cancel and tells people going once', function (): void {
    $event = Event::factory()->create();
    $attendee = User::factory()->create();
    $event->attendees()->attach($attendee);

    $this->actingAs($event->commit->user)->post(route('event-cancellation.store', $event->commit))->assertRedirect()->assertSessionHasNoErrors();

    $event->refresh();

    expect($event->state())->toBe(EventState::Cancelled)
        ->and($event->sequence)->toBe(1)
        ->and(Notification::query()->sole()->type)->toBe(NotificationType::EventCancelled);

    $this->actingAs($event->commit->user)->post(route('event-cancellation.store', $event->commit))
        ->assertForbidden();

    expect(Notification::query()->count())->toBeOne();
});

it('only lets the host cancel, and not after the event', function (): void {
    $event = Event::factory()->create();
    $this->actingAs(User::factory()->create())->post(route('event-cancellation.store', $event->commit))->assertForbidden();

    $ended = Event::factory()->past()->create();
    $this->actingAs($ended->commit->user)->post(route('event-cancellation.store', $ended->commit))
        ->assertForbidden();
});

it('is not found for commits without an event, even for their author', function (): void {
    $commit = Commit::factory()->create();

    $this->actingAs($commit->user)->post(route('event-cancellation.store', $commit))->assertNotFound();
});
