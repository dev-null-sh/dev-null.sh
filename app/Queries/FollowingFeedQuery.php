<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Commit;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

final readonly class FollowingFeedQuery
{
    /**
     * The latest approved commits of the users the given user follows, as anyone may see them.
     *
     * @return Collection<int, Commit>
     */
    public function get(User $user, int $limit = 50): Collection
    {
        $following = $user->following();

        return Commit::query()
            ->whereIn('user_id', $following->newPivotQuery()->select($following->getRelatedPivotKeyName()))
            ->approved()
            ->topLevel()
            ->with('user')
            ->latest()
            ->latest('id')
            ->limit($limit)
            ->get();
    }
}
