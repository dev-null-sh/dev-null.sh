<?php

declare(strict_types=1);

use App\Enums\Log;
use App\Models\Commit;
use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('shows events in the log with attendance and calendar links', function (): void {
    $viewer = User::factory()->create();
    $event = Event::factory()->online()->create(['title' => 'Async PHP night']);
    $event->attendees()->attach([$viewer->id, User::factory()->create()->id]);

    $this->actingAs($viewer)->get(route('log.show', Log::Latest))
        ->assertInertia(fn ($page) => $page
            ->where('commits.data.0.event.title', 'Async PHP night')
            ->where('commits.data.0.event.attendees_count', 2)
            ->where('commits.data.0.event.is_going', true)
            ->where('commits.data.0.event.state', 'upcoming')
            ->where('commits.data.0.event.join_url', $event->join_url)
            ->where('commits.data.0.event.calendar.ics_url', route('event-calendar.show', $event->commit))
            ->where('commits.data.0.event.calendar.apple_url', route('event-calendar.show', ['commit' => $event->commit, 'open' => 1]))
            ->has('commits.data.0.event.calendar.google_url')
            ->has('commits.data.0.event.calendar.outlook_url'));
});

it('shows null for commits without an event', function (): void {
    $viewer = User::factory()->create();
    Commit::factory()->create();

    $this->actingAs($viewer)->get(route('log.show', Log::Latest))
        ->assertInertia(fn ($page) => $page->where('commits.data.0.event', null));
});

it('shows the event on the commit page', function (): void {
    $viewer = User::factory()->create();
    $event = Event::factory()->create();

    $this->actingAs($viewer)->get(route('commit.show', [$event->commit->user, $event->commit]))
        ->assertInertia(fn ($page) => $page->where('commit.event.id', $event->id));
});

it('loads events without a query per commit', function (): void {
    $viewer = User::factory()->create(['log' => Log::Latest]);
    $count = function () use ($viewer): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($viewer)->get(route('log.show', Log::Latest))->assertOk();

        return count(DB::getQueryLog());
    };

    Event::factory()->create()->attendees()->attach(User::factory()->create());
    $one = $count();

    Event::factory()->count(19)->create()->each(fn (Event $event) => $event->attendees()->attach(User::factory()->create()));
    $twenty = $count();

    expect($twenty)->toBe($one);
});

it('builds google and outlook links in utc', function (): void {
    $event = Event::factory()->create([
        'title' => 'Night & day',
        'starts_at' => '2026-11-12 17:00:00',
        'ends_at' => '2026-11-12 19:30:00',
    ]);

    $links = $event->load('commit.user')->calendarLinks();

    expect($links['google_url'])->toStartWith('https://calendar.google.com/calendar/render?action=TEMPLATE')
        ->toContain('text=Night%20%26%20day')
        ->toContain('dates=20261112T170000Z%2F20261112T193000Z')
        ->and($links['outlook_url'])->toStartWith('https://outlook.live.com/calendar/0/deeplink/compose?')
        ->toContain('startdt=2026-11-12T17%3A00%3A00Z');
});
