<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Commit;
use App\Models\User;

final readonly class StarCommit
{
    public function handle(User $user, Commit $commit): void
    {
        $user->starredCommits()->syncWithoutDetaching([$commit->id]);
    }
}
