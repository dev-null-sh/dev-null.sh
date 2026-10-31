<?php

declare(strict_types=1);

use App\Actions\CreateTomFriendship;
use App\Models\User;
use App\Queries\RetentionCohortsQuery;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->travelTo(now()->setDate(2026, 11, 5)->setTime(12, 0));
});

function activeOn(User $user, string ...$days): void
{
    DB::table('user_active_days')->insert(array_map(fn (string $day): array => ['user_id' => $user->id, 'seen_at' => $day], $days));
}

it('splits the signups by week and counts the ones seen again on each day of the table, among the ones who reached it', function (): void {
    activeOn(User::factory()->create(['created_at' => '2026-10-26 10:00:00']), '2026-10-27', '2026-11-02');
    activeOn(User::factory()->create(['created_at' => '2026-11-01 10:00:00']), '2026-11-02');
    activeOn(User::factory()->create(['created_at' => '2026-11-02 10:00:00']), '2026-11-03', '2026-11-05');
    User::factory()->create(['github_id' => CreateTomFriendship::GITHUB_ID, 'created_at' => '2026-10-26 10:00:00']);

    $result = resolve(RetentionCohortsQuery::class)->get(CarbonImmutable::parse('2026-10-26'), CarbonImmutable::parse('2026-11-05'));

    expect($result['cohorts'])->toBe([
        ['week' => '2026-10-26', 'signups' => 2, 'days' => [
            ['day' => 1, 'eligible' => 2, 'retained' => 2, 'backfilled' => 1],
            ['day' => 3, 'eligible' => 2, 'retained' => 0, 'backfilled' => 1],
            ['day' => 7, 'eligible' => 1, 'retained' => 1, 'backfilled' => 0],
            ['day' => 14, 'eligible' => 0, 'retained' => 0, 'backfilled' => 0],
            ['day' => 30, 'eligible' => 0, 'retained' => 0, 'backfilled' => 0],
        ]],
        ['week' => '2026-11-02', 'signups' => 1, 'days' => [
            ['day' => 1, 'eligible' => 1, 'retained' => 1, 'backfilled' => 0],
            ['day' => 3, 'eligible' => 0, 'retained' => 0, 'backfilled' => 0],
            ['day' => 7, 'eligible' => 0, 'retained' => 0, 'backfilled' => 0],
            ['day' => 14, 'eligible' => 0, 'retained' => 0, 'backfilled' => 0],
            ['day' => 30, 'eligible' => 0, 'retained' => 0, 'backfilled' => 0],
        ]],
    ]);
});

it('pools the cohorts into a curve of every day someone reached, up to the last of the table', function (): void {
    activeOn(User::factory()->create(['created_at' => '2026-10-26 10:00:00']), '2026-10-27', '2026-10-28');
    activeOn(User::factory()->create(['created_at' => '2026-11-02 10:00:00']), '2026-11-03');

    expect(array_slice(resolve(RetentionCohortsQuery::class)->get(CarbonImmutable::parse('2026-10-26'), CarbonImmutable::parse('2026-11-05'))['curve'], 0, 3))->toBe([
        ['day' => 1, 'eligible' => 2, 'retained' => 2, 'backfilled' => 1],
        ['day' => 2, 'eligible' => 2, 'retained' => 1, 'backfilled' => 1],
        ['day' => 3, 'eligible' => 1, 'retained' => 0, 'backfilled' => 1],
    ]);
});

it('has no cohort and no curve before anyone reached day 1', function (): void {
    User::factory()->create(['created_at' => '2026-11-05 10:00:00']);

    expect(resolve(RetentionCohortsQuery::class)->get(CarbonImmutable::parse('2026-11-05'), CarbonImmutable::parse('2026-11-05')))
        ->toBe(['cohorts' => [['week' => '2026-11-02', 'signups' => 1, 'days' => [
            ['day' => 1, 'eligible' => 0, 'retained' => 0, 'backfilled' => 0],
            ['day' => 3, 'eligible' => 0, 'retained' => 0, 'backfilled' => 0],
            ['day' => 7, 'eligible' => 0, 'retained' => 0, 'backfilled' => 0],
            ['day' => 14, 'eligible' => 0, 'retained' => 0, 'backfilled' => 0],
            ['day' => 30, 'eligible' => 0, 'retained' => 0, 'backfilled' => 0],
        ]]], 'curve' => []]);
});
