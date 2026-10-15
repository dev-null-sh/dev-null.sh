<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\Event;
use App\Models\User;

it('shows guests the event without its join link, calendar links, or who is going', function (): void {
    $host = User::factory()->create(['username' => 'miranovak', 'name' => 'Mira Novak']);
    $event = Event::factory()->hybrid()->for(Commit::factory()->for($host)->state(['body' => 'Pizza is on us.']))->create([
        'title' => 'Async PHP night',
        'address' => '12 Market Square, Lviv',
        'join_url' => 'https://zoom.us/j/secret',
    ]);
    $event->attendees()->attach(User::factory()->create(['username' => 'someone_going']));

    $this->get(route('commit.show', [$host, $event->commit]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('commit/show')
            ->where('commit.event.title', 'Async PHP night')
            ->where('commit.event.address', '12 Market Square, Lviv')
            ->where('commit.event.attendees_count', 1)
            ->where('commit.event.is_going', false)
            ->where('commit.event.join_url', null)
            ->where('commit.event.calendar', null))
        ->assertDontSee('zoom.us/j/secret')
        ->assertDontSee('someone_going');
});

it('keeps the calendar file behind login', function (): void {
    $event = Event::factory()->create();

    $this->get(route('event-calendar.show', $event->commit))->assertRedirectToRoute('home');
});

it('changes the link preview image version when the event changes', function (): void {
    $event = Event::factory()->create();
    $commit = $event->commit->loadPublicPreview();
    $before = $commit->openGraphImageVersion();

    $event->update(['sequence' => 1]);

    expect($commit->fresh()?->loadPublicPreview()->openGraphImageVersion())->not->toBe($before);
});
