<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\UpdateCommitHeadlines;
use App\Exceptions\CommitProcessingFailed;
use App\Models\Commit;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Throwable;

#[Backoff([60, 600])]
#[Timeout(120)]
#[Tries(3)]
#[DeleteWhenMissingModels]
final class LinkCommitHeadlines implements ShouldQueue
{
    use Queueable;

    /** @param list<string> $names */
    public function __construct(public readonly Commit $commit, public readonly array $names, public readonly string $bodyHash)
    {
        //
    }

    public function handle(UpdateCommitHeadlines $action): ?int
    {
        try {
            return $action->handle($this->commit, $this->names, $this->bodyHash);
        } catch (Throwable $throwable) {
            throw new CommitProcessingFailed('Headline assignment', $this->commit->id, $throwable);
        }
    }
}
