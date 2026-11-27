<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Commit;
use App\Models\Headline;
use App\Models\User;
use Illuminate\Support\Collection;

final readonly class HeadlineCommitsQuery
{
    /**
     * @return Collection<int, Commit>
     */
    public function get(Headline $headline, User $viewer): Collection
    {
        return Commit::query()
            ->whereAttachedTo($headline, 'headlines')
            ->where('created_at', '>=', now()->subWeek())
            ->visibleTo($viewer)
            ->withoutMutedAuthorsFor($viewer)
            ->withVisibleReplyCount($viewer)
            ->withContinuationId($viewer)
            ->withCardDataFor($viewer)
            ->newestFirst()
            ->get()
            ->toBase();
    }
}
