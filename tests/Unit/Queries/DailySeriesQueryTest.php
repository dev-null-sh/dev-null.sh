<?php

declare(strict_types=1);

use App\Actions\CreateTomFriendship;
use App\Models\Commit;
use App\Models\User;
use App\Queries\DailySeriesQuery;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->travelTo(now()->setDate(2026, 10, 20)->setTime(12, 0));
});

it('lists every day of the range, oldest first, with nothing counted on quiet days', function (): void {
    expect(resolve(DailySeriesQuery::class)->get(CarbonImmutable::parse('2026-10-18'), CarbonImmutable::parse('2026-10-19')))->toBe([
        ['date' => '2026-10-18', 'signups' => 0, 'commits' => 0, 'replies' => 0, 'active_users' => 0, 'stars' => 0, 'follows' => 0, 'failed_jobs' => 0],
        ['date' => '2026-10-19', 'signups' => 0, 'commits' => 0, 'replies' => 0, 'active_users' => 0, 'stars' => 0, 'follows' => 0, 'failed_jobs' => 0],
    ]);
});

it('counts what happened each utc day, deleted and pending commits included', function (): void {
    $alice = User::factory()->create(['created_at' => '2026-10-19 00:00:00']);
    $bob = User::factory()->create(['created_at' => '2026-10-19 23:59:59']);
    User::factory()->create(['created_at' => '2026-10-18 12:00:00']);
    User::factory()->create(['created_at' => '2026-10-20 00:00:00']);

    $commit = Commit::factory()->for($alice)->create(['created_at' => '2026-10-19 08:00:00']);
    Commit::factory()->for($bob)->pending()->create(['created_at' => '2026-10-19 09:00:00']);
    Commit::factory()->for($bob)->create(['created_at' => '2026-10-19 10:00:00'])->delete();
    Commit::factory()->for($bob)->for($commit, 'parent')->create(['created_at' => '2026-10-19 11:00:00']);
    Commit::factory()->for($bob)->create(['created_at' => '2026-10-20 00:00:00']);

    $commit->stars()->attach($bob, ['created_at' => '2026-10-19 12:00:00', 'updated_at' => '2026-10-19 12:00:00']);
    $alice->following()->attach($bob, ['created_at' => '2026-10-19 13:00:00', 'updated_at' => '2026-10-19 13:00:00']);
    $bob->following()->attach($alice, ['created_at' => '2026-10-18 13:00:00', 'updated_at' => '2026-10-18 13:00:00']);

    DB::table('active_user_counts')->insert([['seen_at' => '2026-10-19', 'count' => 7]]);
    failedJob(failedAt: CarbonImmutable::parse('2026-10-19 23:00:00'));
    failedJob(failedAt: CarbonImmutable::parse('2026-10-20 00:00:00'));

    expect(resolve(DailySeriesQuery::class)->get(CarbonImmutable::parse('2026-10-18'), CarbonImmutable::parse('2026-10-19')))->toBe([
        ['date' => '2026-10-18', 'signups' => 1, 'commits' => 0, 'replies' => 0, 'active_users' => 0, 'stars' => 0, 'follows' => 1, 'failed_jobs' => 0],
        ['date' => '2026-10-19', 'signups' => 2, 'commits' => 3, 'replies' => 1, 'active_users' => 7, 'stars' => 1, 'follows' => 1, 'failed_jobs' => 1],
    ]);
});

it('leaves out what bots do', function (): void {
    $tom = User::factory()->create(['github_id' => CreateTomFriendship::GITHUB_ID, 'created_at' => '2026-10-19 08:00:00']);
    $alice = User::factory()->create(['created_at' => '2026-10-01 00:00:00']);

    $commit = Commit::factory()->for($alice)->create(['created_at' => '2026-10-01 00:00:00']);
    Commit::factory()->for($tom)->create(['created_at' => '2026-10-19 09:00:00']);
    Commit::factory()->for($tom)->for($commit, 'parent')->create(['created_at' => '2026-10-19 09:00:00']);
    $commit->stars()->attach($tom, ['created_at' => '2026-10-19 10:00:00', 'updated_at' => '2026-10-19 10:00:00']);
    $tom->following()->attach($alice, ['created_at' => '2026-10-19 10:00:00', 'updated_at' => '2026-10-19 10:00:00']);

    expect(resolve(DailySeriesQuery::class)->get(CarbonImmutable::parse('2026-10-19'), CarbonImmutable::parse('2026-10-19')))->toBe([
        ['date' => '2026-10-19', 'signups' => 0, 'commits' => 0, 'replies' => 0, 'active_users' => 0, 'stars' => 0, 'follows' => 0, 'failed_jobs' => 0],
    ]);
});
