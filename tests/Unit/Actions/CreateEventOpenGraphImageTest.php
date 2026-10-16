<?php

declare(strict_types=1);

use App\Actions\CreateCommitOpenGraphImage;
use App\Actions\CreateEventOpenGraphImage;
use App\Models\Commit;
use App\Models\Event;
use App\Models\User;

it('draws events as a 1200 by 630 card', function (array $attributes, string $state): void {
    $commit = Commit::factory()->for(User::factory()->withoutProfile())->create();
    $factory = Event::factory()->for($commit);
    $factory = $state === 'upcoming' ? $factory : $factory->{$state}();
    $factory->create($attributes);

    $image = resolve(CreateCommitOpenGraphImage::class)->handle($commit->user, $commit->fresh());

    expect(array_slice((array) getimagesizefromstring($image), 0, 2))->toBe([1200, 630]);
})->with([
    'short title' => [['title' => 'PHP night'], 'upcoming'],
    'longest title' => [['title' => mb_trim(str_repeat('WONDERFUL ', 12))], 'upcoming'],
    'online' => [['title' => 'Stream'], 'online'],
    'cancelled' => [['title' => 'Cancelled night'], 'cancelled'],
    'emoji only' => [['title' => '🚀🔥'], 'upcoming'],
]);

it('draws the event instead of the commit once the commit has one', function (): void {
    $commit = Commit::factory()->for(User::factory()->withoutProfile())->create(['body' => 'Same body']);
    $action = resolve(CreateCommitOpenGraphImage::class);

    $withoutEvent = $action->handle($commit->user, $commit->fresh());
    Event::factory()->for($commit)->create();

    expect($action->handle($commit->user, $commit->fresh()))->not->toBe($withoutEvent);
});

it('shows how many people are going', function (): void {
    $event = Event::factory()->for(Commit::factory()->for(User::factory()->withoutProfile()))->create();
    $action = resolve(CreateEventOpenGraphImage::class);

    $nobodyGoing = $action->handle($event->commit->user, $event->commit->fresh());
    $event->attendees()->attach(User::factory()->create());

    expect($action->handle($event->commit->user, $event->commit->fresh()))->not->toBe($nobodyGoing);
});
