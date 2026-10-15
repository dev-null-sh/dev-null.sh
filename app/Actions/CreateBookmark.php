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
        Bookmark::query()->firstOrCreate([
            'user_id' => $user->id,
            'commit_id' => $commit->id,
        ]);
    }
}
