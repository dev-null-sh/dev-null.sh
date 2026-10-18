<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\UpdateCommitAiDecision;
use App\Actions\UpdateCommitStatuses;
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
     * A commit that was moderated or deleted in the meantime no longer needs the AI's decision.
     */
    public function handle(UpdateCommitAiDecision $action, UpdateCommitStatuses $updateCommitStatuses): void
    {
        if ($this->commit->status !== CommitStatus::Pending || $this->commit->trashed()) {
            $this->leaveUnchecked();

            return;
        }

        if ($this->commit->user?->hasGoodReputation()) {
            if (! $this->commit->user->hasRecentReports()) {
                $updateCommitStatuses->handle([$this->commit->id], CommitStatus::Approved, onlyPending: true);
            }

            $this->leaveUnchecked();

            return;
        }

        $action->handle($this->commit);
    }

    /**
     * Once every attempt has failed, the commit is left without a decision and simply waits for a moderator.
     */
    public function failed(): void
    {
        $this->leaveUnchecked();
    }

    /**
     * Clears only the pending AI decision, so a decision written in the meantime stays.
     */
    private function leaveUnchecked(): void
    {
        Commit::withTrashed()
            ->whereKey($this->commit->id)
            ->where('ai_decision', CommitStatus::Pending)
            ->update(['ai_decision' => null]);
    }
}
