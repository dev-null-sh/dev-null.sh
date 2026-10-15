<?php

declare(strict_types=1);

namespace App\Queries;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class FailedJobsQuery
{
    /**
     * @return array{total: int, recent: list<array{uuid: string, name: string, queue: string, exception: string, failed_at: string}>}
     */
    public function get(int $limit = 10): array
    {
        /** @var list<object{uuid: string, queue: string, payload: string, exception: string, failed_at: string}> $recent */
        $recent = $this->failedJobs()
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['uuid', 'queue', 'payload', 'exception', 'failed_at'])
            ->all();

        return [
            'total' => $this->failedJobs()->count(),
            'recent' => array_map(fn (object $failedJob): array => [
                'uuid' => $failedJob->uuid,
                'name' => $this->name($failedJob->payload),
                'queue' => $failedJob->queue,
                'exception' => Str::limit(Str::before($failedJob->exception, "\n"), 300),
                'failed_at' => CarbonImmutable::parse($failedJob->failed_at)->toIso8601String(),
            ], $recent),
        ];
    }

    private function failedJobs(): Builder
    {
        return DB::connection(config()->string('queue.failed.database'))->table(config()->string('queue.failed.table'));
    }

    private function name(string $payload): string
    {
        /** @var array{displayName?: string, job?: string} $decoded */
        $decoded = json_decode($payload, true);

        return $decoded['displayName'] ?? $decoded['job'] ?? 'Unknown job';
    }
}
