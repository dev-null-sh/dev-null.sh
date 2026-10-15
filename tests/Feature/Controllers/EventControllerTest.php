<?php

declare(strict_types=1);

use App\Enums\NotificationType;
use App\Models\Commit;
use App\Models\Event;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * @param  array<string, mixed>  $overrides
 * @return array{event: array<string, mixed>}
 */
function eventChanges(array $overrides = []): array
{
    return ['event' => [
        'starts_at' => now()->addDays(12)->format('Y-m-d').'T20:00',
        'ends_at' => now()->addDays(12)->format('Y-m-d').'T22:00',
        'timezone' => 'Europe/Kyiv',
        'format' => 'onsite',
        'venue' => 'New Hall',
        'address' => '1 Rynok Square, Lviv',
        ...$overrides,
    ]];
}

it('lets the host move the event and marks it updated', function (): void {
    $event = Event::factory()->create();
    $attendee = User::factory()->create();
    $event->attendees()->attach($attendee);

    $this->actingAs($event->commit->user)
        ->patch(route('event.update', $event->commit), eventChanges())
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $event->refresh();

    expect($event->venue)->toBe('New Hall')
        ->and($event->sequence)->toBe(1)
        ->and($event->details_changed_at?->toDateTimeString())->toBe(now()->toDateTimeString())
        ->and(Notification::query()->where('recipient_id', $attendee->id)->sole()->type)->toBe(NotificationType::EventChanged);
});

it('does not count a save without changes', function (): void {
    $event = Event::factory()->create();

    $this->actingAs($event->commit->user)->patch(route('event.update', $event->commit), ['event' => [
        'starts_at' => $event->starts_at->copy()->setTimezone('Europe/Kyiv')->format('Y-m-d\TH:i'),
        'ends_at' => $event->ends_at->copy()->setTimezone('Europe/Kyiv')->format('Y-m-d\TH:i'),
        'timezone' => 'Europe/Kyiv',
        'format' => 'onsite',
        'venue' => $event->venue,
        'address' => $event->address,
    ]])->assertSessionHasNoErrors();

    expect($event->refresh()->sequence)->toBe(0)
        ->and(Notification::query()->count())->toBe(0);
});

it('ignores a new title', function (): void {
    $event = Event::factory()->create(['title' => 'Original']);

    $this->actingAs($event->commit->user)->patch(route('event.update', $event->commit), eventChanges(['title' => 'Changed']));

    expect($event->refresh()->title)->toBe('Original');
});

it('changes the cover only while the commit is pending', function (): void {
    Storage::fake();
    $author = User::factory()->create();
    $pending = Event::factory()->for(Commit::factory()->for($author)->pending())->create();
    $approved = Event::factory()->for(Commit::factory()->for($author))->create();
    $cover = fn () => UploadedFile::fake()->image('cover.jpg', 800, 450);

    $this->actingAs($author)->patch(route('event.update', $pending->commit), eventChanges(['cover' => $cover()]))->assertSessionHasNoErrors();
    $this->actingAs($author)->patch(route('event.update', $approved->commit), eventChanges(['cover' => $cover()]))
        ->assertSessionHasErrors(['event.cover' => "The cover can't change after approval."]);

    expect($pending->refresh()->cover_path)->not->toBeNull()
        ->and($approved->refresh()->cover_path)->toBeNull();
});

it('only lets the host edit open events', function (): void {
    $event = Event::factory()->create();

    $this->actingAs(User::factory()->create())->patch(route('event.update', $event->commit), eventChanges())->assertForbidden();

    $ended = Event::factory()->past()->create();
    $this->actingAs($ended->commit->user)->patch(route('event.update', $ended->commit), eventChanges())
        ->assertForbidden();

    $cancelled = Event::factory()->cancelled()->create();
    $this->actingAs($cancelled->commit->user)->patch(route('event.update', $cancelled->commit), eventChanges())
        ->assertForbidden();
});

it('is not found for commits without an event, even for their author', function (): void {
    $commit = Commit::factory()->create();

    $this->actingAs($commit->user)->patch(route('event.update', $commit), eventChanges())->assertNotFound();
});

