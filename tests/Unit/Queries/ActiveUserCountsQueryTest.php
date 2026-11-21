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
        ['date' => '2026-10-17', 'count' => 10, 'regular' => 10, 'back' => 0, 'lost' => 0, 'lost_complete' => false, 'backfilled' => true, 'activated' => 0, 'not_activated' => 0, 'pending' => 0],
        ['date' => '2026-10-18', 'count' => 20, 'regular' => 20, 'back' => 0, 'lost' => 0, 'lost_complete' => false, 'backfilled' => true, 'activated' => 0, 'not_activated' => 0, 'pending' => 0],
        ['date' => '2026-10-19', 'count' => 30, 'regular' => 30, 'back' => 0, 'lost' => null, 'lost_complete' => false, 'backfilled' => true, 'activated' => 0, 'not_activated' => 0, 'pending' => 0],
    ]);
});

it('splits each day into the people who came back and the signups of the day, activated, not, or too young to tell', function (): void {
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
        ['date' => '2026-10-18', 'count' => 10, 'regular' => 7, 'back' => 0, 'lost' => 0, 'lost_complete' => false, 'backfilled' => true, 'activated' => 1, 'not_activated' => 2, 'pending' => 0],
        ['date' => '2026-10-19', 'count' => 8, 'regular' => 6, 'back' => 0, 'lost' => null, 'lost_complete' => false, 'backfilled' => true, 'activated' => 0, 'not_activated' => 1, 'pending' => 1],
    ]);
});

it('counts nobody as coming back on a day with more signups than people seen', function (): void {
    DB::table('active_user_counts')->insert(['seen_at' => '2026-10-18', 'count' => 1]);
    User::factory()->count(2)->create(['created_at' => '2026-10-18 01:00:00']);

    expect(resolve(ActiveUserCountsQuery::class)->get(CarbonImmutable::parse('2026-10-18'), CarbonImmutable::parse('2026-10-18')))
        ->sequence(fn ($day) => $day->regular->toBe(0)->back->toBe(0)->not_activated->toBe(2));
});

it('splits the people who came back into the ones back after a week away and regulars, with the people lost', function (): void {
    $this->travelTo(now()->setDate(2026, 11, 20)->setTime(12, 0));
    DB::table('active_user_counts')->insert(['seen_at' => '2026-11-18', 'count' => 5]);
    $back = User::factory()->create(['created_at' => '2026-11-01 10:00:00']);
    $lost = User::factory()->create(['created_at' => '2026-11-01 10:00:00']);
    DB::table('user_active_days')->insert([
        ['user_id' => $back->id, 'seen_at' => '2026-11-01'],
        ['user_id' => $back->id, 'seen_at' => '2026-11-18'],
        ['user_id' => $lost->id, 'seen_at' => '2026-11-17'],
    ]);

    expect(resolve(ActiveUserCountsQuery::class)->get(CarbonImmutable::parse('2026-11-18'), CarbonImmutable::parse('2026-11-18')))->toBe([
        ['date' => '2026-11-18', 'count' => 5, 'regular' => 4, 'back' => 1, 'lost' => 1, 'lost_complete' => false, 'backfilled' => false, 'activated' => 0, 'not_activated' => 0, 'pending' => 0],
    ]);
});
