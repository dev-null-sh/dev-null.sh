<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Bookmark;
use App\Models\Commit;
use App\Models\User;

final readonly class DeleteBookmark
{
    public function handle(User $user, Commit $commit): void
    {
        Bookmark::query()
            ->where('user_id', $user->id)
            ->where('commit_id', $commit->id)
            ->delete();
    }
}
