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
        ->toBe(['longest' => 0, 'current' => 0, 'shields' => 0, 'protected_days' => 0]);
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
        ->toBe(['longest' => 3, 'current' => 2, 'shields' => 0, 'protected_days' => 0]);
});

it('keeps the current streak alive when the last commit was yesterday', function (): void {
    $user = User::factory()->create();

    Commit::factory()->for($user)->create(['created_at' => now()->subDay()->setTime(23, 59)]);
    Commit::factory()->for($user)->create(['created_at' => now()->subDays(2)->setTime(0, 1)]);

    expect(resolve(UserStreakQuery::class)->get($user, $user))
        ->toBe(['longest' => 2, 'current' => 2, 'shields' => 0, 'protected_days' => 0]);
});

it('breaks the current streak when the last commit was two days ago', function (): void {
    $user = User::factory()->create();

    Commit::factory()->for($user)->create(['created_at' => now()->subDays(2)->setTime(23, 59)]);
    Commit::factory()->for($user)->create(['created_at' => now()->subDays(3)]);

    expect(resolve(UserStreakQuery::class)->get($user, $user))
        ->toBe(['longest' => 2, 'current' => 0, 'shields' => 0, 'protected_days' => 0]);
});

it('finds the longest streak beyond the last year', function (): void {
    $user = User::factory()->create();

    foreach (range(0, 11) as $day) {
        Commit::factory()->for($user)->create(['created_at' => now()->subYears(3)->addDays($day)]);
    }

    Commit::factory()->for($user)->create();

    expect(resolve(UserStreakQuery::class)->get($user, $user))
        ->toBe(['longest' => 12, 'current' => 1, 'shields' => 0, 'protected_days' => 0]);
});

it('only counts the commits visible to the viewer', function (): void {
    $user = User::factory()->create();

    Commit::factory()->for($user)->create(['created_at' => now()->subDays(2)]);
    Commit::factory()->for($user)->pending()->create(['created_at' => now()->subDay()]);
    Commit::factory()->for($user)->rejected()->create();

    $query = resolve(UserStreakQuery::class);

    expect($query->get($user, User::factory()->create()))->toBe(['longest' => 1, 'current' => 0, 'shields' => 0, 'protected_days' => 0])
        ->and($query->get($user, $user))->toBe(['longest' => 3, 'current' => 3, 'shields' => 0, 'protected_days' => 0]);
});

it('does not extend streaks through deleted commits', function (): void {
    $user = User::factory()->create();

    Commit::factory()->for($user)->create();
    Commit::factory()->for($user)->create(['created_at' => now()->subDay()])->delete();
    Commit::factory()->for($user)->create(['created_at' => now()->subDays(2)]);

    $query = resolve(UserStreakQuery::class);

    expect($query->get($user, $user))->toBe(['longest' => 1, 'current' => 1, 'shields' => 0, 'protected_days' => 0])
        ->and($query->get($user, User::factory()->create()))->toBe(['longest' => 1, 'current' => 1, 'shields' => 0, 'protected_days' => 0]);
});

it('only counts approved commits for guests', function (): void {
    $user = User::factory()->create();

    Commit::factory()->for($user)->create(['created_at' => now()->subDay()]);
    Commit::factory()->for($user)->pending()->create();

    expect(resolve(UserStreakQuery::class)->get($user, null))->toBe(['longest' => 1, 'current' => 1, 'shields' => 0, 'protected_days' => 0]);
});

it('earns shields from distinct commit days and caps the balance at two', function (int $days, int $shields): void {
    $user = User::factory()->create();

    foreach (range(0, $days - 1) as $day) {
        Commit::factory()->for($user)->count(2)->create(['created_at' => now()->subDays($day)]);
    }

    expect(resolve(UserStreakQuery::class)->get($user, $user))->toBe([
        'longest' => $days, 'current' => $days, 'shields' => $shields, 'protected_days' => 0,
    ]);
})->with([[6, 0], [7, 1], [14, 2], [21, 2]]);

it('covers only completed missed days and breaks when shields run out', function (int $age, int $current, int $shields, int $protected): void {
    $user = User::factory()->create();

    foreach (range(0, 13) as $day) {
        Commit::factory()->for($user)->create(['created_at' => now()->subDays($day + $age)]);
    }

    $query = resolve(UserStreakQuery::class);
    $expected = ['longest' => 14, 'current' => $current, 'shields' => $shields, 'protected_days' => $protected];

    expect($query->get($user, $user))->toBe($expected)
        ->and($query->get($user, $user))->toBe($expected);
})->with([[0, 14, 2, 0], [1, 14, 2, 0], [2, 14, 1, 1], [3, 14, 0, 2], [4, 0, 0, 0]]);

it('continues a protected streak and earns another shield after seven more commit days', function (): void {
    $user = User::factory()->create();

    foreach (range(0, 14) as $day) {
        if ($day !== 7) {
            Commit::factory()->for($user)->create(['created_at' => now()->subDays($day)]);
        }
    }

    expect(resolve(UserStreakQuery::class)->get($user, $user))->toBe([
        'longest' => 14, 'current' => 14, 'shields' => 1, 'protected_days' => 1,
    ]);
});

it('cannot use a future earned shield to repair an earlier gap', function (): void {
    $user = User::factory()->create();

    foreach ([9, 8, 7, 6, 5, 4, 2, 1, 0] as $day) {
        Commit::factory()->for($user)->create(['created_at' => now()->subDays($day)]);
    }

    expect(resolve(UserStreakQuery::class)->get($user, $user))->toBe([
        'longest' => 6, 'current' => 3, 'shields' => 0, 'protected_days' => 0,
    ]);
});

it('starts a new streak after an unprotected gap and resets shield progress', function (): void {
    $user = User::factory()->create();

    foreach ([10, 9, 8, 7, 6, 5, 4, 1, 0] as $day) {
        Commit::factory()->for($user)->create(['created_at' => now()->subDays($day)]);
    }

    expect(resolve(UserStreakQuery::class)->get($user, $user))->toBe([
        'longest' => 7, 'current' => 2, 'shields' => 0, 'protected_days' => 0,
    ]);
});

it('does not earn shields from hidden or deleted commits', function (): void {
    $user = User::factory()->create();

    foreach (range(2, 7) as $day) {
        Commit::factory()->for($user)->create(['created_at' => now()->subDays($day)]);
    }

    $hidden = Commit::factory()->for($user)->pending()->create(['created_at' => now()->subDay()]);
    $query = resolve(UserStreakQuery::class);

    expect($query->get($user, $user))->toBe([
        'longest' => 7, 'current' => 7, 'shields' => 1, 'protected_days' => 0,
    ])->and($query->get($user, null))->toBe([
        'longest' => 6, 'current' => 0, 'shields' => 0, 'protected_days' => 0,
    ]);

    $hidden->delete();

    expect($query->get($user, $user))->toBe([
        'longest' => 6, 'current' => 0, 'shields' => 0, 'protected_days' => 0,
    ]);
});

it('ignores future commit days when earning shields', function (): void {
    $user = User::factory()->create();

    foreach (range(-1, 5) as $day) {
        Commit::factory()->for($user)->create(['created_at' => now()->subDays($day)]);
    }

    expect(resolve(UserStreakQuery::class)->get($user, $user))->toBe([
        'longest' => 6, 'current' => 6, 'shields' => 0, 'protected_days' => 0,
    ]);
});
