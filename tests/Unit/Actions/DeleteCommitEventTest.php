<?php

declare(strict_types=1);

use App\Actions\CreateCommitRestoration;
use App\Actions\DeleteCommit;
use App\Actions\DeleteCommitEvent;
use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake();
    Queue::fake();
});

function deletedCommitWithEvent(): Event
{
    $event = Event::factory()->withCover()->create();
    Storage::disk()->put((string) $event->cover_path, 'webp');
    $event->attendees()->attach(User::factory()->create());

    resolve(DeleteCommit::class)->handle($event->commit);

    return $event;
}

it('erases the event, its attendees, and its cover once the undo window has closed', function (): void {
    $event = deletedCommitWithEvent();
    $this->travel(30)->seconds();

    resolve(DeleteCommitEvent::class)->handle($event->commit);

    $this->assertModelMissing($event);
    expect(DB::table('event_attendees')->count())->toBe(0);
    Storage::disk()->assertMissing((string) $event->cover_path);
});

it('keeps the event while the commit can still be restored', function (): void {
    $event = deletedCommitWithEvent();
    $this->travel(29)->seconds();

    resolve(DeleteCommitEvent::class)->handle($event->commit);

    $this->assertModelExists($event);
    Storage::disk()->assertExists((string) $event->cover_path);
});

it('keeps the event of a restored commit', function (): void {
    $event = deletedCommitWithEvent();
    resolve(CreateCommitRestoration::class)->handle($event->commit);
    $this->travel(30)->seconds();

    resolve(DeleteCommitEvent::class)->handle($event->commit);

    $this->assertModelExists($event);
    Storage::disk()->assertExists((string) $event->cover_path);
});

it('leaves a deleted commit without an event alone', function (): void {
    $commit = Event::factory()->create()->commit;
    $commit->event?->delete();
    resolve(DeleteCommit::class)->handle($commit);
    $this->travel(30)->seconds();

    resolve(DeleteCommitEvent::class)->handle($commit);

    expect(Event::query()->count())->toBe(0);
});
