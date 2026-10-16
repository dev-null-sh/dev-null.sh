<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\Event;
use App\Models\User;

it('downloads the event as a calendar file with the join link', function (): void {
    $event = Event::factory()->online()->create(['join_url' => 'https://zoom.us/j/meeting']);

    $response = $this->actingAs(User::factory()->create())->get(route('event-calendar.show', $event->commit));

    $response->assertOk()
        ->assertHeader('Content-Type', 'text/calendar; charset=utf-8')
        ->assertHeader('Content-Disposition', 'attachment; filename="'.$event->commit->shortHash().'.ics"');

    expect($response->getContent())->toContain('BEGIN:VEVENT')->toContain('zoom.us/j/meeting');
});

it('opens the calendar file in place for Apple Calendar instead of downloading it', function (): void {
    $event = Event::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('event-calendar.show', ['commit' => $event->commit, 'open' => 1]))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/calendar; charset=utf-8')
        ->assertHeader('Content-Disposition', 'inline; filename="'.$event->commit->shortHash().'.ics"');
});

it('is not found while the commit is not visible to the viewer', function (string $state): void {
    $event = Event::factory()->for(Commit::factory()->{$state}())->create();

    $this->actingAs(User::factory()->create())->get(route('event-calendar.show', $event->commit))->assertNotFound();
})->with(['pending', 'rejected']);

it('lets the author download their pending event', function (): void {
    $author = User::factory()->create();
    $event = Event::factory()->for(Commit::factory()->for($author)->pending())->create();

    $this->actingAs($author)->get(route('event-calendar.show', $event->commit))->assertOk();
});

it('is not found for commits without an event or deleted commits', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('event-calendar.show', Commit::factory()->create()))->assertNotFound();

    $event = Event::factory()->create();
    $event->commit->delete();

    $this->actingAs($user)->get(route('event-calendar.show', $event->commit_id))->assertNotFound();
});
