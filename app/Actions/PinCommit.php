<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Commit;
use App\Models\User;
use InvalidArgumentException;

final readonly class PinCommit
{
    public function handle(User $user, Commit $commit): void
    {
        throw_if($commit->user_id !== $user->id || $commit->parent_id !== null || $commit->trashed(), InvalidArgumentException::class, 'Only an own nondeleted top-level commit can be pinned.');

        User::withoutTimestamps(fn (): int => User::query()->whereKey($user->id)->update(['pinned_commit_id' => $commit->id]));
    }
}
