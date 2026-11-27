<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\CreateHeadlineSummary;
use App\Exceptions\HeadlineSummaryFailed;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Throwable;

#[Backoff([60, 600])]
#[Timeout(120)]
#[Tries(3)]
final class GenerateHeadlineSummary implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $headlineId)
    {
        //
    }

    public function uniqueId(): string
    {
        return $this->headlineId;
    }

    public function handle(CreateHeadlineSummary $action): void
    {
        try {
            $action->handle($this->headlineId);
        } catch (Throwable $throwable) {
            throw new HeadlineSummaryFailed($this->headlineId, $throwable);
        }
    }
}
