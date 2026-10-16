<?php

declare(strict_types=1);

namespace App\Queries;

use Illuminate\Support\Facades\DB;

final readonly class QueueBacklogQuery
{
    /**
     * Jobs waiting on, or running from, each queue of the database queue connection. The connection's default queue is
     * always listed, so an empty backlog still reads as one.
     *
     * @return list<array{queue: string, size: int, oldest_job_age: int|null}>
     */
    public function get(): array
    {
        /** @var array{connection?: string|null, table: string, queue: string} $config */
        $config = config('queue.connections.database');

        /** @var array<string, object{size: int, oldest_created_at: int}> $backlog */
        $backlog = DB::connection($config['connection'] ?? null)
            ->table($config['table'])
            ->select('queue')
            ->selectRaw('count(*) as size, min(created_at) as oldest_created_at')
            ->groupBy('queue')
            ->get()
            ->keyBy('queue')
            ->all();

        $queues = array_unique([$config['queue'], ...array_keys($backlog)]);
        sort($queues);

        return array_map(fn (string $queue): array => [
            'queue' => $queue,
            'size' => (int) ($backlog[$queue]->size ?? 0),
            'oldest_job_age' => isset($backlog[$queue])
                ? max(0, now()->getTimestamp() - (int) $backlog[$queue]->oldest_created_at)
                : null,
        ], $queues);
    }
}
