<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Commit;
use App\Models\User;

final readonly class PinCommit
{
    public function handle(User $user, Commit $commit): void
    {
        User::withoutTimestamps(fn (): int => User::query()->whereKey($user->id)->update(['pinned_commit_id' => $commit->id]));
    }
}
