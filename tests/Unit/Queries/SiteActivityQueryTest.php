<?php

declare(strict_types=1);

use App\Enums\ActivityMetric;
use App\Enums\ActivityScale;
use App\Models\Commit;
use App\Models\User;
use App\Queries\SiteActivityQuery;

beforeEach(function (): void {
    $this->travelTo(now()->setDate(2026, 10, 15)->setTime(12, 30));
});

it('counts the top-level commits of everyone per day of the last 7 days, whatever their status since, deleted ones included', function (): void {
    $commit = Commit::factory()->create();
    Commit::factory()->pending()->create();
    Commit::factory()->rejected()->create(['created_at' => '2026-10-12 23:59:59']);
    Commit::factory()->create(['created_at' => '2026-10-08 12:30:00'])->delete();
    Commit::factory()->create(['created_at' => '2026-10-08 12:29:59']);
    Commit::factory()->for($commit, 'parent')->create();

    expect(resolve(SiteActivityQuery::class)->get(ActivityMetric::Commits, ActivityScale::Week))->toBe([
        'metric' => ActivityMetric::Commits,
        'scale' => ActivityScale::Week,
        'buckets' => [
            ['date' => '2026-10-08', 'count' => 1],
            ['date' => '2026-10-09', 'count' => 0],
            ['date' => '2026-10-10', 'count' => 0],
            ['date' => '2026-10-11', 'count' => 0],
            ['date' => '2026-10-12', 'count' => 1],
            ['date' => '2026-10-13', 'count' => 0],
            ['date' => '2026-10-14', 'count' => 0],
            ['date' => '2026-10-15', 'count' => 2],
        ],
    ]);
});

it('counts the replies of everyone apart from the top-level commits', function (): void {
    $commit = Commit::factory()->create();
    Commit::factory()->for($commit, 'parent')->create();
    Commit::factory()->for($commit, 'parent')->pending()->create(['created_at' => '2026-10-10 08:00:00']);
    Commit::factory()->for($commit, 'parent')->create()->delete();

    $activity = resolve(SiteActivityQuery::class)->get(ActivityMetric::Replies, ActivityScale::Week);

    expect($activity['metric'])->toBe(ActivityMetric::Replies)
        ->and(array_sum(array_column($activity['buckets'], 'count')))->toBe(3)
        ->and($activity['buckets'][2])->toBe(['date' => '2026-10-10', 'count' => 1])
        ->and($activity['buckets'][7])->toBe(['date' => '2026-10-15', 'count' => 2]);
});

it('counts the signups', function (): void {
    User::factory()->count(2)->create();
    User::factory()->create(['created_at' => '2026-10-09 23:00:00']);
    User::factory()->create(['created_at' => '2026-10-07 23:00:00']);

    $activity = resolve(SiteActivityQuery::class)->get(ActivityMetric::Signups, ActivityScale::Week);

    expect($activity['metric'])->toBe(ActivityMetric::Signups)
        ->and(array_sum(array_column($activity['buckets'], 'count')))->toBe(3)
        ->and($activity['buckets'][1])->toBe(['date' => '2026-10-09', 'count' => 1])
        ->and($activity['buckets'][7])->toBe(['date' => '2026-10-15', 'count' => 2]);
});

it('counts the commits of each utc hour of the last 24 hours, the first one from the moment the window starts', function (): void {
    Commit::factory()->count(2)->create();
    Commit::factory()->create(['created_at' => '2026-10-14 12:30:00']);
    Commit::factory()->create(['created_at' => '2026-10-14 12:29:59']);

    $activity = resolve(SiteActivityQuery::class)->get(ActivityMetric::Commits, ActivityScale::Day);

    expect($activity['scale'])->toBe(ActivityScale::Day)
        ->and($activity['buckets'])->toHaveCount(25)
        ->and($activity['buckets'][0])->toBe(['date' => '2026-10-14T12:00:00Z', 'count' => 1])
        ->and($activity['buckets'][1])->toBe(['date' => '2026-10-14T13:00:00Z', 'count' => 0])
        ->and($activity['buckets'][24])->toBe(['date' => '2026-10-15T12:00:00Z', 'count' => 2]);
});

it('counts the commits of each day of the given scale', function (ActivityScale $scale, int $buckets, string $first): void {
    Commit::factory()->create();

    $activity = resolve(SiteActivityQuery::class)->get(ActivityMetric::Commits, $scale);

    expect($activity['scale'])->toBe($scale)
        ->and($activity['buckets'])->toHaveCount($buckets)
        ->and($activity['buckets'][0])->toBe(['date' => $first, 'count' => 0])
        ->and($activity['buckets'][$buckets - 1])->toBe(['date' => '2026-10-15', 'count' => 1]);
})->with([
    'month' => [ActivityScale::Month, 31, '2026-09-15'],
    'year' => [ActivityScale::Year, 369, '2025-10-12'],
]);