it('validates the changes like a new event', function (): void {
    $event = Event::factory()->create();

    $this->actingAs($event->commit->user)
        ->patch(route('event.update', $event->commit), eventChanges(['format' => 'online', 'join_url' => 'http://insecure.test']))
        ->assertSessionHasErrors(['event.platform', 'event.join_url']);
});

it('lets the host fix the place of an event that has already started', function (): void {
    $event = Event::factory()->live()->create();

    $this->actingAs($event->commit->user)->patch(route('event.update', $event->commit), ['event' => [
        'starts_at' => $event->starts_at->copy()->setTimezone('Europe/Kyiv')->format('Y-m-d\\TH:i'),
        'ends_at' => $event->ends_at->copy()->setTimezone('Europe/Kyiv')->format('Y-m-d\\TH:i'),
        'timezone' => 'Europe/Kyiv',
        'format' => 'onsite',
        'venue' => 'Room B',
        'address' => $event->address,
    ]])->assertSessionHasNoErrors();

    expect($event->refresh()->venue)->toBe('Room B');
});

it('still refuses moving a started event to another past time', function (): void {
    $event = Event::factory()->live()->create();

    $this->actingAs($event->commit->user)->patch(route('event.update', $event->commit), eventChanges([
        'starts_at' => now()->subHours(3)->setTimezone('Europe/Kyiv')->format('Y-m-d\\TH:i'),
    ]))->assertSessionHasErrors('event.starts_at');
});

it('does not end a running event early through an edit', function (): void {
    $event = Event::factory()->live()->create();

    $this->actingAs($event->commit->user)->patch(route('event.update', $event->commit), ['event' => [
        'starts_at' => $event->starts_at->copy()->setTimezone('Europe/Kyiv')->format('Y-m-d\\TH:i'),
        'ends_at' => now()->subMinutes(2)->setTimezone('Europe/Kyiv')->format('Y-m-d\\TH:i'),
        'timezone' => 'Europe/Kyiv',
        'format' => 'onsite',
        'venue' => $event->venue,
        'address' => $event->address,
    ]])->assertSessionHasErrors('event.ends_at');

    expect($event->refresh()->sequence)->toBe(0);
});

it('expands emoji shortcodes in an edited venue', function (): void {
    $event = Event::factory()->create();

    $this->actingAs($event->commit->user)->patch(route('event.update', $event->commit), eventChanges(['venue' => 'Hall :rocket']));

    expect($event->refresh()->venue)->toBe('Hall 🚀');
});

it('counts a new time zone as a change', function (): void {
    $event = Event::factory()->create(['timezone' => 'Europe/Kyiv']);
    $start = $event->starts_at->copy()->setTimezone('Europe/Warsaw');
    $end = $event->ends_at->copy()->setTimezone('Europe/Warsaw');

    $this->actingAs($event->commit->user)->patch(route('event.update', $event->commit), ['event' => [
        'starts_at' => $start->format('Y-m-d\\TH:i'),
        'ends_at' => $end->format('Y-m-d\\TH:i'),
        'timezone' => 'Europe/Warsaw',
        'format' => 'onsite',
        'venue' => $event->venue,
        'address' => $event->address,
    ]])->assertSessionHasNoErrors();

    expect($event->refresh()->sequence)->toBe(1);
});

it('erases the replaced cover once the change is saved', function (): void {
    Storage::fake();
    $author = User::factory()->create();
    $event = Event::factory()->withCover()->for(Commit::factory()->for($author)->pending())->create();
    $previous = (string) $event->cover_path;
    Storage::disk()->put($previous, 'old');

    $this->actingAs($author)->patch(route('event.update', $event->commit), eventChanges([
        'cover' => UploadedFile::fake()->image('cover.jpg', 800, 450),
    ]))->assertSessionHasNoErrors();

    Storage::disk()->assertMissing($previous);
    Storage::disk()->assertExists((string) $event->refresh()->cover_path);
});
