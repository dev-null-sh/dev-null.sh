<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\CommitReport;
use App\Models\LinkPreview;
use App\Models\User;
use App\Queries\ConnectivityQuery;
use App\Queries\OperationsSignalsQuery;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-20 12:00:00'));
});

/**
 * @return array{key: string, area: string, status: string, title: string, detail: string, href: string|null}
 */
function signal(string $key): array
{
    return collect(resolve(OperationsSignalsQuery::class)->get())->firstWhere('key', $key);
}

/**
 * The people seen on each of the 14 days before yesterday, and yesterday.
 *
 * @param  list<int>  $baseline
 */
function activeUsers(array $baseline, int $yesterday): void
{
    $days = [...array_reverse($baseline), $yesterday];

    DB::table('active_user_counts')->insert(array_map(fn (int $count, int $index): array => [
        'seen_at' => CarbonImmutable::parse('2026-10-19')->subDays(count($days) - 1 - $index)->toDateString(),
        'count' => $count,
    ], $days, array_keys($days)));
}

/**
 * Approved top-level commits by someone, written in the given week, of which the given number got a star from someone
 * else within the hour.
 */
function answeredCommits(CarbonImmutable $day, int $commits, int $answered): void
{
    $author = User::factory()->create(['created_at' => $day->subMonth()]);
    $reader = User::factory()->create(['created_at' => $day->subMonth()]);

    Commit::factory()->for($author)->count($commits)->create(['created_at' => $day])
        ->take($answered)
        ->each(fn (Commit $commit) => $commit->stars()->attach($reader, ['created_at' => $day->addHour(), 'updated_at' => $day->addHour()]));
}

it('lists every signal, the failing first, then the warnings', function (): void {
    Commit::factory()->pending()->create(['created_at' => now()->subHours(7)]);
    CommitReport::factory()->create();

    $signals = resolve(OperationsSignalsQuery::class)->get();

    expect(array_column($signals, 'key'))->toBe([
        'moderation_backlog',
        'open_reports',
        'connectivity',
        'queue_backlog',
        'failed_jobs',
        'link_previews',
        'signups',
        'active_users',
        'activation',
        'commits_answered',
    ])
        ->and(array_column($signals, 'status'))->toBe(['fail', 'warn', 'ok', 'ok', 'ok', 'ok', 'ok', 'ok', 'ok', 'ok']);
});

it('passes when the database and the cache are reachable', function (): void {
    expect(signal('connectivity'))->toBe([
        'key' => 'connectivity',
        'area' => 'infrastructure',
        'status' => 'ok',
        'title' => 'Database and cache reachable',
        'detail' => 'database ok · cache ok',
        'href' => '/operations/health',
    ]);
});

it('fails when the cache cannot be reached', function (): void {
    $cache = Mockery::mock(Repository::class);
    $cache->shouldReceive('get')->andThrow(new RuntimeException('Connection refused'));
    $this->instance(ConnectivityQuery::class, new ConnectivityQuery(resolve(ConnectionResolverInterface::class), $cache));

    expect(signal('connectivity'))->toMatchArray([
        'status' => 'fail',
        'title' => 'Cache unreachable',
        'detail' => 'database ok · cache down',
    ]);
});

it('judges the queues by the job that has waited the longest', function (?int $minutes, string $status, string $title, string $detail): void {
    queuedJob('default', now()->subMinute());

    if ($minutes !== null) {
        queuedJob('previews', now()->subMinutes($minutes));
    }

    expect(signal('queue_backlog'))->toMatchArray(['area' => 'queues', 'status' => $status, 'title' => $title, 'detail' => $detail]);
})->with([
    'fresh' => [null, 'ok', 'Oldest job waiting 1m', '1 queued · oldest on default'],
    'five minutes' => [5, 'warn', 'Oldest job waiting 5m', '2 queued · oldest on previews'],
    'half an hour' => [30, 'fail', 'Oldest job waiting 30m', '2 queued · oldest on previews'],
]);

