<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\CommitStatus;
use App\Enums\NotificationType;
use App\Models\Commit;
use App\Models\Notification;
use Illuminate\Support\Facades\DB;

final readonly class ReopenCommit
{
    /**
     * Puts a moderated commit back in the queue and takes back the approval or rejection its author was told about.
     */
    public function handle(Commit $commit): void
    {
        DB::transaction(function () use ($commit): void {
            Commit::query()->whereKey($commit->id)->update(['status' => CommitStatus::Pending]);

            Notification::query()
                ->where('commit_id', $commit->id)
                ->whereIn('type', [NotificationType::Approved, NotificationType::Rejected])
                ->delete();
        });
    }
}
