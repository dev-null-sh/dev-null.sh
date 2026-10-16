<?php

declare(strict_types=1);

namespace App\Queries\LogQueries;

use App\Contracts\Queries\LogQuery;
use App\Models\Commit;
use App\Models\User;
use App\Queries\CommitBuilder;

final readonly class TrendingLogQuery implements LogQuery
{
    /**
     * Ranked by hot score, so commits that draw stars and replies rise and then give way to newer ones.
     */
    public function builder(?User $user): CommitBuilder
    {
        return Commit::query()
            ->visibleTo($user)
            ->withoutMutedAuthorsFor($user)
            ->topLevel()
            ->withVisibleReplyCount($user)
            ->withReplyPreviews($user)
            ->withCardDataFor($user)
            ->orderByDesc('hot_score')
            ->orderByDesc('id');
    }
}
