<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\StarFlavor;
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
     * Upserted rather than synced, which would read every star the user has given. Starring again only changes the flavor.
     */
    public function handle(User $user, Commit $commit, ?StarFlavor $flavor = null): void
    {
        DB::transaction(function () use ($user, $commit, $flavor): void {
            $user->starredCommits()->newPivotStatement()->upsert([
                'user_id' => $user->id,
                'commit_id' => $commit->id,
                'flavor' => $flavor?->value,
                'created_at' => now(),
                'updated_at' => now(),
            ], ['user_id', 'commit_id'], ['flavor', 'updated_at']);

            $this->createStarNotification->handle($user, $commit);

            $this->updateCommitHotScores->handle([$commit->id]);
        });
    }
}
