<?php

declare(strict_types=1);

namespace App\Queries\LogQueries;

use App\Contracts\Queries\LogQuery;
use App\Models\Commit;
use App\Models\User;
use App\Queries\CommitBuilder;

final readonly class LatestLogQuery implements LogQuery
{
    public function builder(?User $user): CommitBuilder
    {
        return Commit::query()
            ->visibleTo($user)
            ->withoutSections()
            ->withoutMutedAuthorsFor($user)
            ->topLevel()
            ->withVisibleReplyCount($user)
            ->withReplyPreviews($user)
            ->withCardDataFor($user)
            ->latest()
            ->latest('id');
    }
}
