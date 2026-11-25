<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\User;
use Carbon\Carbon;

it('renders the year in review for guests', function (): void {
    $user = User::factory()->create();
    $year = (int) now()->year;
    $this->travelTo(Carbon::create($year, 12, 4, 9, 1));

    $top = Commit::factory()->for($user)->create(['created_at' => now()->setDate($year, 3, 10)]);
    $top->stars()->attach(User::factory()->count(2)->create(), ['created_at' => now()->setDate($year, 4, 1)]);
    Commit::factory()->for($user)->create(['created_at' => now()->setDate($year, 5, 20)]);
    Commit::factory()->for($user)->create(['created_at' => now()->setDate($year - 1, 6, 15)]);

    $this->get(route('wrapped.show', ['profile' => $user->profile->handle]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('wrapped/show')
            ->where('handle', $user->profile->handle)
            ->where('stats.year', $year)
            ->where('stats.commits', 2)
            ->where('shareUrl', route('wrapped.show', ['profile' => $user->profile->handle]))
            ->where('stats.starsEarned', 2)
            ->where('stats.topCommit.stars', 2)
            ->where('stats.months.2', 1)
            ->where('stats.months.4', 1)
            ->where('stats.languages', [])
            ->where('stats.comeback', false)
            ->where('stats.year', $year)
            ->where('year', $year)
            ->where('currentYear', $year)
            ->where('hasPreviousYear', true)
            ->has('stats.hourCounts', 24));
});

it('describes the review to search engines and link previews', function (): void {
    $user = User::factory()->create();
    $year = (int) now()->year;
    $this->travelTo(Carbon::create($year, 12, 4, 9, 1));

    $this->get(route('wrapped.show', ['profile' => $user->profile->handle]))
        ->assertOk()
        ->assertSee("<title>{$user->profile->handle}&#039;s {$year} in review - /dev/null</title>", false);
});

it('locks wrapped behind a countdown until December 3rd at 09:00', function (): void {
    $user = User::factory()->create();
    $year = (int) now()->year;
    $this->travelTo(Carbon::create($year, 12, 2, 23, 59));

    $this->get(route('wrapped.show', ['profile' => $user->profile->handle]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('wrapped/show')
            ->where('handle', $user->profile->handle)
            ->where('locked', true)
            ->where('stats', null)
            ->where('unlocksAt', Carbon::create($year, 12, 3, 9)->toIso8601String()));
});

it('opens wrapped once the unlock moment passes', function (): void {
    $user = User::factory()->create();
    $year = (int) now()->year;
    $this->travelTo(Carbon::create($year, 12, 3, 9));

    $this->get(route('wrapped.show', ['profile' => $user->profile->handle]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('wrapped/show')
            ->where('locked', false)
            ->where('unlocksAt', null)
            ->where('stats.year', $year));
});

it('counts consecutive calendar days as a streak', function (): void {
    $user = User::factory()->create();
    $year = (int) now()->year;
    $this->travelTo(Carbon::create($year, 12, 4, 9, 1));

    Commit::factory()->for($user)->create(['created_at' => now()->setDate($year, 1, 5)]);
    Commit::factory()->for($user)->create(['created_at' => now()->setDate($year, 1, 6)]);
    Commit::factory()->for($user)->create(['created_at' => now()->setDate($year, 1, 7)]);

    $this->get(route('wrapped.show', ['profile' => $user->profile->handle]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('stats.longestStreak', 3)
            ->where('stats.comeback', false));
});

it('flags a 7-day run after a month of silence as a comeback', function (): void {
    $user = User::factory()->create();
    $year = (int) now()->year;
    $this->travelTo(Carbon::create($year, 12, 4, 9, 1));

    Commit::factory()->for($user)->create(['created_at' => now()->setDate($year, 1, 1)]);

    foreach (range(1, 7) as $day) {
        Commit::factory()->for($user)->create(['created_at' => now()->setDate($year, 3, $day)]);
    }

    $this->get(route('wrapped.show', ['profile' => $user->profile->handle]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('stats.longestStreak', 7)
            ->where('stats.comeback', true));
});

it('merges language aliases into one entry per post', function (): void {
    $user = User::factory()->create();
    $year = (int) now()->year;
    $this->travelTo(Carbon::create($year, 12, 4, 9, 1));

    Commit::factory()->for($user)->create(['body' => "Note\n```js\nconst a = 1;\n```", 'created_at' => now()->setDate($year, 2, 1)]);
    Commit::factory()->for($user)->create(['body' => "Note\n```javascript\nconst b = 2;\n```\n```php\necho 1;\n```", 'created_at' => now()->setDate($year, 2, 2)]);
    Commit::factory()->for($user)->create(['body' => "Note\n```js\nconst c = 3;\n```\n```javascript\nconst d = 4;\n```", 'created_at' => now()->setDate($year, 2, 3)]);

    $this->get(route('wrapped.show', ['profile' => $user->profile->handle]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('stats.languages', [
                ['language' => 'JavaScript', 'commits' => 3],
                ['language' => 'PHP', 'commits' => 1],
            ]));
});

it('renders numeric fence tags without failing', function (): void {
    $user = User::factory()->create();
    $year = (int) now()->year;
    $this->travelTo(Carbon::create($year, 12, 4, 9, 1));

    Commit::factory()->for($user)->create(['body' => "Note\n```123\n456\n```", 'created_at' => now()->setDate($year, 2, 1)]);

    $this->get(route('wrapped.show', ['profile' => $user->profile->handle]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('stats.languages', [
                ['language' => '123', 'commits' => 1],
            ]));
});

it('opens a past year from a notification link', function (): void {
    $user = User::factory()->create();
    $year = (int) now()->year;
    $this->travelTo(Carbon::create($year, 12, 4, 9, 1));

    Commit::factory()->for($user)->create(['created_at' => now()->setDate($year - 1, 6, 15)]);

    $this->get(route('wrapped.show', ['profile' => $user->profile->handle, 'year' => $year - 1]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('wrapped/show')
            ->where('locked', false)
            ->where('stats.year', $year - 1)
            ->where('stats.commits', 1)
            ->where('year', $year - 1)
            ->where('currentYear', $year)
            ->where('hasPreviousYear', false)
            ->where('shareUrl', route('wrapped.show', ['profile' => $user->profile->handle, 'year' => $year - 1])));
});

it('falls back to the current year for a future year', function (): void {
    $user = User::factory()->create();
    $year = (int) now()->year;
    $this->travelTo(Carbon::create($year, 12, 4, 9, 1));

    $this->get(route('wrapped.show', ['profile' => $user->profile->handle, 'year' => $year + 5]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('locked', false)
            ->where('stats.year', $year));
});
