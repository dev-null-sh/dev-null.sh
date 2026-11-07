<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\CommitStatus;
use App\Enums\CommitUpdate;
use App\Enums\UserUpdate;
use App\Events\CommitsUpdated;
use App\Events\ProfilesUpdated;
use App\Events\UsersUpdated;
use App\Models\Commit;
use Illuminate\Support\Facades\DB;

final readonly class CreateCommitRestoration
{
    public function __construct(private UpdateCommitHotScores $updateCommitHotScores)
    {
        //
    }

    public function handle(Commit $commit): Commit
    {
        return DB::transaction(function () use ($commit): Commit {
            if ($commit->quoted_commit_id !== null && $commit->user_id !== null && $commit->body === '') {
                Commit::query()->lockForUpdate()->find($commit->quoted_commit_id);
            }

            $commit = Commit::withTrashed()->lockForUpdate()->findOrFail($commit->id);

            if ($commit->isRestorable()) {
                $replacementExists = $commit->quoted_commit_id !== null
                    && $commit->user_id !== null
                    && $commit->body === ''
                    && Commit::query()
                        ->where('user_id', $commit->user_id)
                        ->where('quoted_commit_id', $commit->quoted_commit_id)
                        ->where('body', '')
                        ->whereKeyNot($commit->id)
                        ->exists();

                if ($replacementExists) {
                    return $commit;
                }

                $commit->restore();
                $this->updateCommitHotScores->handle([$commit->threadRootId()]);

                if ($commit->status === CommitStatus::Approved) {
                    event(new CommitsUpdated([$commit], CommitUpdate::Visibility));
                }

                // The author's other devices show it too, the queue of scheduled commits included.
                event(new UsersUpdated(array_filter([$commit->user_id]), UserUpdate::Commits));
                event(new UsersUpdated(array_filter([$commit->user_id]), UserUpdate::ScheduledCommits));
                event(new ProfilesUpdated([$commit->author_id]));
            }

            return $commit;
        }, attempts: 3);
    }
}
