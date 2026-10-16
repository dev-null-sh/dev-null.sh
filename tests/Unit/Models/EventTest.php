<?php

declare(strict_types=1);

use App\Enums\EventFormat;
use App\Enums\EventState;
use App\Enums\StreamPlatform;
use App\Models\Commit;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

it('belongs to a commit and is reachable from it', function (): void {
    $event = Event::factory()->create();

    expect($event->commit)->toBeInstanceOf(Commit::class)
        ->and($event->commit->event?->is($event))->toBeTrue();
});

it('sets the inverse commit when eager loaded from commits', function (): void {
    Event::factory()->count(2)->create();

    $commits = Commit::query()->with('event')->get();

    expect($commits->every(fn (Commit $commit): bool => $commit->event?->relationLoaded('commit') === true))->toBeTrue();
});

it('casts its columns', function (): void {
    $event = Event::factory()->online()->create();

    expect($event->format)->toBe(EventFormat::Online)
        ->and($event->platform)->toBe(StreamPlatform::Youtube)
        ->and($event->starts_at->isFuture())->toBeTrue()
        ->and($event->refresh()->sequence)->toBe(0);
});

it('knows its state', function (string $factoryState, EventState $state): void {
    $event = $factoryState === 'upcoming'
        ? Event::factory()->create()
        : Event::factory()->{$factoryState}()->create();

    expect($event->state())->toBe($state);
})->with([
    ['upcoming', EventState::Upcoming],
    ['live', EventState::Live],
    ['past', EventState::Ended],
    ['cancelled', EventState::Cancelled],
]);

it('describes where it happens', function (): void {
    $onsite = Event::factory()->make(['venue' => 'Coworking Hall', 'address' => '12 Market Square, Lviv']);
    $hybrid = Event::factory()->hybrid()->make(['venue' => 'Coworking Hall', 'address' => 'Lviv', 'join_url' => 'https://youtube.com/live/abc']);

    $withoutLink = Event::factory()->online()->make(['join_url' => null]);

    expect($onsite->location())->toBe('Coworking Hall, 12 Market Square, Lviv')
        ->and($hybrid->location())->toBe('Coworking Hall, Lviv · https://youtube.com/live/abc')
        ->and($withoutLink->location())->toBe('YouTube Live');
});

it('is deleted with its commit and takes its attendees along', function (): void {
    $event = Event::factory()->create();
    $event->attendees()->attach(User::factory()->create());

    $event->commit->forceDelete();

    expect(Event::query()->count())->toBe(0)
        ->and(DB::table('event_attendees')->count())->toBe(0);
});

it('allows one attendance per user', function (): void {
    $event = Event::factory()->create();
    $user = User::factory()->create();

    $event->attendees()->attach($user);

    expect(fn () => $event->attendees()->attach($user))->toThrow(UniqueConstraintViolationException::class);
});

it('changes the cover url when the cover file changes', function (): void {
    $event = Event::factory()->withCover()->create();
    $before = $event->cover()['url'] ?? null;

    $event->update(['cover_path' => 'event-covers/another.webp']);

    expect($event->cover()['url'] ?? null)->not->toBe($before)
        ->and($event->cover()['url'] ?? '')->toStartWith(route('event-cover.show', $event));
});
