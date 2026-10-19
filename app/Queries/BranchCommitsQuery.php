<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Commit;
use App\Models\User;

final readonly class BranchCommitsQuery
{
    public function builder(string $branch, ?User $user, bool $trending = false): CommitBuilder
    {
        $query = Commit::query()
            ->visibleTo($user)
            ->withoutMutedAuthorsFor($user)
            ->topLevel()
            ->onBranch($branch)
            ->withVisibleReplyCount($user)
            ->withReplyPreviews($user)
            ->withCardDataFor($user);

        if ($trending) {
            return $query->orderByDesc('hot_score')->orderByDesc('id');
        }

        return $query->latest()->latest('id');
    }
}
