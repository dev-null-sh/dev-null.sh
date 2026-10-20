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
use Illuminate\Support\Facades\DB;
use Throwable;

#[Backoff([60, 600])]
#[Timeout(180)]
#[Tries(3)]
#[DeleteWhenMissingModels]
final class ModerateCommit implements ShouldQueue
{
    use Queueable;

    public readonly string $version;

    public function __construct(public readonly Commit $commit)
    {
        $this->version = $commit->version();
    }

    /**
     * A commit that was moderated or deleted in the meantime no longer needs the AI's decision. Once every attempt
     * has failed, the commit is simply left without one.
     */
    public function handle(UpdateCommitAiDecision $action, UpdateCommitStatuses $updateCommitStatuses): void
    {
        $commit = $this->commit->fresh();
        if (! $commit instanceof Commit || $commit->trashed() || $commit->version() !== ($this->version ?? $this->commit->id)) {
            return;
        }

        if ($commit->status !== CommitStatus::Pending) {
            return;
        }

        if ($commit->user?->hasGoodReputation()) {
            DB::transaction(function () use ($updateCommitStatuses): void {
                $current = Commit::query()->lockForUpdate()->find($this->commit->id);
                if ($current instanceof Commit && $current->version() === ($this->version ?? $this->commit->id)) {
                    $updateCommitStatuses->handle([$current->id], CommitStatus::Approved, onlyPending: true);
                }
            });

            return;
        }

        try {
            $action->handle($commit);
        } catch (Throwable $throwable) {
            throw new CommitProcessingFailed('Moderation', $this->commit->id, $throwable);
        }
    }
}
