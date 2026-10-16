<?php

declare(strict_types=1);

namespace App\Actions;

use Illuminate\Contracts\Queue\Factory as Queue;
use Illuminate\Queue\Failed\FailedJobProviderInterface;

final readonly class CreateFailedJobRetry
{
    public function __construct(private FailedJobProviderInterface $failer, private Queue $queue)
    {
        //
    }

    /**
     * Pushes the failed job back onto the queue it failed on, as `queue:retry` does, then forgets it. A job that is
     * already gone was retried or forgotten by someone else, so there is nothing left to do.
     */
    public function handle(string $uuid): void
    {
        /** @var object{connection: string, queue: string, payload: string}|null $failedJob */
        $failedJob = $this->failer->find($uuid);

        if ($failedJob === null) {
            return;
        }

        /** @var array<string, mixed> $payload */
        $payload = json_decode($failedJob->payload, true);

        $this->queue->connection($failedJob->connection)
            ->pushRaw(json_encode([...$payload, 'attempts' => 0], JSON_THROW_ON_ERROR), $failedJob->queue);

        $this->failer->forget($uuid);
    }
}
