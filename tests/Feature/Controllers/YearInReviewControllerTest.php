<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\User;

it('renders the year in review for guests', function (): void {
    $user = User::factory()->create();
    $year = (int) now()->year;

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
            ->where('stats.starsEarned', 2)
            ->where('stats.topCommit.stars', 2)
            ->where('stats.months.2', 1)
            ->where('stats.months.4', 1)
            ->where('stats.comeback', false)
            ->has('stats.hourCounts', 24));
});

it('describes the review to search engines and link previews', function (): void {
    $user = User::factory()->create();
    $year = (int) now()->year;

    $this->get(route('wrapped.show', ['profile' => $user->profile->handle]))
        ->assertOk()
        ->assertSee("<title>{$user->profile->handle}&#039;s {$year} in review - /dev/null</title>", false);
});
