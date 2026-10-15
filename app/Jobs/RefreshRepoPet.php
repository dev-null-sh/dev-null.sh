<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\RepoPetStatus;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\Attributes\UniqueFor;
use Throwable;

#[Tries(1)]
#[Timeout(45)]
#[UniqueFor(120)]
final class RefreshRepoPet implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /** @param array{repository: string, repository_id: int, workflow_id: int, workflow: string} $configuration */
    public function __construct(public readonly array $configuration)
    {
        //
    }

    public function uniqueId(): string
    {
        return RepoPetStatus::key($this->configuration);
    }

    public function handle(RepoPetStatus $status): void
    {
        $status->refresh($this->configuration);
    }

    public function failed(?Throwable $exception): void
    {
        resolve(RepoPetStatus::class)->unavailable($this->configuration);
    }
}
