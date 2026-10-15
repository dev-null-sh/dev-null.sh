<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\User;
use App\Queries\RecentVolumeQuery;

it('counts signups, commits, and replies over the last day and week', function (): void {
    $user = User::factory()->create();
    User::factory()->create(['created_at' => now()->subDays(3)]);
    User::factory()->create(['created_at' => now()->subDays(8)]);

    $commit = Commit::factory()->for($user)->create();
    Commit::factory()->for($user)->pending()->create(['created_at' => now()->subDays(2)]);
    Commit::factory()->for($user)->create(['created_at' => now()->subDays(10)]);
    Commit::factory()->for($user)->create()->delete();
    Commit::factory()->for($user)->for($commit, 'parent')->create(['created_at' => now()->subHours(23)]);
    Commit::factory()->for($user)->for($commit, 'parent')->create(['created_at' => now()->subDays(6)]);

    expect(resolve(RecentVolumeQuery::class)->get())->toBe([
        'signups' => ['day' => 1, 'week' => 2],
        'commits' => ['day' => 2, 'week' => 3],
        'replies' => ['day' => 1, 'week' => 2],
    ]);
});
