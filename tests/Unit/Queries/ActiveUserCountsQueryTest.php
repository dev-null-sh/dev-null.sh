<?php

declare(strict_types=1);

use App\Actions\CreateTomFriendship;
use App\Models\Commit;
use App\Models\User;
use App\Queries\ActiveUserCountsQuery;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->travelTo(now()->setDate(2026, 10, 19)->setTime(12, 0));
});

it('lists the daily counts of the range, oldest first', function (): void {
    DB::table('active_user_counts')->insert([
        ['seen_at' => '2026-10-19', 'count' => 30],
        ['seen_at' => '2026-10-17', 'count' => 10],
        ['seen_at' => '2026-10-18', 'count' => 20],
        ['seen_at' => '2026-10-16', 'count' => 5],
    ]);

    expect(resolve(ActiveUserCountsQuery::class)->get(CarbonImmutable::parse('2026-10-17'), CarbonImmutable::parse('2026-10-19')))->toBe([
        ['date' => '2026-10-17', 'count' => 10, 'returning' => 10, 'activated' => 0, 'not_activated' => 0, 'pending' => 0],
        ['date' => '2026-10-18', 'count' => 20, 'returning' => 20, 'activated' => 0, 'not_activated' => 0, 'pending' => 0],
        ['date' => '2026-10-19', 'count' => 30, 'returning' => 30, 'activated' => 0, 'not_activated' => 0, 'pending' => 0],
    ]);
});

it('splits each day into returning people and the signups of the day, activated, not, or too young to tell', function (): void {
    DB::table('active_user_counts')->insert([
        ['seen_at' => '2026-10-18', 'count' => 10],
        ['seen_at' => '2026-10-19', 'count' => 8],
    ]);

    $other = User::factory()->create(['created_at' => '2026-09-01 00:00:00']);
    $author = User::factory()->create(['created_at' => '2026-10-18 08:00:00']);
    User::factory()->create(['created_at' => '2026-10-18 09:00:00']);
    User::factory()->create(['created_at' => '2026-10-18 23:00:00']);
    User::factory()->create(['github_id' => CreateTomFriendship::GITHUB_ID, 'created_at' => '2026-10-18 10:00:00']);
    User::factory()->create(['created_at' => '2026-10-19 00:00:00']);
    User::factory()->create(['created_at' => '2026-10-19 00:00:01']);

    Commit::factory()->for($author)->create(['created_at' => '2026-10-18 09:00:00'])
        ->stars()->attach($other, ['created_at' => '2026-10-18 10:00:00', 'updated_at' => '2026-10-18 10:00:00']);

    expect(resolve(ActiveUserCountsQuery::class)->get(CarbonImmutable::parse('2026-10-18'), CarbonImmutable::parse('2026-10-19')))->toBe([
        ['date' => '2026-10-18', 'count' => 10, 'returning' => 7, 'activated' => 1, 'not_activated' => 2, 'pending' => 0],
        ['date' => '2026-10-19', 'count' => 8, 'returning' => 6, 'activated' => 0, 'not_activated' => 1, 'pending' => 1],
    ]);
});

it('counts nobody as returning on a day with more signups than people seen', function (): void {
    DB::table('active_user_counts')->insert(['seen_at' => '2026-10-18', 'count' => 1]);
    User::factory()->count(2)->create(['created_at' => '2026-10-18 01:00:00']);

    expect(resolve(ActiveUserCountsQuery::class)->get(CarbonImmutable::parse('2026-10-18'), CarbonImmutable::parse('2026-10-18')))
        ->sequence(fn ($day) => $day->returning->toBe(0)->not_activated->toBe(2));
});
