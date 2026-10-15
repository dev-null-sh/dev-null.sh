<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake();
});

function eventWithCover(?Commit $commit = null): Event
{
    $event = Event::factory()->withCover()->for($commit ?? Commit::factory()->create())->create();
    Storage::disk()->put((string) $event->cover_path, 'webp-bytes');

    return $event;
}

it('serves the cover of an approved event to signed-in people', function (): void {
    $event = eventWithCover();

    $this->actingAs(User::factory()->create())->get(route('event-cover.show', $event))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/webp')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Content-Security-Policy', "default-src 'none'; sandbox; frame-ancestors 'none'");
});

it('hides pending covers from everyone but the author', function (): void {
    $author = User::factory()->create();
    $event = eventWithCover(Commit::factory()->for($author)->pending()->create());

    $this->actingAs(User::factory()->create())->get(route('event-cover.show', $event))->assertNotFound();
    $this->actingAs($author)->get(route('event-cover.show', $event))->assertOk();
});

it('hides covers of deleted commits and events without a cover', function (): void {
    $event = eventWithCover();
    $event->commit->delete();

    $this->actingAs(User::factory()->create());
    $this->get(route('event-cover.show', $event))->assertNotFound();
    $this->get(route('event-cover.show', Event::factory()->create()))->assertNotFound();
});

it('serves approved covers to guests but hides pending ones', function (): void {
    $this->get(route('event-cover.show', eventWithCover()))->assertOk();
    $this->get(route('event-cover.show', eventWithCover(Commit::factory()->pending()->create())))->assertNotFound();
});
