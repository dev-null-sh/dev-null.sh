<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\ActivityScale;
use App\Models\Commit;
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
    public function get(User $user, ?User $viewer): array
    {
        $now = CarbonImmutable::now();

        $firstCommitAt = $user->commits()->visibleTo($viewer)->min('created_at');

        $scale = ActivityScale::since(
            is_string($firstCommitAt) ? CarbonImmutable::parse($firstCommitAt) : $user->created_at,
            $now,
        );

        $from = $scale->start($now);

        $commits = $user->commits()
            ->visibleTo($viewer)
            ->where('created_at', '>=', $from);

        $counts = $scale->isHourly()
            ? $commits->get(['created_at'])
                ->countBy(fn (Commit $commit): string => $commit->created_at->utc()->startOfHour()->toIso8601ZuluString())
                ->all()
            : $commits->selectRaw('date(created_at) as bucket, count(*) as aggregate')
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
