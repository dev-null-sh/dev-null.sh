<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Bookmark;
use App\Models\Commit;
use App\Models\User;

final readonly class CreateBookmark
{
    public function handle(User $user, Commit $commit): void
    {
        $now = now();

        Bookmark::query()->insertOrIgnore([
            'user_id' => $user->id,
            'commit_id' => $commit->id,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
