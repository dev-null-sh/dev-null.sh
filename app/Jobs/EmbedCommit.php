<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\UpdateCommitEmbedding;
use App\Models\Commit;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;
use Illuminate\Queue\Attributes\Tries;

#[Backoff([60, 600])]
#[Tries(3)]
#[DeleteWhenMissingModels]
final class EmbedCommit implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Commit $commit)
    {
        //
    }

    /**
     * A commit deleted in the meantime no longer needs an embedding. Once every attempt has failed, the commit is
     * simply left without one, and the backfill command picks it up on its next run.
     */
    public function handle(UpdateCommitEmbedding $action): void
    {
        if ($this->commit->trashed()) {
            return;
        }

        $action->handle($this->commit);
    }
}
