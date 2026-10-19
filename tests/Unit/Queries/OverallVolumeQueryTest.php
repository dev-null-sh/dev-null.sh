<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\User;
use App\Queries\OverallVolumeQuery;

it('counts everyone and everything since the first signup, whatever the status since, deleted commits included', function (): void {
    $alice = User::factory()->create(['created_at' => '2025-03-01 08:00:00']);
    $bob = User::factory()->create();
    User::factory()->create();

    $commit = Commit::factory()->for($alice)->create(['created_at' => '2025-03-02 00:00:00']);
    Commit::factory()->for($alice)->pending()->create()->delete();
    Commit::factory()->for($bob)->for($commit, 'parent')->create();

    expect(resolve(OverallVolumeQuery::class)->get())->toBe([
        'since' => '2025-03-01',
        'signups' => 3,
        'commits' => 2,
        'replies' => 1,
        'authors' => 2,
    ]);
});

it('has nothing to count before the first signup', function (): void {
    expect(resolve(OverallVolumeQuery::class)->get())->toBe([
        'since' => null,
        'signups' => 0,
        'commits' => 0,
        'replies' => 0,
        'authors' => 0,
    ]);
});
