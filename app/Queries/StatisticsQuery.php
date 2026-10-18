<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Commit;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Cache;

/**
 * @phpstan-type Statistics array{users: int, commits: int, activity: list<array{date: string, count: int}>, updatedAt: string}
 */
final readonly class StatisticsQuery
{
    private const int CACHE_SECONDS = 300;

    /**
     * Everyone reads the same public snapshot, including its timestamp. A new UTC day starts a fresh window.
     *
     * @return Statistics
     */
    public function get(): array
    {
        /** @var Statistics */
        return Cache::remember('statistics:'.CarbonImmutable::now('UTC')->toDateString(), self::CACHE_SECONDS, $this->snapshot(...));
    }

    /**
     * Approved, non-deleted commits include replies and quotes, just as on public profile activity graphs.
     * Only daily aggregates are read, never commit bodies or user profiles.
     *
     * @return Statistics
     */
    private function snapshot(): array
    {
        $now = CarbonImmutable::now('UTC');
        $from = $now->startOfDay()->subDays(29);

        $users = User::query()->count();
        $commits = Commit::query()->approved()->where('created_at', '<=', $now)->count();

        /** @var array<string, int|string> $counts */
        $counts = Commit::query()->approved()
            ->whereBetween('created_at', [$from, $now])
            ->toBase()
            ->selectRaw('date(created_at) as bucket, count(*) as aggregate')
            ->groupBy('bucket')
            ->pluck('aggregate', 'bucket')
            ->all();

        return [
            'users' => $users,
            'commits' => $commits,
            'activity' => array_values(array_map(
                fn (CarbonInterface $date): array => ['date' => $date->toDateString(), 'count' => (int) ($counts[$date->toDateString()] ?? 0)],
                CarbonPeriod::create($from, '1 day', $now->startOfDay())->toArray(),
            )),
            'updatedAt' => $now->toIso8601ZuluString(),
        ];
    }
}
