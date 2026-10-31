<?php

declare(strict_types=1);

use App\Enums\ActivityScale;
use App\Models\Commit;
use App\Models\User;
use App\Queries\ActivityBreakdownsQuery;

beforeEach(function (): void {
    $this->travelTo(now()->setDate(2026, 10, 15)->setTime(12, 30));
});

it('counts the commits and replies of the window by status, in a fixed order, deleted ones included', function (): void {
    $commit = Commit::factory()->create();
    Commit::factory()->for($commit, 'parent')->pending()->create();
    Commit::factory()->pending()->create(['created_at' => '2026-10-08 12:30:00'])->delete();
    Commit::factory()->rejected()->create(['created_at' => '2026-10-08 12:29:59']);

    expect(resolve(ActivityBreakdownsQuery::class)->get(ActivityScale::Week)['statuses'])->toBe([
        ['name' => 'approved', 'count' => 1],
        ['name' => 'pending', 'count' => 2],
        ['name' => 'rejected', 'count' => 0],
    ]);
});

it('ranks the authors of the window by their commits and replies, the busiest first and ties by name', function (): void {
    $alice = User::factory()->create(['handle' => 'alice']);
    $bob = User::factory()->create(['handle' => 'bob']);
    $carol = User::factory()->create(['handle' => 'carol']);

    $commit = Commit::factory()->for($bob)->create();
    Commit::factory()->for($bob)->for($commit, 'parent')->create();
    Commit::factory()->for($alice)->rejected()->create()->delete();
    Commit::factory()->for($carol)->create();
    Commit::factory()->for($alice)->create(['created_at' => '2026-10-14 12:00:00']);

    expect(resolve(ActivityBreakdownsQuery::class)->get(ActivityScale::Day)['authors'])->toBe([
        ['name' => 'bob', 'count' => 2],
        ['name' => 'alice', 'count' => 1],
        ['name' => 'carol', 'count' => 1],
    ]);
});

it('keeps the ten busiest authors', function (): void {
    foreach (range(1, 11) as $index) {
        Commit::factory()->create();
    }

    expect(resolve(ActivityBreakdownsQuery::class)->get(ActivityScale::Week)['authors'])->toHaveCount(10);
});
