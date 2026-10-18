<?php

declare(strict_types=1);

use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\User;
use App\Queries\StatisticsQuery;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-18 12:34:56', 'UTC'));
});

it('returns zero totals and thirty ordered days when the community is empty', function (): void {
    $statistics = resolve(StatisticsQuery::class)->get();

    expect($statistics)->toHaveKeys(['users', 'commits', 'activity', 'updatedAt'])
        ->users->toBe(0)
        ->commits->toBe(0)
        ->updatedAt->toBe('2026-10-18T12:34:56Z')
        ->activity->toHaveCount(30)->each(fn ($day) => $day->count->toBe(0))
        ->and($statistics['activity'][0]['date'])->toBe('2026-09-19')
        ->and($statistics['activity'][29]['date'])->toBe('2026-10-18');

    expect(array_column($statistics['activity'], 'date'))->toBe(
        array_map(fn (int $day): string => now()->subDays(29 - $day)->toDateString(), range(0, 29)),
    );
});

it('counts current users and only public commits including replies and quotes', function (): void {
    $user = User::factory()->create();
    User::factory()->create();
    User::factory()->create()->delete();

    $parent = Commit::factory()->for($user)->create();
    Commit::factory()->for($user)->for($parent, 'parent')->create();
    Commit::factory()->for($user)->for($parent, 'quotedCommit')->create();
    Commit::factory()->for($user)->pending()->create();
    Commit::factory()->for($user)->rejected()->create();
    Commit::factory()->for($user)->create()->delete();

    $statistics = resolve(StatisticsQuery::class)->get();

    expect($statistics['users'])->toBe(2)
        ->and($statistics['commits'])->toBe(3)
        ->and($statistics['activity'][29])->toBe(['date' => '2026-10-18', 'count' => 3])
        ->and(array_sum(array_column($statistics['activity'], 'count')))->toBe(3);
});

it('bounds daily activity by the first UTC midnight and the snapshot instant', function (): void {
    $user = User::factory()->create();

    foreach (['2026-09-18 23:59:59', '2026-09-19 00:00:00', '2026-10-17 23:59:59', '2026-10-18 00:00:00', '2026-10-18 12:34:56', '2026-10-18 12:34:57'] as $date) {
        Commit::factory()->for($user)->create(['created_at' => $date]);
    }

    $statistics = resolve(StatisticsQuery::class)->get();

    expect($statistics['commits'])->toBe(5)
        ->and($statistics['activity'][0])->toBe(['date' => '2026-09-19', 'count' => 1])
        ->and($statistics['activity'][1])->toBe(['date' => '2026-09-20', 'count' => 0])
        ->and($statistics['activity'][28])->toBe(['date' => '2026-10-17', 'count' => 1])
        ->and($statistics['activity'][29])->toBe(['date' => '2026-10-18', 'count' => 2])
        ->and(array_sum(array_column($statistics['activity'], 'count')))->toBe(4);
});

it('uses three aggregate queries on a cold cache and none on a warm cache', function (): void {
    Commit::factory()->count(3)->create();

    DB::flushQueryLog();
    DB::enableQueryLog();

    $query = resolve(StatisticsQuery::class);
    $statistics = $query->get();
    $queries = array_column(DB::getQueryLog(), 'query');

    DB::flushQueryLog();

    expect($query->get())->toBe($statistics)
        ->and(DB::getQueryLog())->toBe([]);

    DB::disableQueryLog();

    expect($queries)->toHaveCount(3)->each(fn ($sql) => $sql
        ->toContain('count(')
        ->not->toContain('select *', 'body', 'email', 'embedding'));
});

it('caches the timestamp with the counts and refreshes them together after five minutes', function (): void {
    $user = User::factory()->create();
    $commit = Commit::factory()->for($user)->create();
    $query = resolve(StatisticsQuery::class);
    $snapshot = $query->get();

    User::factory()->create();
    Commit::factory()->for($user)->count(2)->create();
    $commit->delete();
    $this->travel(299)->seconds();

    expect($query->get())->toBe($snapshot);

    $this->travel(2)->seconds();
    $refreshed = $query->get();

    expect($refreshed['users'])->toBe(2)
        ->and($refreshed['commits'])->toBe(2)
        ->and($refreshed['activity'][29]['count'])->toBe(2)
        ->and($refreshed['updatedAt'])->toBe('2026-10-18T12:39:57Z');
});

it('removes a rejected commit from the next snapshot', function (): void {
    $commit = Commit::factory()->create();
    $query = resolve(StatisticsQuery::class);

    expect($query->get()['commits'])->toBe(1);

    $commit->update(['status' => CommitStatus::Rejected]);
    $this->travel(301)->seconds();

    expect($query->get()['commits'])->toBe(0)
        ->and($query->get()['activity'][29]['count'])->toBe(0);
});

it('starts a fresh UTC day without waiting for the previous cache to expire', function (string $before, string $first, string $last): void {
    $this->travelTo(CarbonImmutable::parse($before, 'UTC'));
    $commit = Commit::factory()->create();
    $query = resolve(StatisticsQuery::class);
    $snapshot = $query->get();

    $this->travel(2)->seconds();
    $next = $query->get();

    expect($next['updatedAt'])->not->toBe($snapshot['updatedAt'])
        ->and($next['activity'])->toHaveCount(30)
        ->and($next['activity'][0]['date'])->toBe($first)
        ->and($next['activity'][28])->toBe(['date' => $commit->created_at->toDateString(), 'count' => 1])
        ->and($next['activity'][29])->toBe(['date' => $last, 'count' => 0]);
})->with([
    'a month boundary' => ['2026-10-31 23:59:59', '2026-10-03', '2026-11-01'],
    'a year boundary' => ['2026-12-31 23:59:59', '2026-12-03', '2027-01-01'],
    'a leap day' => ['2024-02-28 23:59:59', '2024-01-31', '2024-02-29'],
]);
