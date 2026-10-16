<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\NotificationType;
use App\Models\Commit;
use App\Models\Notification;
use App\Queries\NotificationBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final readonly class DeleteCommit
{
    public function __construct(private UpdateCommitHotScores $updateCommitHotScores)
    {
        //
    }

    /**
     * Soft deleting keeps the row for its replies and quotes, so its notifications, the notifications about its replies and
     * quotes, and its images are removed here instead of by the foreign key.
     */
    public function handle(Commit $commit): void
    {
        $imagePaths = DB::transaction(function () use ($commit): array {
            $commit = Commit::query()->whereKey($commit->id)->lockForUpdate()->first();

            if (! $commit instanceof Commit) {
                return [];
            }

            Notification::query()
                ->where('commit_id', $commit->id)
                ->orWhere(fn (NotificationBuilder $query): NotificationBuilder => $query
                    ->where('type', NotificationType::Replied)
                    ->whereIn('commit_id', $commit->replies()->select('id')))
                ->orWhere(fn (NotificationBuilder $query): NotificationBuilder => $query
                    ->where('type', NotificationType::Quoted)
                    ->whereIn('commit_id', $commit->quotes()->select('id')))
                ->delete();

            $imagePaths = $commit->images()->pluck('path')->all();

            $commit->images()->delete();
            $commit->delete();

            $this->updateCommitHotScores->handle([$commit->threadRootId()]);

            return $imagePaths;
        });

        Storage::disk()->delete($imagePaths);
    }
}
