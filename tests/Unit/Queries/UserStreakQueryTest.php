<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\User;
use App\Queries\UserStreakQuery;

beforeEach(function (): void {
    $this->travelTo(now()->setDate(2026, 10, 15)->setTime(12, 0));
});

it('has no streaks without commits', function (): void {
    $user = User::factory()->create();

    Commit::factory()->create();

    expect(resolve(UserStreakQuery::class)->get($user, $user))
        ->toBe(['longest' => 0, 'current' => 0]);
});

it('counts consecutive days with commits and breaks on gaps', function (): void {
    $user = User::factory()->create();

    Commit::factory()->for($user)->count(3)->create();
    Commit::factory()->for($user)->create(['created_at' => now()->subDay()]);
    Commit::factory()->for($user)->create(['created_at' => now()->subDays(3)]);
    Commit::factory()->for($user)->create(['created_at' => now()->subDays(4)]);
    Commit::factory()->for($user)->create(['created_at' => now()->subDays(5)]);
    Commit::factory()->for($user)->create(['created_at' => now()->subDays(9)]);

    expect(resolve(UserStreakQuery::class)->get($user, $user))
        ->toBe(['longest' => 3, 'current' => 2]);
});

it('keeps the current streak alive when the last commit was yesterday', function (): void {
    $user = User::factory()->create();

    Commit::factory()->for($user)->create(['created_at' => now()->subDay()->setTime(23, 59)]);
    Commit::factory()->for($user)->create(['created_at' => now()->subDays(2)->setTime(0, 1)]);

    expect(resolve(UserStreakQuery::class)->get($user, $user))
        ->toBe(['longest' => 2, 'current' => 2]);
});

it('breaks the current streak when the last commit was two days ago', function (): void {
    $user = User::factory()->create();

    Commit::factory()->for($user)->create(['created_at' => now()->subDays(2)->setTime(23, 59)]);
    Commit::factory()->for($user)->create(['created_at' => now()->subDays(3)]);

    expect(resolve(UserStreakQuery::class)->get($user, $user))
        ->toBe(['longest' => 2, 'current' => 0]);
});

it('finds the longest streak beyond the last year', function (): void {
    $user = User::factory()->create();

    foreach (range(0, 11) as $day) {
        Commit::factory()->for($user)->create(['created_at' => now()->subYears(3)->addDays($day)]);
    }

    Commit::factory()->for($user)->create();

    expect(resolve(UserStreakQuery::class)->get($user, $user))
        ->toBe(['longest' => 12, 'current' => 1]);
});

it('only counts the commits visible to the viewer', function (): void {
    $user = User::factory()->create();

    Commit::factory()->for($user)->create(['created_at' => now()->subDays(2)]);
    Commit::factory()->for($user)->pending()->create(['created_at' => now()->subDay()]);
    Commit::factory()->for($user)->rejected()->create();

    $query = resolve(UserStreakQuery::class);

    expect($query->get($user, User::factory()->create()))->toBe(['longest' => 1, 'current' => 0])
        ->and($query->get($user, $user))->toBe(['longest' => 3, 'current' => 3]);
});

it('does not extend streaks through deleted commits', function (): void {
    $user = User::factory()->create();

    Commit::factory()->for($user)->create();
    Commit::factory()->for($user)->create(['created_at' => now()->subDay()])->delete();
    Commit::factory()->for($user)->create(['created_at' => now()->subDays(2)]);

    $query = resolve(UserStreakQuery::class);

    expect($query->get($user, $user))->toBe(['longest' => 1, 'current' => 1])
        ->and($query->get($user, User::factory()->create()))->toBe(['longest' => 1, 'current' => 1]);
});