it('passes when no job is queued', function (): void {
    expect(signal('queue_backlog'))->toMatchArray(['status' => 'ok', 'title' => 'Queues empty', 'detail' => '0 queued']);
});

it('warns of any failed job, and fails on a spike', function (int $last, int $previous, string $status, string $title): void {
    foreach (range(1, $last) as $_) {
        failedJob(failedAt: now()->subHour());
    }

    foreach (range(1, $previous) as $_) {
        failedJob(failedAt: now()->subHours(30));
    }

    expect(signal('failed_jobs'))->toMatchArray([
        'status' => $status,
        'title' => $title,
        'detail' => "{$last} in 24h · {$previous} the 24h before",
    ]);
})->with([
    'one' => [1, 1, 'warn', '1 failed job in 24h'],
    'many, as usual' => [10, 4, 'warn', '10 failed jobs in 24h'],
    'a spike' => [10, 3, 'fail', '10 failed jobs in 24h'],
]);

it('passes without failed jobs', function (): void {
    expect(signal('failed_jobs'))->toMatchArray(['status' => 'ok', 'title' => 'No failed jobs in 24h']);
});

it('warns when link previews back up', function (): void {
    LinkPreview::factory()->count(50)->create();
    LinkPreview::factory()->failed()->create();

    expect(signal('link_previews'))->toMatchArray([
        'status' => 'warn',
        'title' => 'Link previews backing up',
        'detail' => '50 pending · 1 failed in 24h',
    ]);
});

it('passes while link previews keep up', function (): void {
    LinkPreview::factory()->count(49)->create();

    expect(signal('link_previews'))->toMatchArray(['status' => 'ok', 'title' => 'Link previews keeping up']);
});

it('judges the moderation queue by the commit that has waited the longest', function (int $minutes, string $status): void {
    Commit::factory()->pending()->create(['created_at' => now()->subMinutes($minutes)]);
    Commit::factory()->pending()->create();

    expect(signal('moderation_backlog'))->toMatchArray(['status' => $status, 'title' => '2 commits awaiting review', 'href' => '/moderation']);
})->with([
    'fresh' => [59, 'ok'],
    'an hour' => [60, 'warn'],
    'six hours' => [360, 'fail'],
]);

it('passes with an empty moderation queue', function (): void {
    expect(signal('moderation_backlog'))->toMatchArray(['status' => 'ok', 'title' => 'Moderation queue empty', 'detail' => '0 pending']);
});

it('says how long the oldest pending commit has waited', function (): void {
    Commit::factory()->pending()->create(['created_at' => now()->subMinutes(90)->subSeconds(30)]);

    expect(signal('moderation_backlog'))->toMatchArray(['title' => '1 commit awaiting review', 'detail' => '1 pending · oldest 1h 30m']);
});

it('warns of open reports', function (): void {
    CommitReport::factory()->count(2)->create();

    expect(signal('open_reports'))->toMatchArray(['status' => 'warn', 'title' => '2 open reports', 'detail' => '2 open', 'href' => '/moderation/reports']);
});

it('passes without open reports', function (): void {
    expect(signal('open_reports'))->toMatchArray(['status' => 'ok', 'title' => 'No open reports']);
});

it('compares the people seen yesterday with the 14 days before', function (int $yesterday, string $status, string $title, string $detail): void {
    activeUsers(array_fill(0, 14, 10), $yesterday);

    expect(signal('active_users'))->toMatchArray([
        'area' => 'growth',
        'status' => $status,
        'title' => $title,
        'detail' => $detail,
        'href' => '/operations/metrics',
    ]);
})->with([
    'as usual' => [10, 'ok', 'Active users yesterday normal', '10 yesterday · 10.0 ± 3.2 over 14d · z +0.0'],
    'a spike' => [40, 'ok', 'Active users yesterday normal', '40 yesterday · 10.0 ± 3.2 over 14d · z +9.5'],
    'below' => [6, 'warn', 'Active users yesterday below normal', '6 yesterday · 10.0 ± 3.2 over 14d · z -1.3'],
    'far below' => [3, 'fail', 'Active users yesterday far below normal', '3 yesterday · 10.0 ± 3.2 over 14d · z -2.2'],
]);

