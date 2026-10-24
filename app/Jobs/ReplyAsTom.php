<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\CreateTomReply;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use RuntimeException;
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
        } catch (Throwable) {
            throw new RuntimeException('Tom reply failed for commit '.$this->commitId.'.');
        }
    }
}
