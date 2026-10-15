<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\ActivityScale;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;

final readonly class UserActivityQuery
{
    /**
     * Hourly buckets are keyed by their UTC start instant so the client can label them in local time, while daily buckets are UTC dates.
     *
     * @return array{scale: ActivityScale, buckets: list<array{date: string, count: int}>}
     */
    public function get(User $user, User $viewer): array
    {
        $now = CarbonImmutable::now();

        $firstCommitAt = $user->commits()->visibleTo($viewer)->min('created_at');

        $scale = ActivityScale::since(
            is_string($firstCommitAt) ? CarbonImmutable::parse($firstCommitAt) : $user->created_at,
            $now,
        );

        $from = $scale->start($now);

        $counts = $user->commits()
            ->visibleTo($viewer)
            ->where('created_at', '>=', $from)
            ->selectRaw('strftime(?, created_at) as bucket, count(*) as aggregate', [
                $scale->isHourly() ? '%Y-%m-%dT%H:00:00Z' : '%Y-%m-%d',
            ])
            ->groupBy('bucket')
            ->pluck('aggregate', 'bucket')
            ->map(fn (mixed $count): int => is_numeric($count) ? (int) $count : 0)
            ->all();

        $period = $scale->isHourly()
            ? CarbonPeriod::create($from, '1 hour', $now->startOfHour())
            : CarbonPeriod::create($from, '1 day', $now->startOfDay());

        return [
            'scale' => $scale,
            'buckets' => array_values(array_map(
                function (CarbonInterface $start) use ($scale, $counts): array {
                    $date = $scale->isHourly() ? $start->toIso8601ZuluString() : $start->toDateString();

                    return ['date' => $date, 'count' => $counts[$date] ?? 0];
                },
                $period->toArray(),
            )),
        ];
    }
}
