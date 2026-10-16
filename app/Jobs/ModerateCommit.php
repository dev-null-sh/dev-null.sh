<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\UpdateCommitAiDecision;
use App\Enums\CommitStatus;
use App\Models\Commit;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;
use Illuminate\Queue\Attributes\Tries;

#[Backoff([60, 600])]
#[Tries(3)]
#[DeleteWhenMissingModels]
final class ModerateCommit implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Commit $commit)
    {
        //
    }

    /**
     * A commit that was moderated or deleted in the meantime no longer needs the AI's decision. Once every attempt
     * has failed, the commit is simply left without one.
     */
    public function handle(UpdateCommitAiDecision $action): void
    {
        if ($this->commit->status !== CommitStatus::Pending || $this->commit->trashed()) {
            return;
        }

        $action->handle($this->commit);
    }
}
