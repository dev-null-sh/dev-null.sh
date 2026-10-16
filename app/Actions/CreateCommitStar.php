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

    public function handle(User $user, Commit $commit): void
    {
        DB::transaction(function () use ($user, $commit): void {
            $user->starredCommits()->syncWithoutDetaching([$commit->id]);

            $this->createStarNotification->handle($user, $commit);

            $this->updateCommitHotScores->handle([$commit->id]);
        });
    }
}
