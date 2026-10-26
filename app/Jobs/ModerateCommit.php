<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\UpdateCommitAiDecision;
use App\Actions\UpdateCommitStatuses;
use App\Enums\CommitStatus;
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
#[Timeout(180)]
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
    public function handle(UpdateCommitAiDecision $action, UpdateCommitStatuses $updateCommitStatuses): void
    {
        if ($this->commit->status !== CommitStatus::Pending || $this->commit->trashed()) {
            return;
        }

        if ($this->commit->user?->hasGoodReputation()) {
            $updateCommitStatuses->handle([$this->commit->id], CommitStatus::Approved, onlyPending: true);

            return;
        }

        try {
            $action->handle($this->commit);
        } catch (Throwable $throwable) {
            throw new CommitProcessingFailed('Moderation', $this->commit->id, $throwable);
        }
    }
}
