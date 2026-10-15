<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\User;
use App\Queries\UserActivityQuery;

beforeEach(function (): void {
    $this->travelTo(now()->setDate(2026, 10, 15)->setTime(12, 0));
});

it('counts the commits of each day since the start of the week a year ago', function (): void {
    $user = User::factory()->create();

    Commit::factory()->for($user)->count(2)->create();
    Commit::factory()->for($user)->create(['created_at' => now()->subDays(3)]);
    Commit::factory()->for($user)->create(['created_at' => now()->setDate(2025, 10, 12)]);
    Commit::factory()->for($user)->create(['created_at' => now()->setDate(2025, 10, 11)]);
    Commit::factory()->create();

    $activity = resolve(UserActivityQuery::class)->get($user, $user);

    expect($activity)->toHaveCount(369)
        ->and($activity[0])->toBe(['date' => '2025-10-12', 'count' => 1])
        ->and($activity[1])->toBe(['date' => '2025-10-13', 'count' => 0])
        ->and($activity[365])->toBe(['date' => '2026-10-12', 'count' => 1])
        ->and($activity[368])->toBe(['date' => '2026-10-15', 'count' => 2]);
});

it('only counts the commits visible to the viewer', function (): void {
    $user = User::factory()->create();

    Commit::factory()->for($user)->create();
    Commit::factory()->for($user)->pending()->create();
    Commit::factory()->for($user)->rejected()->create();

    $query = resolve(UserActivityQuery::class);

    expect($query->get($user, User::factory()->create())[368]['count'])->toBe(1)
        ->and($query->get($user, $user)[368]['count'])->toBe(3);
});
