<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\User;
use App\Queries\TodayVolumeQuery;

beforeEach(function (): void {
    $this->travelTo(now()->setDate(2026, 10, 15)->setTime(12, 30));
});

it('counts the signups of today and yesterday, as utc days', function (): void {
    User::factory()->create();
    User::factory()->create(['created_at' => '2026-10-15 00:00:00']);
    User::factory()->create(['created_at' => '2026-10-14 23:59:59']);
    User::factory()->create(['created_at' => '2026-10-14 00:00:00']);
    User::factory()->create(['created_at' => '2026-10-13 23:59:59']);

    expect(resolve(TodayVolumeQuery::class)->get()['signups'])->toBe(['today' => 2, 'yesterday' => 2]);
});

it('counts the commits, replies and their authors of today and yesterday, whatever the status since, deleted ones included', function (): void {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $carol = User::factory()->create();

    $commit = Commit::factory()->for($alice)->create();
    Commit::factory()->for($alice)->for($commit, 'parent')->create();
    Commit::factory()->for($bob)->pending()->create(['created_at' => '2026-10-14 08:00:00']);
    Commit::factory()->for($bob)->create(['created_at' => '2026-10-14 09:00:00'])->delete();
    Commit::factory()->for($carol)->for($commit, 'parent')->create(['created_at' => '2026-10-13 23:59:59']);

    expect(resolve(TodayVolumeQuery::class)->get())
        ->commits->toBe(['today' => 1, 'yesterday' => 2])
        ->replies->toBe(['today' => 1, 'yesterday' => 0])
        ->authors->toBe(['today' => 1, 'yesterday' => 1]);
});
