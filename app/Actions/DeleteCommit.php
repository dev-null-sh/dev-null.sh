<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\NotificationType;
use App\Jobs\EraseDeletedCommit;
use App\Models\Commit;
use App\Models\Notification;
use App\Services\CommitTranslations;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final readonly class DeleteCommit
{
    public function __construct(private UpdateCommitHotScores $updateCommitHotScores)
    {
        //
    }

    /**
     * Soft deleting keeps the row for its replies and quotes, so its notifications, the notifications about its replies and
     * quotes are removed here instead of by the foreign key, one indexed query each.
     * Reports stay so deleting a commit cannot clear its author's moderation hold.
     * Its content and images are kept until the undo window closes, so undoing the deletion brings them back too.
     */
    public function handle(Commit $commit): Commit
    {
        return DB::transaction(function () use ($commit): Commit {
            $commit = Commit::withTrashed()->lockForUpdate()->findOrFail($commit->id);

            if ($commit->trashed()) {
                return $commit;
            }

            Notification::query()->where('commit_id', $commit->id)->delete();
            Notification::query()->where('type', NotificationType::Replied)->whereIn('commit_id', $commit->replies()->select('id'))->delete();
            Notification::query()->where('type', NotificationType::Quoted)->whereIn('commit_id', $commit->quotes()->select('id'))->delete();

            $commit->delete();

            Cache::forget(resolve(CommitTranslations::class)->key($commit));

            $this->updateCommitHotScores->handle([$commit->id]);

            dispatch(new EraseDeletedCommit($commit))
                ->delay(now()->addSeconds(Commit::RESTORABLE_SECONDS))
                ->afterCommit();

            return $commit->refresh();
        }, attempts: 3);
    }
}
