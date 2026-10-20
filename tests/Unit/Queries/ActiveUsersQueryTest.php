<?php

declare(strict_types=1);

use App\Actions\CreateTomFriendship;
use App\Models\Commit;
use App\Models\User;
use App\Queries\ActiveUsersQuery;

beforeEach(function (): void {
    $this->travelTo(now()->setDate(2026, 10, 19)->setTime(12, 0));
});

it('counts the people seen today, in the last 7 days and in the last 30, leaving the bots out', function (): void {
    User::factory()->create(['last_seen_at' => '2026-10-19']);
    User::factory()->create(['last_seen_at' => '2026-10-13']);
    User::factory()->create(['last_seen_at' => '2026-10-12']);
    User::factory()->create(['last_seen_at' => '2026-09-20']);
    User::factory()->create(['last_seen_at' => '2026-09-19']);
    User::factory()->create(['last_seen_at' => null]);
    User::factory()->create(['github_id' => CreateTomFriendship::GITHUB_ID, 'last_seen_at' => '2026-10-19']);

    expect(resolve(ActiveUsersQuery::class)->get())
        ->daily->toBe(1)
        ->weekly->toBe(2)
        ->monthly->toBe(4);
});

it('counts the people who wrote a commit or a reply in the last 7 days, deleted ones included', function (): void {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $tom = User::factory()->create(['github_id' => CreateTomFriendship::GITHUB_ID]);

    $commit = Commit::factory()->for($alice)->create(['created_at' => '2026-10-13 00:00:00']);
    Commit::factory()->for($alice)->for($commit, 'parent')->create();
    Commit::factory()->for($bob)->create()->delete();
    Commit::factory()->for($bob)->create(['created_at' => '2026-10-12 23:59:59']);
    Commit::factory()->for($tom)->create();

    expect(resolve(ActiveUsersQuery::class)->get()['weekly_authors'])->toBe(2);
});
