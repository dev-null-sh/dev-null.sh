<?php

declare(strict_types=1);

use App\Enums\Log;
use App\Models\Commit;
use App\Models\Event;
use App\Models\User;

it('may go to an event', function (): void {
    $user = User::factory()->create();
    $event = Event::factory()->create();

    $this->actingAs($user)
        ->fromRoute('log.show', Log::Latest)
        ->post(route('event-attendance.store', $event->commit))
        ->assertRedirectToRoute('log.show', Log::Latest);

    expect($event->attendees()->whereKey($user->id)->exists())->toBeTrue();
});

it('going twice is idempotent', function (): void {
    $user = User::factory()->create();
    $event = Event::factory()->create();

    $this->actingAs($user)->post(route('event-attendance.store', $event->commit))->assertRedirect();
    $this->actingAs($user)->post(route('event-attendance.store', $event->commit))->assertRedirect();

    expect($event->attendees()->count())->toBeOne();
});

it('refuses closed events', function (string $state): void {
    $event = Event::factory()->{$state}()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('event-attendance.store', $event->commit))
        ->assertForbidden();

    expect($event->attendees()->count())->toBe(0);
})->with(['past', 'cancelled']);

it('refuses events awaiting approval', function (): void {
    $author = User::factory()->create();
    $event = Event::factory()->for(Commit::factory()->for($author)->pending())->create();

    $this->actingAs($author)
        ->post(route('event-attendance.store', $event->commit))
        ->assertForbidden();
});

it('refuses the host', function (): void {
    $event = Event::factory()->create();

    $this->actingAs($event->commit->user)
        ->post(route('event-attendance.store', $event->commit))
        ->assertForbidden();
});

it('may not go to events it cannot see', function (): void {
    $event = Event::factory()->for(Commit::factory()->rejected())->create();

    $this->actingAs(User::factory()->create())
        ->post(route('event-attendance.store', $event->commit))
        ->assertForbidden();
});

it('is not found for commits without an event', function (): void {
    $user = User::factory()->create();
    $commit = Commit::factory()->create();

    $this->actingAs($user)->post(route('event-attendance.store', $commit))->assertNotFound();
    $this->actingAs($user)->delete(route('event-attendance.destroy', $commit))->assertNotFound();
});

it('may stop going, even after the event was cancelled', function (): void {
    $user = User::factory()->create();
    $event = Event::factory()->cancelled()->create();
    $event->attendees()->attach($user);

    $this->actingAs($user)
        ->delete(route('event-attendance.destroy', $event->commit))
        ->assertRedirect();

    expect($event->attendees()->count())->toBe(0);
});

it('requires authentication', function (): void {
    $event = Event::factory()->create();

    $this->post(route('event-attendance.store', $event->commit))->assertRedirectToRoute('home');
});
