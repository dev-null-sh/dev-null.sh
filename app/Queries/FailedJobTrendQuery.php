<?php

declare(strict_types=1);

namespace App\Queries;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Fluent;

/**
 * @phpstan-type FailureTrend array{last_24h: int, previous_24h: int, hours: list<array{at: string, count: int}>, by_job: list<array{name: string, count: int, last_failed_at: string}>}
 */
final readonly class FailedJobTrendQuery
{
    private const int TOP_JOBS = 8;

    /**
     * The jobs that failed in the last 24 hours against the 24 before, hour by hour up to the current one, and the jobs
     * that failed the most in those 24 hours.
     *
     * @return FailureTrend
     */
    public function get(): array
    {
        $now = CarbonImmutable::now();
        $firstHour = $now->startOfHour()->subHours(23);

        /** @var object{last_24h: int, previous_24h: int} $totals */
        $totals = $this->failedJobs()
            ->where('failed_at', '>=', $now->subDays(2))
            ->selectRaw('count(*) filter (where failed_at >= ?) as last_24h, count(*) filter (where failed_at < ?) as previous_24h', [$now->subDay(), $now->subDay()])
            ->first();

        $hours = $this->failedJobs()
            ->where('failed_at', '>=', $firstHour)
            ->selectRaw("date_trunc('hour', failed_at)::timestamp(0)::text as hour, count(*) as count")
            ->groupBy('hour')
            ->get()
            ->mapInto(Fluent::class)
            ->mapWithKeys(fn (Fluent $hour): array => [$hour->string('hour')->value() => $hour->integer('count')]);

        /** @var list<object{name: string, count: int, last_failed_at: string}> $jobs */
        $jobs = $this->failedJobs()
            ->where('failed_at', '>=', $now->subDay())
            ->selectRaw("coalesce(payload::jsonb->>'displayName', payload::jsonb->>'job', 'Unknown job') as name, count(*) as count, max(failed_at) as last_failed_at")
            ->groupBy('name')
            ->orderByDesc('count')
            ->latest('last_failed_at')
            ->limit(self::TOP_JOBS)
            ->get()
            ->all();

        return [
            'last_24h' => (int) $totals->last_24h,
            'previous_24h' => (int) $totals->previous_24h,
            'hours' => array_map(function (int $hour) use ($firstHour, $hours): array {
                $at = $firstHour->addHours($hour);

                return ['at' => $at->toIso8601String(), 'count' => $hours->get($at->toDateTimeString(), 0)];
            }, range(0, 23)),
            'by_job' => array_map(fn (object $job): array => [
                'name' => $job->name,
                'count' => (int) $job->count,
                'last_failed_at' => CarbonImmutable::parse($job->last_failed_at)->toIso8601String(),
            ], $jobs),
        ];
    }

    private function failedJobs(): Builder
    {
        return DB::connection(config()->string('queue.failed.database'))->table(config()->string('queue.failed.table'));
    }
}
