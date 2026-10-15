<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Commit;
use App\Models\User;

final readonly class DeleteCommitStar
{
    public function handle(User $user, Commit $commit): void
    {
        $user->starredCommits()->detach($commit->id);
    }
}
