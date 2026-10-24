<?php

declare(strict_types=1);

use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\User;
use App\Queries\UserXpLogQuery;

beforeEach(function (): void {
    $this->travelTo(now()->setDate(2026, 10, 22)->setTime(12, 0));
});

it('lists where the xp of each day came from, newest first', function (): void {
    $user = User::factory()->create(['created_at' => '2026-10-20 10:00:00']);
    $other = User::factory()->create();
    $first = Commit::factory()->for($user)->create(['created_at' => '2026-10-20 09:00:00']);
    Commit::factory()->for($user)->create(['created_at' => '2026-10-21 09:00:00']);
    Commit::factory()->for($user)->create(['created_at' => '2026-10-21 10:00:00']);
    $theirs = Commit::factory()->for($other)->create(['created_at' => '2026-10-21 08:00:00']);
    Commit::factory()->for($user)->for($theirs, 'parent')->create(['created_at' => '2026-10-21 11:00:00']);
    Commit::factory()->for($user)->for($first, 'parent')->create(['created_at' => '2026-10-21 12:00:00']);
    $first->stars()->attach($other, ['created_at' => '2026-10-22 08:00:00', 'updated_at' => '2026-10-22 08:00:00']);
    $first->stars()->attach($user, ['created_at' => '2026-10-22 08:00:00', 'updated_at' => '2026-10-22 08:00:00']);
    $user->starredCommits()->attach($theirs, ['created_at' => '2026-10-22 09:00:00', 'updated_at' => '2026-10-22 09:00:00']);

    $log = resolve(UserXpLogQuery::class)->get($user, $user);

    expect($log)->toBe([
        ['date' => '2026-10-22', 'xp' => 7, 'entries' => [
            ['source' => 'stars_received', 'count' => 1, 'xp' => 5],
            ['source' => 'stars_given', 'count' => 1, 'xp' => 2],
        ]],
        ['date' => '2026-10-21', 'xp' => 60, 'entries' => [
            ['source' => 'commits', 'count' => 4, 'xp' => 40],
            ['source' => 'replies', 'count' => 1, 'xp' => 5],
            ['source' => 'streak', 'count' => 1, 'xp' => 15],
        ]],
        ['date' => '2026-10-20', 'xp' => 125, 'entries' => [
            ['source' => 'commits', 'count' => 1, 'xp' => 10],
            ['source' => 'streak', 'count' => 1, 'xp' => 15],
            ['source' => 'shell_shocked', 'count' => 1, 'xp' => 100],
        ]],
    ]);
});

it('only counts the days of the longest streak', function (): void {
    $user = User::factory()->create();
    foreach (['2026-10-10', '2026-10-11', '2026-10-12', '2026-10-15', '2026-10-16'] as $day) {
        Commit::factory()->for($user)->create(['created_at' => "{$day} 09:00:00"]);
    }

    $days = collect(resolve(UserXpLogQuery::class)->get($user, $user))
        ->filter(fn (array $day): bool => collect($day['entries'])->contains('source', 'streak'))
        ->pluck('date')->all();

    expect($days)->toBe(['2026-10-12', '2026-10-11', '2026-10-10']);
});

it('shows pending commits to their author only and leaves old days out', function (): void {
    $user = User::factory()->create(['created_at' => '2026-05-01 10:00:00']);
    Commit::factory()->for($user)->create(['status' => CommitStatus::Pending, 'created_at' => '2026-10-22 09:00:00']);
    Commit::factory()->for($user)->create(['created_at' => '2026-07-01 09:00:00']);

    $query = resolve(UserXpLogQuery::class);

    expect($query->get($user, $user))->toBe([
        ['date' => '2026-10-22', 'xp' => 25, 'entries' => [
            ['source' => 'commits', 'count' => 1, 'xp' => 10],
            ['source' => 'streak', 'count' => 1, 'xp' => 15],
        ]],
    ])->and($query->get($user, null))->toBe([]);
});
