<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\CommitStatus;
use App\Enums\CommitUpdate;
use App\Enums\NotificationType;
use App\Enums\UserUpdate;
use App\Events\CommitsUpdated;
use App\Events\ProfilesUpdated;
use App\Events\UsersUpdated;
use App\Jobs\EraseDeletedCommit;
use App\Models\Commit;
use App\Models\Headline;
use App\Models\Notification;
use App\Queries\AdminsQuery;
use App\Services\CommitTranslations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final readonly class DeleteCommit
{
    public function __construct(private UpdateCommitHotScores $updateCommitHotScores, private AdminsQuery $admins)
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

            $notifications = Notification::query()->where('commit_id', $commit->id)
                ->orWhere(fn (Builder $query): Builder => $query->where('type', NotificationType::Replied)->whereIn('commit_id', $commit->replies()->select('id')))
                ->orWhere(fn (Builder $query): Builder => $query->where('type', NotificationType::Quoted)->whereIn('commit_id', $commit->quotes()->select('id')));

            event(new UsersUpdated($notifications->clone()->distinct()->pluck('recipient_id')->all(), UserUpdate::Notifications));

            $notifications->delete();

            Headline::query()->whereIn('id', DB::table('commit_headline')->select('headline_id')->where('commit_id', $commit->id))
                ->update(['summary' => null, 'summary_created_at' => null]);

            $commit->delete();

            Cache::forget(resolve(CommitTranslations::class)->key($commit));

            $this->updateCommitHotScores->handle([$commit->id]);

            $commit->status === CommitStatus::Approved
                ? event(new CommitsUpdated([$commit], CommitUpdate::Visibility))
                : event(new UsersUpdated($this->admins->ids(), UserUpdate::Moderation, ['commits' => [$commit->id]]));

            // The author's other devices show it too, the queue of scheduled commits included.
            event(new UsersUpdated(array_filter([$commit->user_id]), UserUpdate::Commits));
            event(new UsersUpdated(array_filter([$commit->user_id]), UserUpdate::ScheduledCommits));
            event(new ProfilesUpdated([$commit->author_id]));

            dispatch(new EraseDeletedCommit($commit))
                ->delay(now()->addSeconds(Commit::RESTORABLE_SECONDS))
                ->afterCommit();

            return $commit->refresh();
        }, attempts: 3);
    }
}
