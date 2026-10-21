<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Commit;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class DeleteCommitStar
{
    public function __construct(
        private DeleteStarNotification $deleteStarNotification,
        private UpdateCommitHotScores $updateCommitHotScores,
    ) {
        //
    }

    public function handle(User $user, Commit $commit): void
    {
        DB::transaction(function () use ($user, $commit): void {
            $deleted = $user->starredCommits()->detach($commit->id);

            $this->deleteStarNotification->handle($user, $commit);

            if ($deleted > 0) {
                $this->updateCommitHotScores->handle([$commit->id]);
            }
        });
    }
}