it('measures the spread of the days before yesterday', function (): void {
    activeUsers([10, 30, 10, 30, 10, 30, 10, 30, 10, 30, 10, 30, 10, 30], 10);

    expect(signal('active_users'))->toMatchArray([
        'status' => 'ok',
        'detail' => '10 yesterday · 20.0 ± 10.4 over 14d · z -1.0',
    ]);
});

it('waits for enough history before judging a day', function (array $baseline, string $detail): void {
    activeUsers($baseline, 0);

    expect(signal('active_users'))->toMatchArray([
        'status' => 'ok',
        'title' => 'Active users yesterday: not enough data yet',
        'detail' => $detail,
    ]);
})->with([
    'under a week' => [[10, 10, 10, 10, 10, 10, 0, 0, 0, 0, 0, 0, 0, 0], '0 yesterday · 6 days of history'],
    'too few people' => [array_fill(0, 14, 4), '0 yesterday · 14 days of history'],
]);

it('compares the signups of yesterday with the 14 days before', function (): void {
    foreach (range(2, 8) as $daysAgo) {
        User::factory()->count(5)->create(['created_at' => now()->subDays($daysAgo)]);
    }

    expect(signal('signups'))->toMatchArray([
        'status' => 'fail',
        'title' => 'Signups yesterday far below normal',
        'detail' => '0 yesterday · 5.0 ± 2.2 over 7d · z -2.2',
        'href' => '/operations/activity',
    ]);
});

it('compares the commits answered in the last 7 days with the 7 before', function (int $answered, string $status, string $title, string $detail): void {
    answeredCommits(now()->subDays(10)->toImmutable(), 30, 24);
    answeredCommits(now()->subDays(3)->toImmutable(), 30, $answered);

    expect(signal('commits_answered'))->toMatchArray([
        'area' => 'engagement',
        'status' => $status,
        'title' => $title,
        'detail' => $detail,
    ]);
})->with([
    'as often' => [24, 'ok', 'Commits answered holding', '80.0% in 7d · 80.0% the 7d before · 30 commits'],
    'five points less' => [22, 'warn', 'Commits answered down', '73.3% in 7d · 80.0% the 7d before · 30 commits'],
    'ten points less' => [21, 'fail', 'Commits answered down', '70.0% in 7d · 80.0% the 7d before · 30 commits'],
]);

it('waits for enough commits before comparing how many were answered', function (): void {
    answeredCommits(now()->subDays(10)->toImmutable(), 30, 30);
    answeredCommits(now()->subDays(3)->toImmutable(), 29, 0);

    expect(signal('commits_answered'))->toMatchArray([
        'status' => 'ok',
        'title' => 'Commits answered: not enough data yet',
        'detail' => '29 commits in 7d · 30 the 7d before',
    ]);
});

it('compares the activation of the last 7 days of signups with the 7 before', function (): void {
    $reader = User::factory()->create(['created_at' => now()->subMonth()]);

    foreach ([10 => 30, 3 => 20] as $daysAgo => $activated) {
        User::factory()->count(30)->create(['created_at' => now()->subDays($daysAgo)])
            ->take($activated)
            ->each(fn (User $user) => Commit::factory()->for($user)->create(['created_at' => now()->subDays($daysAgo)->addHour()])
                ->stars()->attach($reader, ['created_at' => now()->subDays($daysAgo)->addHours(2), 'updated_at' => now()->subDays($daysAgo)->addHours(2)]));
    }

    expect(signal('activation'))->toMatchArray([
        'status' => 'fail',
        'title' => 'Activation down',
        'detail' => '66.7% in 7d · 100.0% the 7d before · 30 signups',
        'href' => '/operations/metrics',
    ]);
});
