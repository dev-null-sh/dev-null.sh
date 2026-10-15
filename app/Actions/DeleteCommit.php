<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\NotificationType;
use App\Models\Commit;
use App\Models\Notification;
use App\Queries\NotificationBuilder;
use Illuminate\Support\Facades\DB;

final readonly class DeleteCommit
{
    /**
     * Soft deleting keeps the row for its replies, so its notifications and reply notifications are removed here instead of by the foreign key.
     */
    public function handle(Commit $commit): void
    {
        DB::transaction(function () use ($commit): void {
            $commit = Commit::query()->whereKey($commit->id)->lockForUpdate()->first();

            if (! $commit instanceof Commit) {
                return;
            }

            Notification::query()
                ->where('commit_id', $commit->id)
                ->orWhere(fn (NotificationBuilder $query): NotificationBuilder => $query
                    ->where('type', NotificationType::Replied)
                    ->whereIn('commit_id', $commit->replies()->select('id')))
                ->delete();

            $commit->delete();
        });
    }
}
