<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Commit;
use App\Models\User;

final readonly class HashtagCommitsQuery
{
    public function builder(string $tag, ?User $user, bool $trending = false): CommitBuilder
    {
        $query = Commit::query()
            ->visibleTo($user)
            ->withoutMutedAuthorsFor($user)
            ->topLevel()
            ->onBranch($tag)
            ->withVisibleReplyCount($user)
            ->withReplyPreviews($user)
            ->withCardDataFor($user);

        if ($trending) {
            return $query->orderByDesc('hot_score')->orderByDesc('id');
        }

        return $query->latest()->latest('id');
    }
}
