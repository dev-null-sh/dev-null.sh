<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Bookmark;
use App\Models\Commit;
use App\Models\User;

final readonly class SaveCommit
{
    public function handle(User $user, Commit $commit): void
    {
        Commit::query()->visibleTo($user)->findOrFail($commit->id);

        Bookmark::query()->firstOrCreate([
            'user_id' => $user->id,
            'commit_id' => $commit->id,
        ]);
    }
}
