<?php

declare(strict_types=1);

use App\Enums\EventResponse;
use App\Models\Commit;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\User;

it('records and changes a response', function (): void {
    $user = User::factory()->create();
    $event = Event::factory()->create();

    $this->actingAs($user)
        ->from(route('event.show', $event))
        ->put(route('event-attendance.update', $event), ['response' => 'going'])
        ->assertRedirect(route('event.show', $event));

    expect($user->eventAttendances()->sole()->response)->toBe(EventResponse::Going);

    $this->actingAs($user)->put(route('event-attendance.update', $event), ['response' => 'interested']);

    expect($user->eventAttendances()->sole()->response)->toBe(EventResponse::Interested);
});

it('takes responses while an event is happening', function (): void {
    $event = Event::factory()->live()->create();

    $this->actingAs(User::factory()->create())
        ->put(route('event-attendance.update', $event), ['response' => 'going'])
        ->assertSessionHasNoErrors();

    expect(EventAttendee::query()->count())->toBe(1);
});

it('explains why a response is refused', function (Closure $scenario, string $message): void {
    [$user, $event] = $scenario();

    $this->actingAs($user)
        ->put(route('event-attendance.update', $event), ['response' => 'going'])
        ->assertSessionHasErrors(['response' => $message]);

    expect(EventAttendee::query()->count())->toBe(0);
})->with([
    'host' => [function (): array {
        $event = Event::factory()->create();

        return [$event->commit->user, $event];
    }, 'You host this event.'],
    'ended' => [fn (): array => [User::factory()->create(), Event::factory()->ended()->create()], 'This event no longer takes responses.'],
    'cancelled' => [fn (): array => [User::factory()->create(), Event::factory()->cancelled()->create()], 'This event no longer takes responses.'],
]);

it('requires a known response', function (): void {
    $this->actingAs(User::factory()->create())
        ->put(route('event-attendance.update', Event::factory()->create()), ['response' => 'maybe'])
        ->assertSessionHasErrors('response');
});

it('hides events that cannot be seen', function (): void {
    $event = Event::factory()->for(Commit::factory()->pending())->create();
    $user = User::factory()->create();

    $this->actingAs($user)->put(route('event-attendance.update', $event), ['response' => 'going'])->assertNotFound();
    $this->actingAs($user)->delete(route('event-attendance.destroy', $event))->assertNotFound();
});

it('takes a response back, even after the event', function (): void {
    $user = User::factory()->create();
    $event = Event::factory()->ended()->create();
    EventAttendee::factory()->for($event)->for($user)->create();
    $other = EventAttendee::factory()->for($event)->create();

    $this->actingAs($user)
        ->delete(route('event-attendance.destroy', $event))
        ->assertRedirect();

    expect(EventAttendee::query()->sole()->is($other))->toBeTrue();
});

it('sends guests to sign in before responding', function (): void {
    $this->put(route('event-attendance.update', Event::factory()->create()), ['response' => 'going'])
        ->assertRedirect(route('home'));
});
