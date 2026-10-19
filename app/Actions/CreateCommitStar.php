<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Commit;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class CreateCommitStar
{
    public function __construct(
        private CreateStarNotification $createStarNotification,
        private UpdateCommitHotScores $updateCommitHotScores,
    ) {
        //
    }

    /**
     * Inserted unless already there rather than synced, which would read every star the user has given.
     */
    public function handle(User $user, Commit $commit): void
    {
        DB::transaction(function () use ($user, $commit): void {
            $inserted = $user->starredCommits()->newPivotStatement()->insertOrIgnore([
                'user_id' => $user->id,
                'commit_id' => $commit->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($inserted === 0) {
                return;
            }

            $this->createStarNotification->handle($user, $commit);

            $this->updateCommitHotScores->handle([$commit->id]);
        });
    }
}
