<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Commit;
use App\Models\User;
use Illuminate\Support\Collection;

final readonly class CommitRepliesQuery
{
    /**
     * @return Collection<int, Commit>
     */
    public function get(Commit $commit, User $user): Collection
    {
        return $commit->replies()
            ->visibleTo($user)
            ->withVisibleReplyCount($user)
            ->with('user')
            ->oldest()
            ->oldest('id')
            ->limit(100)
            ->get()
            ->toBase();
    }
}
