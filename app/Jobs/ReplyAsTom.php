<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\CreateTomReply;
use App\Exceptions\CommitProcessingFailed;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Throwable;

#[Backoff([60, 600])]
#[Timeout(900)]
#[Tries(3)]
final class ReplyAsTom implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $commitId)
    {
        //
    }

    public function uniqueId(): string
    {
        return $this->commitId;
    }

    public function handle(CreateTomReply $action): void
    {
        try {
            $action->handle($this->commitId);
        } catch (Throwable $throwable) {
            throw new CommitProcessingFailed('Tom reply', $this->commitId, $throwable);
        }
    }
}
