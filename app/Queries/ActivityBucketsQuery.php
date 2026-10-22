<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\ActivityScale;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Database\Query\Builder;

/**
 * @phpstan-type ActivityBucket array{date: string, count: int}
 */
final readonly class ActivityBucketsQuery
{
    /**
     * One bucket per hour or day from the one the window starts in to the current one, oldest first, counting the rows
     * created since the start of the window. Hourly buckets are keyed by their UTC start instant so the client can
     * label them in local time, while daily buckets are UTC dates.
     *
     * @return list<ActivityBucket>
     */
    public function get(Builder $rows, ActivityScale $scale, CarbonImmutable $from, CarbonImmutable $now): array
    {
        $bucket = $scale->isHourly()
            ? "to_char(date_trunc('hour', created_at), 'YYYY-MM-DD\"T\"HH24\":00:00Z\"')"
            : 'date(created_at)';

        $counts = $rows
            ->where('created_at', '>=', $from)
            ->selectRaw("{$bucket} as bucket, count(*) as aggregate")
            ->groupBy('bucket')
            ->pluck('aggregate', 'bucket')
            ->map(fn (mixed $count): int => is_numeric($count) ? (int) $count : 0)
            ->all();

        $period = $scale->isHourly()
            ? CarbonPeriod::create($from->startOfHour(), '1 hour', $now->startOfHour())
            : CarbonPeriod::create($from->startOfDay(), '1 day', $now->startOfDay());

        return array_values(array_map(
            function (CarbonInterface $start) use ($scale, $counts): array {
                $date = $scale->isHourly() ? $start->toIso8601ZuluString() : $start->toDateString();

                return ['date' => $date, 'count' => $counts[$date] ?? 0];
            },
            $period->toArray(),
        ));
    }
}
